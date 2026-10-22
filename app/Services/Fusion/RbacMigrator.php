<?php

namespace App\Services\Fusion;

use App\Models\Transaction\UserScope;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * RbacMigrator — the RBAC migration stage of estate-db-fusion (spec task 5,
 * the CENTERPIECE). Driven by the `fusion:migrate-rbac` command
 * ({@see \App\Console\Commands\Fusion\MigrateRbacCommand}).
 *
 * Spec authority:
 *   - design.md sequence diagram (b) "user migration with scope assignment",
 *     LLD-1 (LOCKED role map), LLD-6 (seed-only tiers), LLD-7 (cross-estate
 *     dedup), LLD-9 (idempotency), LLD-10 (RbacMigrator::migrate pseudocode).
 *   - requirements.md R5 (password carry), R6 (role map / skip unmapped),
 *     R7 (tiered access), R8 (dedup), R9.4 (never map seeded tiers),
 *     R14 (idempotent re-run).
 *
 * ## What THIS task (5.3) implements — identity migration only
 *
 * `migrate()` iterates the 12 source DBs (per estate) via SourceRegistry; for
 * each it opens the source PostgreSQL connection and SELECTs ACTIVE tc_user
 * rows, then per user:
 *   1. detects active state defensively (see {@see isActive()});
 *   2. resolves role_code via the LOCKED map ({@see RoleMap::resolveRoleCode()});
 *      an UNMAPPED user_role is recorded to the error ledger and the user is
 *      SKIPPED — a role is NEVER guessed (R6.2/R6.3);
 *   3. resolves the owning TenantRef via {@see TenantResolver::forEstate()}
 *      (needed by the access/scope extension points 5.5/5.6);
 *   4. builds the within-company {@see DedupKey}; a user with no identity is
 *      recorded to the error ledger and skipped (R8.1);
 *   5. inserts the target tc_user with the $2y$12$ bcrypt hash carried
 *      VERBATIM via a RAW DB::table()->insert() — NO Eloquent mutator, NO
 *      rehash, NO reset (R5.2/R5.4). Hash::check(knownPlaintext, carriedHash)
 *      therefore still passes after migration;
 *   6. records the DedupKey -> target user_id mapping to the fusion_ledger
 *      (entry_type 'user_dedup', R14.2);
 *   7. calls the per-user extension points {@see assignAccess()} and
 *      {@see assignScopes()} — STUBS this task leaves for 5.5/5.6 to fill.
 *
 * ## EXTENSION POINTS for later tasks (deliberate seams — see each method)
 *   - {@see assignAccess()}      task 5.5 — tiered tc_user_access per role level
 *     (IMPLEMENTED; creates exactly one access row per user by role level).
 *   - {@see assignScopes()}      task 5.6 — additive tc_user_scope estate rows
 *     (IMPLEMENTED; upserts one estate scope per estate for estate-level users
 *     only, idempotent via the scope_applied ledger).
 *   - {@see resolveDedupConflict()} task 5.8 — cross-estate dedup conflict
 *     handling (IMPLEMENTED; first-seen password wins + logged mismatch naming
 *     both DBs; highest-authority role wins on the single access row, reshaped
 *     via the shared {@see scopeShapeFor()}). The cross-COMPANY global username/
 *     email UNIQUE collision suffixing (R8.6) is also task 5.8 but handled at
 *     INSERT time in the new-identity branch via {@see suffixCollidingIdentity()},
 *     because a different-company identity has a different DedupKey and is thus
 *     treated as NEW, not existing.
 *   - idempotency (task 5.10 — IMPLEMENTED) — the user_dedup ledger is consulted
 *     before inserting so a re-run does not duplicate users ({@see findExistingUserId()}),
 *     AND the scope_applied ledger is consulted in {@see assignScopes()} as the
 *     PRIMARY guard so an already-applied estate scope is a clean no-op (skip the
 *     upsert and the redundant ledger write; the (user_id, scope_type, scope_id)
 *     upsert is the belt-and-suspenders fallback). All ledger writes
 *     ({@see recordUserDedup()}, {@see recordScopeApplied()}, {@see recordNote()},
 *     {@see recordError()}) are stable-key upserts so a re-run does not grow
 *     fusion_ledger. The migrate() summary surfaces scopesApplied/scopesSkipped/
 *     accessUnchanged so an operator can SEE a second run insert 0 users / 0 new
 *     scopes.
 *
 * ## Ordering (documented so 5.5/5.6/5.8/5.10 slot in without re-sequencing)
 *   per source DB:
 *     open source -> SELECT active tc_user
 *       per row:
 *         detect active -> resolve role_code (skip+ledger if unmapped)
 *           -> resolve TenantRef -> build DedupKey (skip+ledger if no identity)
 *             -> ledger lookup by DedupKey
 *                NEW identity:  [R8.6 collision check: adopt-or-suffix] ->
 *                               insertUser (raw pw) -> ledger user_dedup
 *                               -> assignAccess() [5.5] -> assignScopes() [5.6]
 *                EXISTING:      resolveDedupConflict() [5.8: first-seen pw wins
 *                               + highest-authority role flip]
 *                               -> assignScopes() [5.6] (add this estate's scope)
 *     close source
 *
 * ## Never maps the seeded tiers (R9.4)
 * The LOCKED role_map contains only estate-admin-and-below role_codes; it can
 * never yield super_admin/country_admin/company_admin. {@see assertNotSeededTier()}
 * is a defensive guard so a future map edit cannot smuggle a seeded tier in.
 *
 * This service holds the behaviour so it is unit/behaviour testable; the
 * command is a thin wrapper that wires options/output and calls {@see migrate()}.
 */
class RbacMigrator
{
    /** Target connection name (SQL Server EPMS via ODBC). */
    public const TARGET_CONNECTION = 'odbc_sqlsrv';

    /** fusion_ledger entry_type: DedupKey -> target_user_id (R14.2). */
    public const LEDGER_USER_DEDUP = 'user_dedup';

    /** fusion_ledger entry_type: a skipped row / unmapped value (R6.2/R13.5). */
    public const LEDGER_ERROR = 'error';

    /** fusion_ledger entry_type: a non-fatal conflict/divergence note (R8.4/R8.5). */
    public const LEDGER_NOTE = 'note';

    /**
     * fusion_ledger entry_type: an applied additive scope marker so re-running
     * does not re-insert an estate/country scope (R14.2, design LLD-9). Matches
     * the ledger migration's documented scope_applied shape:
     *   key_text   = "user_id|scope_type|scope_id"
     *   value_text = '1'
     */
    public const LEDGER_SCOPE_APPLIED = 'scope_applied';

    /**
     * Role tiers that are SEEDED (LLD-6) and must NEVER originate from a source
     * user mapping (R9.4). Used only as a defensive guard — the LOCKED role_map
     * already excludes them.
     *
     * @var array<int, string>
     */
    private const SEEDED_TIERS = ['super_admin', 'country_admin', 'company_admin'];

    private readonly ConnectionInterface $target;

    public function __construct(
        private readonly SourceRegistry $registry,
        private readonly TenantResolver $resolver,
        ?ConnectionInterface $target = null,
    ) {
        $this->target = $target ?? DB::connection(config('fusion.target_connection', self::TARGET_CONNECTION));
    }

    /**
     * Run the RBAC migration across all 12 source DBs and return a summary.
     *
     * Progress is reported through the optional $progress callback (the command
     * passes one that writes to the console) so the service has no console I/O
     * dependency and stays testable.
     *
     * @param  callable(string $level, string $message):void|null  $progress
     * @param  array{
     *     only_db?: ?string,
     *     limit_per_db?: ?int
     * }  $options  optional run constraints:
     *     - only_db: restrict the run to a single source db_name (for a guarded
     *       single-DB validation run — see the command's --only-db).
     *     - limit_per_db: cap the number of source rows read per DB (for a
     *       guarded few-rows validation run — see --limit).
     * @return array{
     *     databases:int,
     *     usersRead:int, usersInserted:int, usersExisting:int,
     *     skippedInactive:int, skippedUnmapped:int, skippedNoIdentity:int,
     *     skippedNoEmail:int,
     *     errors:int, notes:int,
     *     scopesApplied:int, scopesSkipped:int, accessUnchanged:int,
     *     activeFlagSamples:array<string,int>
     * }
     *
     * Idempotency counters (task 5.10, R14.2/R14.5) make a re-run OBSERVABLE:
     *   - usersInserted   — new tc_user rows this run (0 on a clean re-run).
     *   - usersExisting   — identities already in the user_dedup ledger
     *                       (dedup / re-run); every user on a clean re-run.
     *   - scopesApplied   — estate scopes WRITTEN this run (upsert + ledger
     *                       marker); 0 on a clean re-run because the
     *                       scope_applied guard short-circuits them.
     *   - scopesSkipped   — estate scopes SHORT-CIRCUITED by the scope_applied
     *                       ledger guard (the clean-re-run no-op path).
     *   - accessUnchanged — access rows that already existed before their
     *                       updateOrInsert (no new access row inserted).
     * A second run is a verifiable no-op when usersInserted = scopesApplied = 0.
     */
    public function migrate(?callable $progress = null, array $options = []): array
    {
        $say = $progress ?? static fn (string $level, string $message) => null;

        $onlyDb = $options['only_db'] ?? null;
        $limitPerDb = $options['limit_per_db'] ?? null;

        $summary = [
            'databases'         => 0,
            'usersRead'         => 0,
            'usersInserted'     => 0,
            'usersExisting'     => 0,
            'skippedInactive'   => 0,
            'skippedUnmapped'   => 0,
            'skippedNoIdentity' => 0,
            'skippedNoEmail'    => 0,
            'errors'            => 0,
            'notes'             => 0,
            // Idempotency counters (task 5.10) — a clean re-run shows
            // usersInserted=0, scopesApplied=0, scopesSkipped>0, so the no-op is
            // observable in the command summary.
            'scopesApplied'     => 0,
            'scopesSkipped'     => 0,
            'accessUnchanged'   => 0,
            'activeFlagSamples' => [],
        ];

        foreach ($this->registry->all() as $descriptor) {
            if ($onlyDb !== null && $descriptor->dbName !== $onlyDb) {
                continue;
            }

            $tenant = $this->resolver->forEstate($descriptor->estateCode);
            if ($tenant === null) {
                $this->recordError(
                    companyId: null,
                    key: 'tenant_unresolved|'.$descriptor->estateCode,
                    message: sprintf(
                        'db=%s estate=%s: no TenantRef (estate not seeded) — whole DB skipped. Run fusion:seed-tenants first.',
                        $descriptor->dbName, $descriptor->estateCode,
                    ),
                );
                $summary['errors']++;
                $say('error', sprintf(
                    '  %s (%s): tenant unresolved — DB skipped (seed tenants first).',
                    $descriptor->dbName, $descriptor->estateCode,
                ));
                continue;
            }

            $say('info', sprintf(
                'Migrating users from %s (estate %s, company %s)...',
                $descriptor->dbName, $descriptor->estateCode, $descriptor->companyCode,
            ));

            $summary['databases']++;

            try {
                $this->migrateOneDatabase($descriptor, $tenant, $limitPerDb, $say, $summary);
            } catch (Throwable $e) {
                $this->recordError(
                    companyId: $tenant->companyId,
                    key: 'db_failure|'.$descriptor->dbName,
                    message: sprintf('db=%s: migration failed: %s', $descriptor->dbName, $e->getMessage()),
                );
                $summary['errors']++;
                $say('error', sprintf('  %s: FAILED — %s', $descriptor->dbName, $e->getMessage()));
            } finally {
                SourceConnection::close();
            }
        }

        return $summary;
    }

    /**
     * Migrate the active users of a single source DB. Extracted so the per-DB
     * open/close and the per-row pipeline read clearly and so later tasks can
     * reuse the same seam.
     *
     * @param  int|null  $limitPerDb
     * @param  callable(string,string):void  $say
     * @param  array<string,mixed>  $summary  mutated in place with counters.
     */
    private function migrateOneDatabase(
        SourceDescriptor $descriptor,
        TenantRef $tenant,
        ?int $limitPerDb,
        callable $say,
        array &$summary,
    ): void {
        $pg = SourceConnection::for($descriptor->dbName);

        $query = $pg->table('tc_user');
        if ($limitPerDb !== null) {
            $query->limit($limitPerDb);
        }

        $rows = $query->get();

        foreach ($rows as $rowObject) {
            $summary['usersRead']++;
            /** @var array<string,mixed> $row */
            $row = (array) $rowObject;

            // 1. Active detection (defensive — see isActive()).
            $rawStatus = $row['user_status'] ?? null;
            $flagKey = $this->activeFlagSampleKey($rawStatus);
            $summary['activeFlagSamples'][$flagKey] = ($summary['activeFlagSamples'][$flagKey] ?? 0) + 1;

            if (! $this->isActive($rawStatus)) {
                $summary['skippedInactive']++;
                continue;
            }

            // 2. Role resolution via the LOCKED map (never guess — R6.2/R6.3).
            $userRole = (int) ($row['user_role'] ?? 0);
            $roleCode = RoleMap::resolveRoleCode($userRole);
            if ($roleCode === null) {
                $this->recordError(
                    companyId: $tenant->companyId,
                    key: sprintf('unmapped_role|%s|%s|%s', $descriptor->dbName, $row['user_id'] ?? '?', $userRole),
                    message: sprintf(
                        'db=%s user_id=%s user_login=%s: user_role=%d is NOT in the LOCKED map — user SKIPPED (role never guessed).',
                        $descriptor->dbName, $row['user_id'] ?? '?', $row['user_login'] ?? '?', $userRole,
                    ),
                );
                $summary['skippedUnmapped']++;
                $summary['errors']++;
                $say('warn', sprintf(
                    '  SKIP unmapped role: db=%s user_id=%s user_role=%d',
                    $descriptor->dbName, $row['user_id'] ?? '?', $userRole,
                ));
                continue;
            }

            // R9.4 defensive guard — the LOCKED map can never yield a seeded
            // tier, but never let a future map edit smuggle one in here.
            $this->assertNotSeededTier($roleCode, $descriptor, $row);

            // 3. Build the within-company dedup key (skip if no identity — R8.1).
            try {
                $dedupKey = DedupKey::fromSourceRow($row, $descriptor->companyCode);
            } catch (InvalidArgumentException $e) {
                $this->recordError(
                    companyId: $tenant->companyId,
                    key: sprintf('no_identity|%s|%s', $descriptor->dbName, $row['user_id'] ?? '?'),
                    message: sprintf(
                        'db=%s user_id=%s: no dedup identity (internal/employee code + login all empty) — user SKIPPED. %s',
                        $descriptor->dbName, $row['user_id'] ?? '?', $e->getMessage(),
                    ),
                );
                $summary['skippedNoIdentity']++;
                $summary['errors']++;
                $say('warn', sprintf(
                    '  SKIP no-identity: db=%s user_id=%s',
                    $descriptor->dbName, $row['user_id'] ?? '?',
                ));
                continue;
            }

            // 3b. HARDENING (task-6 fix) — the target tc_user.email column is
            // NOT NULL, so a source user with a NULL/empty user_email would make
            // the raw insert in insertUser() throw and ABORT the whole per-DB
            // loop (observed live: user "PRABA" in gomali26 / company 1PDP).
            // Mirror the no-identity path: SKIP the user, record a STABLE error
            // ledger entry, bump skippedNoEmail + errors, then continue so the
            // rest of the DB still migrates. Applied here (regardless of the
            // new/existing/adopt/suffix branch below) because every branch can
            // reach the raw insert. Idempotent — recordError() upserts by key.
            if ($this->stringOrNull($row['user_email'] ?? null) === null) {
                $this->recordError(
                    companyId: $tenant->companyId,
                    key: sprintf('no_email|%s|%s', $descriptor->dbName, $row['user_id'] ?? '?'),
                    message: sprintf(
                        'db=%s user_id=%s user_login=%s: user_email is NULL/empty — user SKIPPED because target tc_user.email is NOT NULL.',
                        $descriptor->dbName, $row['user_id'] ?? '?', $row['user_login'] ?? '?',
                    ),
                );
                $summary['skippedNoEmail']++;
                $summary['errors']++;
                $say('warn', sprintf(
                    '  SKIP no-email: db=%s user_id=%s user_login=%s',
                    $descriptor->dbName, $row['user_id'] ?? '?', $row['user_login'] ?? '?',
                ));
                continue;
            }

            // 4. Idempotency / dedup lookup by DedupKey (R14.2 + cross-estate R8).
            $existingUserId = $this->findExistingUserId($dedupKey);

            if ($existingUserId === null) {
                // The DedupKey is NOT in the ledger, so this is a NEW identity
                // for THIS company. But the target enforces global UNIQUE on
                // username/email, so this row's user_login/email may still clash
                // with an already-migrated row. Two distinct cases must be told
                // apart (R8.6 vs the same-identity adopt guard):
                //
                //   (a) ADOPT — the clashing target row is actually the SAME
                //       person (its user_dedup ledger maps to THIS dedupKey, or
                //       it has no ledger mapping at all — e.g. a prior load that
                //       bypassed the ledger). We cannot prove a different person,
                //       so we adopt it to avoid an insert crash and record the
                //       DedupKey -> id mapping.
                //
                //   (b) SUFFIX (R8.6) — the clashing target row is a DIFFERENT
                //       person: it maps to a DIFFERENT DedupKey (necessarily a
                //       different company, since DedupKey is within-company).
                //       Two different people sharing a login across companies
                //       collide on the global UNIQUE. We keep the first-seen
                //       identity intact and DETERMINISTICALLY suffix THIS
                //       (lower-priority, later-seen) one — username@companycode,
                //       email+companycode — then insert it as its own user and
                //       log the collision.
                $preexistingUserId = $this->findPreexistingTargetUser($row);

                if ($preexistingUserId !== null) {
                    $clashDedupKey = $this->dedupKeyForUserId($preexistingUserId);
                    $isSamePerson = $clashDedupKey === null || $clashDedupKey === $dedupKey;

                    if ($isSamePerson) {
                        // (a) ADOPT — same identity already present (no proof of
                        // a different person). Record mapping and treat as existing.
                        $this->recordUserDedup($dedupKey, $preexistingUserId, $tenant->companyId, $descriptor->dbName);
                        $this->recordNote(
                            companyId: $tenant->companyId,
                            key: sprintf('adopted_preexisting|%s', $dedupKey),
                            message: sprintf(
                                'db=%s user_login=%s: target already had a tc_user with this username/email and no '
                                .'conflicting DedupKey mapping; adopted existing id=%d and recorded user_dedup.',
                                $descriptor->dbName, $row['user_login'] ?? '?', $preexistingUserId,
                            ),
                        );
                        $summary['usersExisting']++;
                        $summary['notes']++;
                        $this->assignScopes($preexistingUserId, $roleCode, $tenant, $descriptor, $row, $say, $summary);
                        continue;
                    }

                    // (b) SUFFIX — genuine cross-company username/email collision
                    // (R8.6). Deterministically rewrite THIS row's login/email,
                    // log the collision, then fall through to a normal insert of
                    // the (now unique) identity.
                    $row = $this->suffixCollidingIdentity(
                        $row, $dedupKey, $clashDedupKey, $preexistingUserId, $tenant, $descriptor, $say,
                    );
                    $summary['notes']++;
                }

                // NEW identity: raw-insert the user carrying the hash verbatim.
                // Record the FIRST-SEEN source db_name so a later cross-estate
                // password mismatch note (R8.4) can name both DBs.
                $userId = $this->insertUser($row, $tenant);
                $this->recordUserDedup($dedupKey, $userId, $tenant->companyId, $descriptor->dbName);
                $summary['usersInserted']++;

                // Extension points — 5.5 (access) then 5.6 (scope).
                $this->assignAccess($userId, $roleCode, $tenant, $descriptor, $row, $say, $summary);
                $this->assignScopes($userId, $roleCode, $tenant, $descriptor, $row, $say, $summary);
            } else {
                // EXISTING identity (cross-estate, or a re-run). 5.8 resolves
                // the access conflict (first-seen password already wins because
                // we never re-insert/overwrite the user here); 5.6 still adds
                // this estate's additive scope.
                $summary['usersExisting']++;
                $this->resolveDedupConflict($existingUserId, $dedupKey, $roleCode, $tenant, $descriptor, $row, $say);
                $this->assignScopes($existingUserId, $roleCode, $tenant, $descriptor, $row, $say, $summary);
            }
        }
    }

    /**
     * Insert ONE target tc_user, carrying the $2y$12$ bcrypt hash VERBATIM.
     *
     * CRITICAL (R5.2/R5.4): this is a RAW DB::table()->insert() — it does NOT
     * go through App\Models\Transaction\User, so the password-setting mutator
     * (which would call Hash::needsRehash/Hash::make) is bypassed entirely and
     * the 60-char source hash is stored byte-for-byte. A $2y$12$ hash at
     * BCRYPT_ROUNDS=12 would not be rehashed anyway, but the raw insert removes
     * any doubt: Hash::check(knownSourcePlaintext, target.password) still passes
     * after migration (the acceptance proof — design LLD-11 step 5).
     *
     * Column mapping (verified live target tc_user schema):
     *   username                     <- user_login
     *   email                        <- user_email
     *   password                     <- user_password  (AS-IS, literal hash)
     *   user_name                    <- user_name
     *   user_employee_code           <- user_employee_code
     *   user_internal_employee_code  <- user_internal_employee_code
     *   user_token                   <- user_token (if present)
     *   is_active                    = 1 (only active users reach here)
     *   created_by                   <- user_created_by (audit carry)
     *   created_at / updated_at      <- remapped from source audit, else now()
     *
     * Only columns the live tc_user table actually has are written (schema-drift
     * safe), mirroring TenantRoleSeeder::upsertAdminUser().
     *
     * @param  array<string,mixed>  $row  raw source tc_user row.
     * @return int  the new target tc_user.id.
     */
    private function insertUser(array $row, TenantRef $tenant): int
    {
        $cols = $this->targetColumns('tc_user');
        [$createdAt, $updatedAt] = $this->remapAudit($row);

        $values = array_intersect_key([
            'username'                    => $this->stringOrNull($row['user_login'] ?? null),
            'email'                       => $this->stringOrNull($row['user_email'] ?? null),
            // RAW carry — do NOT transform. The literal $2y$ hash is stored as-is.
            'password'                    => $this->stringOrNull($row['user_password'] ?? null),
            'user_name'                   => $this->stringOrNull($row['user_name'] ?? null),
            'user_employee_code'          => $this->stringOrNull($row['user_employee_code'] ?? null),
            'user_internal_employee_code' => $this->stringOrNull($row['user_internal_employee_code'] ?? null),
            'user_token'                  => $this->stringOrNull($row['user_token'] ?? null),
            'is_active'                   => 1,
            'created_by'                  => $this->stringOrNull($row['user_created_by'] ?? null),
            'created_at'                  => $createdAt,
            'updated_by'                  => $this->stringOrNull($row['user_updated_by'] ?? null),
            'updated_at'                  => $updatedAt,
        ], array_flip($cols));

        $this->target->table('tc_user')->insert($values);

        // Resolve the freshly-assigned identity by the unique username.
        return (int) $this->target->table('tc_user')
            ->where('username', $values['username'])
            ->value('id');
    }

    // =====================================================================
    // EXTENSION POINTS — reserved stubs for later tasks. Each is called once
    // per user at the documented point in migrate()'s per-row pipeline so the
    // later task fills the body WITHOUT re-sequencing this task's flow.
    // =====================================================================

    /**
     * [IMPLEMENTED — task 5.5] Assign the tiered tc_user_access row.
     *
     * Creates EXACTLY ONE tc_user_access row per migrated user, with the
     * tier/scope shape driven by the role LEVEL (resolved via
     * {@see RoleMap::level()}) per design's scope-shape rule (LLD-1 / LLD-10
     * scopeShapeFor() / R7.1–R7.6):
     *   - super   (level 10)      -> country_id NULL, company_id NULL, estate_code NULL   (R7.1)
     *   - country (level 20)      -> country_id = tenant country, company_id NULL,
     *                                estate_code NULL                                     (R7.2)
     *   - company (level 30)      -> company_id = tenant company, country_id NULL,
     *                                estate_code NULL                                     (R7.3)
     *   - estate+ (level >= 35)   -> company_id = tenant company, estate_code = source
     *                                estate, country_id NULL                              (R7.4)
     *
     * role_id is resolved by role_code via {@see roleIdByCode()} (NEVER
     * hard-coded — R6.1/R7). An unresolved role_id is recorded to the error
     * ledger and the access row is SKIPPED (the DB run continues rather than
     * crashing). An unknown level (should not happen — the caller already
     * mapped+guarded the role) is also treated as an error and skipped.
     *
     * Schema-drift safe: only columns the live tc_user_access table actually
     * has are written, via {@see targetColumns()} + array_intersect_key, exactly
     * like {@see insertUser()}. Verified target columns: id (identity), user_id,
     * role_id, country_id (nullable), company_id (nullable), estate_code
     * (nullable), is_active.
     *
     * Idempotency (full re-run idempotency is task 5.10): keyed on user_id via
     * updateOrInsert so there is EXACTLY ONE access row per user (R7.5) — a
     * re-migrated user updates its single row rather than creating a duplicate.
     *
     * @param  callable(string,string):void  $say
     * @param  array<string,mixed>  $row
     */
    private function assignAccess(
        int $userId,
        string $roleCode,
        TenantRef $tenant,
        SourceDescriptor $descriptor,
        array $row,
        callable $say,
        array &$summary,
    ): void {
        // Resolve the target role identity by code — NEVER hard-coded (R6.1/R7).
        $roleId = $this->roleIdByCode($roleCode);
        if ($roleId === null) {
            $this->recordError(
                companyId: $tenant->companyId,
                key: sprintf('access_role_unresolved|%s|%s|%d', $descriptor->dbName, $roleCode, $userId),
                message: sprintf(
                    'db=%s user_id=%d role_code=%s: role_id not found in m_roles — tc_user_access row SKIPPED '
                    .'(run fusion:seed-tenants to seed the role catalog first).',
                    $descriptor->dbName, $userId, $roleCode,
                ),
            );
            $say('warn', sprintf(
                '  SKIP access (role_id unresolved): user_id=%d role_code=%s db=%s',
                $userId, $roleCode, $descriptor->dbName,
            ));

            return;
        }

        // Resolve the tier via role LEVEL (design LLD-1 / LLD-10 scopeShapeFor()).
        $level = RoleMap::level($roleCode);
        if ($level === null) {
            // Defensive: the caller already mapped+guarded the role, so a null
            // level here is a map/level regression. Record and skip rather than
            // write an un-tiered access row.
            $this->recordError(
                companyId: $tenant->companyId,
                key: sprintf('access_level_unresolved|%s|%s|%d', $descriptor->dbName, $roleCode, $userId),
                message: sprintf(
                    'db=%s user_id=%d role_code=%s: no level in RoleMap — tc_user_access row SKIPPED '
                    .'(unexpected; the caller already mapped this role).',
                    $descriptor->dbName, $userId, $roleCode,
                ),
            );
            $say('warn', sprintf(
                '  SKIP access (level unresolved): user_id=%d role_code=%s db=%s',
                $userId, $roleCode, $descriptor->dbName,
            ));

            return;
        }

        // Scope-shape rule — country_id / company_id / estate_code by tier.
        // Shared with resolveDedupConflict() (task 5.8) via scopeShapeFor() so
        // the access-row tier SHAPE is produced by ONE rule in both places (DRY).
        $shape = $this->scopeShapeFor($level, $tenant, $descriptor);
        $countryId = $shape['country_id'];
        $companyId = $shape['company_id'];
        $estateCode = $shape['estate_code'];
        $tierLabel = $shape['tier_label'];

        $cols = $this->targetColumns('tc_user_access');
        $now = now();
        [$createdAt, $updatedAt] = $this->remapAudit($row);

        // Only write columns the live tc_user_access table actually has
        // (schema-drift safe, like insertUser). created_at/updated_at are
        // included because the live target declares them NOT NULL; the audit
        // timestamps are remapped from the source row with a now() fallback.
        $values = array_intersect_key([
            'role_id'     => $roleId,
            'country_id'  => $countryId,
            'company_id'  => $companyId,
            'estate_code' => $estateCode,
            'is_active'   => 1,
            'created_by'  => $this->stringOrNull($row['user_created_by'] ?? null),
            'created_at'  => $createdAt,
            'updated_by'  => $this->stringOrNull($row['user_updated_by'] ?? null),
            'updated_at'  => $updatedAt ?? $now,
        ], array_flip($cols));

        // EXACTLY ONE access row per user (R7.5) — keyed on user_id so a re-run
        // (or a re-migrated identity) updates the single row, never duplicates.
        // [task 5.10] Observe idempotency: if an access row already exists for
        // this user the updateOrInsert is an UPDATE (no new row) — count it as
        // "accessUnchanged" so a re-run is visibly a no-op in the summary. This
        // is observation only; it does NOT change the write (still updateOrInsert
        // keyed on user_id, exactly one access row per user).
        $alreadyHadAccess = $this->target->table('tc_user_access')
            ->where('user_id', $userId)
            ->exists();

        $this->target->table('tc_user_access')->updateOrInsert(
            ['user_id' => $userId],
            $values,
        );

        if ($alreadyHadAccess) {
            $summary['accessUnchanged'] = ($summary['accessUnchanged'] ?? 0) + 1;
        }

        $say('info', sprintf(
            '  access: user_id=%d role=%s tier=%s (country_id=%s company_id=%s estate_code=%s)%s',
            $userId,
            $roleCode,
            $tierLabel,
            $countryId ?? 'NULL',
            $companyId ?? 'NULL',
            $estateCode ?? 'NULL',
            $alreadyHadAccess ? ' [unchanged — row already present]' : '',
        ));
    }

    /**
     * Resolve the tc_user_access SCOPE SHAPE for a role LEVEL — the single
     * source of truth for the tier -> (country_id, company_id, estate_code)
     * rule (design LLD-1 scope matrix / LLD-10 scopeShapeFor(); R7.1–R7.4).
     *
     * Shared by {@see assignAccess()} (task 5.5, new identity) and
     * {@see resolveDedupConflict()} (task 5.8, highest-authority role flip) so
     * both tasks AGREE on the access-row shape and a role flip reshapes the row
     * EXACTLY as a fresh insert would (DRY — R8.5). Extracting it does NOT change
     * assignAccess()'s observable behaviour: the branch arms below are the same
     * ones assignAccess() used inline before the refactor.
     *
     *   - super   (level <= 10) -> country_id NULL, company_id NULL, estate_code NULL
     *   - country (level 20)    -> country_id = tenant country, company_id NULL,
     *                              estate_code NULL
     *   - company (level 30)    -> company_id = tenant company, country_id NULL,
     *                              estate_code NULL
     *   - estate+ (level >= 35) -> company_id = tenant company,
     *                              estate_code = source estate, country_id NULL
     *
     * @return array{country_id:?int, company_id:?int, estate_code:?string, tier_label:string}
     */
    private function scopeShapeFor(int $level, TenantRef $tenant, SourceDescriptor $descriptor): array
    {
        if ($level <= 20) {
            // super (<=10) -> all NULL; country (20) -> country only.
            return [
                'country_id'  => $level === 20 ? $tenant->countryId : null,
                'company_id'  => null,
                'estate_code' => null,
                'tier_label'  => $level === 20 ? 'country' : 'super',
            ];
        }

        if ($level === 30) {
            // company_admin -> company only.
            return [
                'country_id'  => null,
                'company_id'  => $tenant->companyId,
                'estate_code' => null,
                'tier_label'  => 'company',
            ];
        }

        // estate+ (level >= 35) -> company + source estate.
        return [
            'country_id'  => null,
            'company_id'  => $tenant->companyId,
            'estate_code' => $descriptor->estateCode,
            'tier_label'  => 'estate',
        ];
    }

    /**
     * [IMPLEMENTED — task 5.6] Assign the additive tc_user_scope estate row.
     *
     * Additive, per-estate scope — the counterpart of the single tiered
     * tc_user_access row from {@see assignAccess()} (design LLD-7 dedup: one
     * user, one company-level access, N estate scopes).
     *
     * Behaviour (R7.4 / R8.3):
     *   - ONLY estate-level/operational users (role LEVEL >= 35, via
     *     {@see RoleMap::isEstateLevel()}) get an estate scope. For
     *     company/country/super tiers this creates NO tc_user_scope row and
     *     returns (the company tier is already company-wide via tc_user_access;
     *     adding an estate scope would wrongly narrow it — R7.1/R7.2/R7.3/R8.3).
     *   - For an estate-level user it UPSERTs EXACTLY ONE tc_user_scope row of
     *     type 'estate' for THIS source estate ($descriptor->estateCode). Because
     *     this method is called once per estate DB the identity appears in, a
     *     cross-estate deduped user accrues one estate scope PER estate (the
     *     additive behaviour — R8.3).
     *
     * scope_id is the target m_estate.id (NOT the raw estate_code string): the
     * runtime contract (App\Traits\HasCompanyScope::assignedEstateCodes() joins
     * tc_user_scope.scope_id -> m_estate.id to resolve estate_codes) and the
     * data model (design "Data Models": scope_id -> m_estate.id) and the
     * Reconciler check (task 10.2: "every estate tc_user_scope.scope_id resolves
     * to an m_estate of the user's company") all require it. The estate is
     * resolved by (company_id, estate_code) — the UNIQUE key on m_estate. An
     * unresolved estate is recorded to the error ledger and the scope is SKIPPED
     * (the DB run continues rather than writing a dangling scope_id — run
     * fusion:seed-tenants to seed m_estate first).
     *
     * Idempotency: the row is upserted keyed on the natural scope identity
     * (user_id, scope_type='estate', scope_id) so a re-run (or the same identity
     * re-seen in the same estate) updates the single row rather than duplicating
     * it. An applied-scope marker is also recorded to the fusion_ledger
     * (entry_type 'scope_applied', keyed "user_id|estate|scope_id") so task 5.10
     * can treat the whole stage as a clean no-op on re-run.
     *
     * Schema-drift safe: only columns the live tc_user_scope table actually has
     * are written, via {@see targetColumns()} + array_intersect_key, exactly
     * like {@see insertUser()}/{@see assignAccess()}. created_at/updated_at are
     * included because the live target declares them NOT NULL; the audit
     * timestamps are remapped from the source row with a now() fallback.
     *
     * Called for BOTH new and existing users (see migrate()) so a cross-estate
     * user accrues one estate scope per estate DB.
     *
     * [task 5.10] PRIMARY idempotency guard (design LLD-9): before writing the
     * estate scope this method consults the scope_applied ledger for
     * (user_id, 'estate', estate_id). If a marker is present the whole scope
     * write is a clean NO-OP — BOTH the tc_user_scope upsert AND the redundant
     * ledger write are skipped — and `scopesSkipped` is incremented. When the
     * marker is ABSENT the scope is applied (upsert + marker written) and
     * `scopesApplied` is incremented. The (user_id, scope_type, scope_id) upsert
     * remains the belt-and-suspenders fallback for the ledger-absent path, so
     * even a ledger-less replay cannot duplicate a scope row.
     *
     * @param  callable(string,string):void  $say
     * @param  array<string,mixed>  $row
     * @param  array<string,mixed>  $summary  mutated in place with scope counters.
     */
    private function assignScopes(
        int $userId,
        string $roleCode,
        TenantRef $tenant,
        SourceDescriptor $descriptor,
        array $row,
        callable $say,
        array &$summary,
    ): void {
        // ONLY estate-level/operational users get an estate scope (R7.4/R8.3).
        // Company/country/super tiers are company-wide (or broader) via
        // tc_user_access alone; adding an estate scope would wrongly narrow them.
        if (! RoleMap::isEstateLevel($roleCode)) {
            $say('debug', sprintf(
                '  scope: user_id=%d role=%s is not estate-level — no estate scope (company/country/super tier).',
                $userId, $roleCode,
            ));

            return;
        }

        // Resolve the estate to its target m_estate.id by (company_id,
        // estate_code) — the runtime + data-model + reconciler contract requires
        // scope_id to be the m_estate FK, not the raw code.
        $estateId = $this->estateIdFor($tenant->companyId, $descriptor->estateCode);
        if ($estateId === null) {
            $this->recordError(
                companyId: $tenant->companyId,
                key: sprintf('scope_estate_unresolved|%s|%s|%d', $descriptor->dbName, $descriptor->estateCode, $userId),
                message: sprintf(
                    'db=%s user_id=%d estate_code=%s company_id=%d: no m_estate row for (company_id, estate_code) — '
                    .'tc_user_scope estate row SKIPPED (run fusion:seed-tenants to seed m_estate first).',
                    $descriptor->dbName, $userId, $descriptor->estateCode, $tenant->companyId,
                ),
            );
            $say('warn', sprintf(
                '  SKIP scope (estate unresolved): user_id=%d estate_code=%s db=%s',
                $userId, $descriptor->estateCode, $descriptor->dbName,
            ));

            return;
        }

        // [task 5.10] PRIMARY idempotency guard (design LLD-9). If this exact
        // estate scope was applied on a previous run the scope_applied ledger
        // marker is present — treat it as a clean NO-OP: skip the DB upsert AND
        // the redundant ledger write, and count it as scopesSkipped. This makes
        // a second run observably insert 0 new scope rows and write 0 new ledger
        // rows for scopes. The (user_id, scope_type, scope_id) upsert below is
        // the belt-and-suspenders fallback used ONLY when the marker is absent.
        if ($this->scopeAlreadyApplied($userId, UserScope::TYPE_ESTATE, $estateId)) {
            $summary['scopesSkipped'] = ($summary['scopesSkipped'] ?? 0) + 1;
            $say('debug', sprintf(
                '  scope SKIP (already applied): user_id=%d estate=%s scope_id=%d — no-op (ledger marker present).',
                $userId, $descriptor->estateCode, $estateId,
            ));

            return;
        }

        $cols = $this->targetColumns('tc_user_scope');
        $now = now();
        [$createdAt, $updatedAt] = $this->remapAudit($row);

        // Only write columns the live tc_user_scope table actually has
        // (schema-drift safe). user_id/scope_type/scope_id form the natural key
        // below and are NOT part of the update payload.
        $values = array_intersect_key([
            'is_active'  => 1,
            'created_by' => $this->stringOrNull($row['user_created_by'] ?? null),
            'created_at' => $createdAt,
            'updated_by' => $this->stringOrNull($row['user_updated_by'] ?? null),
            'updated_at' => $updatedAt ?? $now,
        ], array_flip($cols));

        // Idempotent upsert keyed on the natural scope identity
        // (user_id, scope_type='estate', scope_id) — one estate scope per
        // (user, estate), never duplicated on re-run.
        $this->target->table('tc_user_scope')->updateOrInsert(
            [
                'user_id'    => $userId,
                'scope_type' => UserScope::TYPE_ESTATE,
                'scope_id'   => $estateId,
            ],
            $values,
        );

        // Record the applied-scope marker so task 5.10 can treat a re-run as a
        // clean no-op (ledger scope_applied shape — see migration).
        $this->recordScopeApplied($userId, UserScope::TYPE_ESTATE, $estateId, $tenant->companyId);

        $summary['scopesApplied'] = ($summary['scopesApplied'] ?? 0) + 1;

        $say('info', sprintf(
            '  scope: user_id=%d role=%s estate=%s (scope_type=estate scope_id=%d)',
            $userId, $roleCode, $descriptor->estateCode, $estateId,
        ));
    }

    /**
     * [IMPLEMENTED — task 5.8] Resolve a cross-estate dedup conflict.
     *
     * Called for an EXISTING identity — a DedupKey that already maps to a target
     * user, i.e. the SAME person in another of the company's estate DBs, OR a
     * re-run. The DedupKey collapse itself (one tc_user, one company-level
     * tc_user_access, N estate scopes) is already produced by migrate()'s flow:
     * we never re-insert the user here (so the first-seen tc_user row and its
     * password are implicitly preserved) and assignScopes() keeps adding this
     * estate's scope AFTER this method returns. This method adds the three
     * CONFLICT-RECONCILIATION behaviours on top (R8.4/R8.5):
     *
     *   1. FIRST-SEEN PASSWORD WINS + log mismatch (R8.4). Compares THIS source
     *      row's user_password against the already-migrated tc_user.password for
     *      $existingUserId. If they DIFFER the first-seen hash is KEPT (never
     *      overwritten) and a NOTE naming BOTH source DBs (first-seen + this one)
     *      and the DedupKey is recorded. Equal hashes record no note.
     *
     *   2. HIGHEST-AUTHORITY ROLE WINS on tc_user_access (R8.5). Compares the
     *      role implied by THIS row ($roleCode, level via RoleMap::level()) with
     *      the role currently on the user's single tc_user_access row. HIGHEST
     *      authority = LOWEST numeric level. If this row's role is higher
     *      authority (lower level) the access row's role_id is UPDATED and its
     *      tier SHAPE is reshaped to the new role via {@see scopeShapeFor()} —
     *      the SAME rule assignAccess() uses, so the two tasks agree. Each
     *      per-estate role seen is recorded to the notes ledger. The single
     *      access row invariant (exactly one per user) is preserved — only an
     *      UPDATE of the existing row ever happens here.
     *
     * NOTE on R8.6 (cross-company username/email collision suffixing): a
     * cross-company collision is two DIFFERENT people (different DedupKey) in
     * different companies who happen to share a user_login/email. That case does
     * NOT reach this method — a different DedupKey is treated as NEW by migrate()
     * — so the deterministic suffixing is handled at INSERT time in
     * {@see resolveInsertIdentity()} / the new-identity branch, not here.
     *
     * Idempotent: notes are upserted by stable (entry_type, key_text) so a
     * re-run does not pile up duplicates, and a role flip is deterministic
     * (re-running never flips back because the stored role is already the
     * highest authority once reconciled).
     *
     * @param  callable(string,string):void  $say
     * @param  array<string,mixed>  $row
     */
    private function resolveDedupConflict(
        int $existingUserId,
        string $dedupKey,
        string $roleCode,
        TenantRef $tenant,
        SourceDescriptor $descriptor,
        array $row,
        callable $say,
    ): void {
        // -----------------------------------------------------------------
        // 1. First-seen password WINS; log a mismatch naming both DBs (R8.4).
        // -----------------------------------------------------------------
        $incomingPassword = $this->stringOrNull($row['user_password'] ?? null);
        $storedPassword = $this->stringOrNull(
            $this->target->table('tc_user')->where('id', $existingUserId)->value('password')
        );

        // Only a genuine difference between two present hashes is a conflict. A
        // missing incoming/stored hash is not a "mismatch" to surface here.
        if ($incomingPassword !== null && $storedPassword !== null && ! hash_equals($storedPassword, $incomingPassword)) {
            $firstSeenDb = $this->firstSeenDbFor($dedupKey);
            $firstSeenLabel = $firstSeenDb ?? sprintf('(first-seen db unknown; existing user_id=%d)', $existingUserId);

            $this->recordNote(
                companyId: $tenant->companyId,
                // Stable key per (dedupKey) so a re-run upserts the same note.
                key: sprintf('pw_mismatch|%s', $dedupKey),
                message: sprintf(
                    'Password mismatch for DedupKey=%s (user_id=%d): first-seen db=%s password KEPT; '
                    .'db=%s had a DIFFERENT password hash (NOT overwritten — first-seen wins, R8.4).',
                    $dedupKey, $existingUserId, $firstSeenLabel, $descriptor->dbName,
                ),
            );
            $say('warn', sprintf(
                '  pw mismatch: user_id=%d dedup=%s (first-seen %s kept; %s differs — not overwritten)',
                $existingUserId, $dedupKey, $firstSeenLabel, $descriptor->dbName,
            ));
        }

        // -----------------------------------------------------------------
        // 2. Highest-authority (lowest level) role WINS on tc_user_access (R8.5).
        // -----------------------------------------------------------------
        $incomingLevel = RoleMap::level($roleCode);
        $incomingRoleId = $this->roleIdByCode($roleCode);

        // Record EVERY per-estate role seen for this identity (R8.5), keyed by
        // (dedupKey, roleCode) so re-runs upsert rather than pile up.
        $this->recordNote(
            companyId: $tenant->companyId,
            key: sprintf('role_seen|%s|%s', $dedupKey, $roleCode),
            message: sprintf(
                'DedupKey=%s (user_id=%d): role %s (level=%s) seen in db=%s estate=%s.',
                $dedupKey, $existingUserId, $roleCode,
                $incomingLevel ?? 'null', $descriptor->dbName, $descriptor->estateCode,
            ),
        );

        if ($incomingLevel === null || $incomingRoleId === null) {
            // The caller already mapped+guarded the role, so this is unexpected.
            // Record and leave the existing access row untouched rather than
            // reshape it from an unresolved role.
            $this->recordError(
                companyId: $tenant->companyId,
                key: sprintf('conflict_role_unresolved|%s|%s', $dedupKey, $roleCode),
                message: sprintf(
                    'DedupKey=%s (user_id=%d): incoming role_code=%s could not resolve level/role_id during '
                    .'conflict reconciliation — tc_user_access left unchanged.',
                    $dedupKey, $existingUserId, $roleCode,
                ),
            );

            return;
        }

        $access = $this->target->table('tc_user_access')
            ->where('user_id', $existingUserId)
            ->first(['role_id', 'country_id', 'company_id', 'estate_code']);

        if ($access === null) {
            // Invariant broken upstream (no access row for an existing user).
            // Record it; assignScopes() still runs after we return. Do NOT
            // insert here — assignAccess() owns creation on the new-identity path.
            $this->recordError(
                companyId: $tenant->companyId,
                key: sprintf('conflict_no_access_row|%s', $dedupKey),
                message: sprintf(
                    'DedupKey=%s (user_id=%d): no tc_user_access row found during conflict reconciliation '
                    .'(expected exactly one). Role flip skipped; run fusion:migrate-rbac fresh if unexpected.',
                    $dedupKey, $existingUserId,
                ),
            );

            return;
        }

        $storedRoleId = (int) $access->role_id;
        $storedLevel = $this->levelForRoleId($storedRoleId);

        // Higher authority = LOWER numeric level. Flip only when the incoming
        // row is strictly higher authority than what is stored. A null stored
        // level (unknown role on the row) is treated as lowest authority so the
        // incoming known role wins.
        $shouldFlip = $storedLevel === null || $incomingLevel < $storedLevel;

        if (! $shouldFlip) {
            $say('debug', sprintf(
                '  role keep: user_id=%d stored level=%s >= incoming %s (%s) — access unchanged.',
                $existingUserId, $storedLevel ?? 'null', $incomingLevel, $roleCode,
            ));

            return;
        }

        // Reshape the single access row to the higher-authority role, using the
        // SAME shape rule as assignAccess() (DRY — scopeShapeFor()). This keeps
        // exactly one access row per user (UPDATE, never INSERT).
        $shape = $this->scopeShapeFor($incomingLevel, $tenant, $descriptor);

        $cols = $this->targetColumns('tc_user_access');
        $update = array_intersect_key([
            'role_id'     => $incomingRoleId,
            'country_id'  => $shape['country_id'],
            'company_id'  => $shape['company_id'],
            'estate_code' => $shape['estate_code'],
            'updated_at'  => now(),
        ], array_flip($cols));

        $this->target->table('tc_user_access')
            ->where('user_id', $existingUserId)
            ->update($update);

        $this->recordNote(
            companyId: $tenant->companyId,
            key: sprintf('role_flip|%s', $dedupKey),
            message: sprintf(
                'DedupKey=%s (user_id=%d): tc_user_access role flipped to HIGHER authority %s '
                .'(level=%d, role_id=%d) from stored role_id=%d (level=%s); access row reshaped to tier=%s (R8.5).',
                $dedupKey, $existingUserId, $roleCode, $incomingLevel, $incomingRoleId,
                $storedRoleId, $storedLevel ?? 'null', $shape['tier_label'],
            ),
        );
        $say('info', sprintf(
            '  role flip: user_id=%d -> %s (level=%d, tier=%s) [higher authority than stored level %s]',
            $existingUserId, $roleCode, $incomingLevel, $shape['tier_label'], $storedLevel ?? 'null',
        ));
    }

    // =====================================================================
    // Idempotency helpers (task 5.3 does the minimum; task 5.10 extends).
    // =====================================================================

    /**
     * Look up an existing target user_id for a DedupKey via the user_dedup
     * ledger (R14.2). Returns null when the identity has not been migrated yet.
     *
     * This is the minimal re-run guard for task 5.3: an obvious re-run finds the
     * existing user and does NOT insert a duplicate. Task 5.10 builds on the
     * same ledger (plus scope_applied entries) to make the whole stage a clean
     * no-op on re-run.
     */
    public function findExistingUserId(string $dedupKey): ?int
    {
        $value = $this->target->table('fusion_ledger')
            ->where('entry_type', self::LEDGER_USER_DEDUP)
            ->where('key_text', $dedupKey)
            ->value('value_text');

        return $value === null ? null : (int) $value;
    }

    /**
     * Minimal collision guard (task 5.3): return the id of an EXISTING target
     * tc_user whose username (user_login) or email (user_email) already matches
     * this source row, or null when none exists. Used only to avoid an obvious
     * duplicate-insert crash on the global UNIQUE(username)/UNIQUE(email)
     * constraints when the user_dedup ledger has no record (e.g. a prior load
     * that bypassed the ledger). The caller then distinguishes ADOPT (same
     * person) from SUFFIX (a different-company person colliding on the global
     * UNIQUE — R8.6) via {@see dedupKeyForUserId()}; this method only locates
     * the clashing row, it does not decide the outcome.
     *
     * @param  array<string,mixed>  $row
     */
    private function findPreexistingTargetUser(array $row): ?int
    {
        $username = $this->stringOrNull($row['user_login'] ?? null);
        $email = $this->stringOrNull($row['user_email'] ?? null);

        $query = $this->target->table('tc_user');
        $matched = false;

        if ($username !== null) {
            $query->where('username', $username);
            $matched = true;
        }
        if ($email !== null) {
            $matched
                ? $query->orWhere('email', $email)
                : $query->where('email', $email);
            $matched = true;
        }

        if (! $matched) {
            return null;
        }

        $id = $query->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Reverse-lookup the DedupKey that maps to a target user_id in the
     * user_dedup ledger. Returns null when no user_dedup entry points at that
     * id (e.g. a pre-existing target row written by a prior load that bypassed
     * the ledger). Used by the new-identity path to decide ADOPT vs SUFFIX on a
     * global username/email collision (R8.6): a DIFFERENT DedupKey mapped to the
     * clashing row means a genuine cross-company collision.
     */
    private function dedupKeyForUserId(int $userId): ?string
    {
        return $this->stringOrNull(
            $this->target->table('fusion_ledger')
                ->where('entry_type', self::LEDGER_USER_DEDUP)
                ->where('value_text', (string) $userId)
                ->value('key_text')
        );
    }

    /**
     * [R8.6] Deterministically suffix THIS row's colliding username/email so a
     * genuine cross-company identity collision can be inserted without violating
     * the target's global UNIQUE(username)/UNIQUE(email), and log the collision.
     *
     * The FIRST-SEEN identity (the already-migrated $clashUserId) is left intact
     * — it is the higher-priority one. THIS later-seen, lower-priority row is the
     * one suffixed, using the incoming company_code so the result is STABLE
     * across re-runs (and distinct per company):
     *   - user_login  'jdoe'          -> 'jdoe@1PPP'
     *   - user_email  'jdoe@mail.com' -> 'jdoe+1PPP@mail.com' (before the @),
     *                  or (no '@')     -> 'rawvalue+1PPP'
     *
     * Only the username/email are rewritten; the password, name and codes carry
     * verbatim as usual. The returned row is handed straight to insertUser().
     *
     * Idempotency: a re-run never re-enters this method for the same identity —
     * once inserted, THIS row's DedupKey is in the user_dedup ledger, so
     * findExistingUserId() routes the re-run to resolveDedupConflict() instead.
     * The suffix itself is a pure function of (login/email, company_code), so
     * even a ledger-less replay would produce the identical suffixed values.
     *
     * @param  array<string,mixed>  $row
     * @param  callable(string,string):void  $say
     * @return array<string,mixed>  the row with suffixed user_login / user_email.
     */
    private function suffixCollidingIdentity(
        array $row,
        string $dedupKey,
        string $clashDedupKey,
        int $clashUserId,
        TenantRef $tenant,
        SourceDescriptor $descriptor,
        callable $say,
    ): array {
        $company = strtoupper(trim($descriptor->companyCode));

        $login = $this->stringOrNull($row['user_login'] ?? null);
        $email = $this->stringOrNull($row['user_email'] ?? null);

        $newLogin = $login === null ? null : $login.'@'.$company;
        $newEmail = $this->suffixEmail($email, $company);

        $row['user_login'] = $newLogin;
        $row['user_email'] = $newEmail;

        $this->recordNote(
            companyId: $tenant->companyId,
            // Stable key per colliding DedupKey so a re-run upserts the same note.
            key: sprintf('username_collision|%s', $dedupKey),
            message: sprintf(
                'Cross-company identity collision (R8.6): db=%s DedupKey=%s wanted login=%s/email=%s which already '
                .'belongs to a DIFFERENT person (user_id=%d, DedupKey=%s). First-seen kept; THIS identity '
                .'deterministically suffixed to login=%s/email=%s.',
                $descriptor->dbName, $dedupKey, $login ?? 'NULL', $email ?? 'NULL',
                $clashUserId, $clashDedupKey, $newLogin ?? 'NULL', $newEmail ?? 'NULL',
            ),
        );
        $say('warn', sprintf(
            '  username collision (R8.6): db=%s %s/%s taken by user_id=%d — suffixed to %s/%s',
            $descriptor->dbName, $login ?? 'NULL', $email ?? 'NULL',
            $clashUserId, $newLogin ?? 'NULL', $newEmail ?? 'NULL',
        ));

        return $row;
    }

    /**
     * Deterministically suffix an email's LOCAL part with "+companycode" so the
     * suffixed address stays a valid, routable form. Null/blank email -> null.
     * An email with no '@' is suffixed as "value+company" (best-effort, still
     * unique). Example: 'jdoe@mail.com' + '1PPP' -> 'jdoe+1PPP@mail.com'.
     */
    private function suffixEmail(?string $email, string $company): ?string
    {
        if ($email === null) {
            return null;
        }

        $at = strpos($email, '@');
        if ($at === false) {
            return $email.'+'.$company;
        }

        $local = substr($email, 0, $at);
        $domain = substr($email, $at); // includes the '@'

        return $local.'+'.$company.$domain;
    }

    /**
     * Record a non-fatal note entry (entry_type 'note') for conflicts /
     * divergences / adoptions (R8.4/R8.5). Upserted by (entry_type, key_text)
     * so a re-run does not pile up duplicate notes for the same cause.
     */
    private function recordNote(?int $companyId, string $key, string $message): void
    {
        $now = now();

        $this->target->table('fusion_ledger')->updateOrInsert(
            [
                'entry_type' => self::LEDGER_NOTE,
                'key_text'   => $key,
            ],
            [
                'company_id' => $companyId,
                'message'    => $message,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    /**
     * Record the DedupKey -> target user_id mapping in the fusion_ledger
     * (entry_type 'user_dedup', R14.2). Upserted by (entry_type, key_text) so a
     * re-run is idempotent at the ledger level.
     *
     * The FIRST-SEEN source db_name is stored in the ledger's `source_db`
     * column so {@see resolveDedupConflict()} can name BOTH databases in a
     * password-mismatch note (R8.4). Because this is only written on the
     * new-identity path (the existing-identity path never calls this), the
     * first write wins and a re-run upsert preserves the original first-seen DB.
     *
     * @param  string|null  $firstSeenDb  the source db_name the identity was
     *                                     first migrated from (new-identity path).
     */
    private function recordUserDedup(string $dedupKey, int $userId, int $companyId, ?string $firstSeenDb = null): void
    {
        $now = now();

        $this->target->table('fusion_ledger')->updateOrInsert(
            [
                'entry_type' => self::LEDGER_USER_DEDUP,
                'key_text'   => $dedupKey,
            ],
            [
                'value_text' => (string) $userId,
                'company_id' => $companyId,
                'source_db'  => $firstSeenDb,
                'message'    => null,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    /**
     * Read back the FIRST-SEEN source db_name for a DedupKey from the user_dedup
     * ledger (the `source_db` stored by {@see recordUserDedup()} on the
     * new-identity path). Returns null when the ledger has no source_db recorded
     * (e.g. an adopted pre-existing user, or a ledger written before 5.8).
     */
    private function firstSeenDbFor(string $dedupKey): ?string
    {
        return $this->stringOrNull(
            $this->target->table('fusion_ledger')
                ->where('entry_type', self::LEDGER_USER_DEDUP)
                ->where('key_text', $dedupKey)
                ->value('source_db')
        );
    }

    /**
     * Resolve the role LEVEL currently stored on a tc_user_access row from its
     * role_id — the inverse of {@see roleIdByCode()} + {@see RoleMap::level()}.
     * Used by {@see resolveDedupConflict()} to compare authority. Returns null
     * when the role_id has no role_code in m_roles or no level in RoleMap.
     */
    private function levelForRoleId(int $roleId): ?int
    {
        $roleCode = $this->stringOrNull(
            $this->target->table('m_roles')->where('id', $roleId)->value('role_code')
        );

        return $roleCode === null ? null : RoleMap::level($roleCode);
    }

    /**
     * Record an applied additive-scope marker in the fusion_ledger
     * (entry_type 'scope_applied') so a re-run does not re-insert the scope
     * (R14.2 / design LLD-9). Matches the ledger migration's documented shape:
     * key_text = "user_id|scope_type|scope_id", value_text = '1'. Upserted by
     * (entry_type, key_text) so a re-run is idempotent at the ledger level.
     */
    private function recordScopeApplied(int $userId, string $scopeType, int $scopeId, int $companyId): void
    {
        $now = now();

        $this->target->table('fusion_ledger')->updateOrInsert(
            [
                'entry_type' => self::LEDGER_SCOPE_APPLIED,
                'key_text'   => $this->scopeAppliedKey($userId, $scopeType, $scopeId),
            ],
            [
                'value_text' => '1',
                'company_id' => $companyId,
                'message'    => null,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    /**
     * [task 5.10] The PRIMARY idempotency guard for additive scopes
     * (design LLD-9: "(user_id, estate scope) -> applied ... re-running is a
     * no-op for existing scopes"). Returns true when a scope_applied marker
     * already exists for (user_id, scope_type, scope_id) — i.e. this exact
     * estate scope was written on a previous run and must be treated as a clean
     * no-op now (skip BOTH the DB upsert and the redundant ledger write).
     *
     * The guard reads by the SAME key {@see recordScopeApplied()} writes
     * ({@see scopeAppliedKey()}), so write-key and read-key match EXACTLY. The
     * key uses the target m_estate.id (the value {@see assignScopes()} stores in
     * tc_user_scope.scope_id — design "Data Models", task 5.6), NOT the raw
     * estate_code, so the ledger marker and the scope row agree on identity.
     */
    private function scopeAlreadyApplied(int $userId, string $scopeType, int $scopeId): bool
    {
        return $this->target->table('fusion_ledger')
            ->where('entry_type', self::LEDGER_SCOPE_APPLIED)
            ->where('key_text', $this->scopeAppliedKey($userId, $scopeType, $scopeId))
            ->exists();
    }

    /**
     * Build the canonical scope_applied ledger key "user_id|scope_type|scope_id"
     * (the shape documented in the fusion_ledger migration). ONE formatter used
     * by both the writer {@see recordScopeApplied()} and the reader
     * {@see scopeAlreadyApplied()} so the keys can never drift apart.
     */
    private function scopeAppliedKey(int $userId, string $scopeType, int $scopeId): string
    {
        return sprintf('%d|%s|%d', $userId, $scopeType, $scopeId);
    }

    // =====================================================================
    // Active-user detection (defensive).
    // =====================================================================

    /**
     * Defensive active-user detection (R5.1 / design sequence (b)).
     *
     * CI3 estate DBs store tc_user.user_status as a flag; the verified values in
     * the 12 sources are the strings '1' (active) and '0' (inactive). To be
     * robust against alternative encodings seen in CI3 installs, a user is
     * treated as ACTIVE when the trimmed, lower-cased status is one of
     * '1' / 'active' / 'true' / 'yes' / 'y' / 't', or a boolean true. It is
     * INACTIVE for '0' / '' / 'inactive' / 'false' / 'no' / 'n' / 'f' / null,
     * or boolean false. Any other non-empty value is treated as active (a
     * present, non-"off" status) — the conservative choice that never drops a
     * user merely because the flag used an unexpected token; the chosen rule is
     * logged by migrate() via the activeFlagSamples summary.
     */
    public function isActive(mixed $status): bool
    {
        if (is_bool($status)) {
            return $status;
        }

        if ($status === null) {
            return false;
        }

        $normalized = strtolower(trim((string) $status));

        $inactive = ['0', '', 'inactive', 'false', 'no', 'n', 'f'];
        if (in_array($normalized, $inactive, true)) {
            return false;
        }

        // '1', 'active', 'true', 'yes', 'y', 't' and any other non-empty token.
        return true;
    }

    /**
     * A stable key describing how an active flag value was interpreted, for the
     * summary's activeFlagSamples (so the run REPORTS the detection rule used).
     */
    private function activeFlagSampleKey(mixed $status): string
    {
        $shown = $status === null ? 'null' : (is_bool($status) ? ($status ? 'true' : 'false') : (string) $status);

        return sprintf('%s=>%s', $shown, $this->isActive($status) ? 'active' : 'inactive');
    }

    // =====================================================================
    // Internal helpers.
    // =====================================================================

    /**
     * Defensive guard: a migrated source user must NEVER be assigned one of the
     * SEEDED tiers (super_admin/country_admin/company_admin) — those are
     * provisioned by TenantRoleSeeder, not mapped from a source row (R9.4).
     * The LOCKED role_map already excludes them, so reaching here means a map
     * regression; fail loudly rather than silently create a privileged user.
     *
     * @param  array<string,mixed>  $row
     */
    private function assertNotSeededTier(string $roleCode, SourceDescriptor $descriptor, array $row): void
    {
        if (in_array($roleCode, self::SEEDED_TIERS, true)) {
            throw new \RuntimeException(sprintf(
                'R9.4 violation: db=%s user_id=%s maps to seeded tier "%s" — the LOCKED role_map must never yield '
                .'super_admin/country_admin/company_admin (those are seeded). Fix config(fusion.role_map).',
                $descriptor->dbName, $row['user_id'] ?? '?', $roleCode,
            ));
        }
    }

    /**
     * Remap the CI3 audit columns to target created_at / updated_at timestamps
     * (design "Audit column remap"). Combines user_created_date(+user_created_time)
     * into created_at; user_updated_date(+time) into updated_at with
     * updated_at = created_at fallback. Falls back to now() when the source has
     * no usable created date.
     *
     * @param  array<string,mixed>  $row
     * @return array{0:\Illuminate\Support\Carbon|\Carbon\CarbonInterface, 1:\Illuminate\Support\Carbon|\Carbon\CarbonInterface}
     */
    private function remapAudit(array $row): array
    {
        $now = now();

        $createdAt = $this->combineDateTime(
            $row['user_created_date'] ?? null,
            $row['user_created_time'] ?? null,
        ) ?? $now;

        $updatedAt = $this->combineDateTime(
            $row['user_updated_date'] ?? null,
            $row['user_updated_time'] ?? null,
        ) ?? $createdAt;

        return [$createdAt, $updatedAt];
    }

    /**
     * Combine a source date (and optional time) into a Carbon timestamp, or
     * null when the date is empty/unparseable. If only a date is present the
     * time is 00:00:00.
     */
    private function combineDateTime(mixed $date, mixed $time): ?\Illuminate\Support\Carbon
    {
        $dateStr = $this->stringOrNull($date);
        if ($dateStr === null) {
            return null;
        }

        $timeStr = $this->stringOrNull($time);
        $candidate = $timeStr === null ? $dateStr : trim($dateStr.' '.$timeStr);

        try {
            return \Illuminate\Support\Carbon::parse($candidate);
        } catch (Throwable) {
            try {
                return \Illuminate\Support\Carbon::parse($dateStr);
            } catch (Throwable) {
                return null;
            }
        }
    }

    /** Normalize a scalar source value to a trimmed string, or null when empty. */
    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || ! is_scalar($value)) {
            return null;
        }

        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    /**
     * Read-back m_roles.id by role_code (never hard-coded — R6.1/R7). Shared
     * helper the access extension point (5.5) and conflict handling (5.8) use.
     * Returns null when the role_code is not seeded.
     */
    public function roleIdByCode(string $roleCode): ?int
    {
        $id = $this->target->table('m_roles')
            ->whereRaw('UPPER(role_code) = ?', [strtoupper($roleCode)])
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Read-back m_estate.id for a (company_id, estate_code) pair — the UNIQUE
     * key on m_estate (design "Data Models": UNIQUE(company_id, estate_code)).
     * Used by {@see assignScopes()} to store the correct tc_user_scope.scope_id
     * FK (NEVER the raw estate_code string). Returns null when the estate has
     * not been seeded for that company (run fusion:seed-tenants first).
     */
    public function estateIdFor(int $companyId, string $estateCode): ?int
    {
        $id = $this->target->table('m_estate')
            ->where('company_id', $companyId)
            ->where('estate_code', $estateCode)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Record an error-ledger entry (entry_type 'error') for a skipped row /
     * unmapped value (R6.2 / R13.5). Upserted by (entry_type, key_text) so a
     * re-run does not pile up duplicate error rows for the same cause.
     */
    private function recordError(?int $companyId, string $key, string $message): void
    {
        $now = now();

        $this->target->table('fusion_ledger')->updateOrInsert(
            [
                'entry_type' => self::LEDGER_ERROR,
                'key_text'   => $key,
            ],
            [
                'company_id' => $companyId,
                'message'    => $message,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    /**
     * Discover the live column list of a target table so only existing columns
     * are written (schema-drift safe), mirroring TenantRoleSeeder.
     *
     * @return array<int, string>
     */
    private function targetColumns(string $table): array
    {
        return $this->target->getSchemaBuilder()->getColumnListing($table);
    }
}
