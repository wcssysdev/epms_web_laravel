<?php

namespace App\Http\Controllers\Admin\Masters\Hse;

use App\Models\Master\Hse\Course;
use App\Services\HsePortalService;
use Carbon\Carbon;

/**
 * HSE master: Course (portal-synced, read-only).
 *
 * Global / non-tenant master fed by the HSE Portal three-step flow
 * (Get All Data From HSE → Refresh Master Data Info → Replace Master Data),
 * inheriting all of that scaffolding from {@see BaseHseMasterController}. This
 * concrete controller only supplies the per-master contract: model/table identity,
 * portal endpoint + staging mirror, the portal→staging and staging→master column
 * projections, and the per-master audit-column override.
 *
 * Natural-key master: the primary key `course_no_epms` is a varchar business key,
 * so the row carries no DB-generated identity default — {@see usesManualIdentityKey()}
 * keeps its base default (false) and is NOT overridden. {@see masterKeyColumn()} is
 * the natural key `course_no_epms`.
 *
 * ── ENDPOINT CORRECTION ──────────────────────────────────────────────────────
 * There is NO `MasterData/courses` endpoint. Training courses live under a separate
 * path root: `training-courses/all`. {@see hseEndpoint()} therefore returns a
 * NON-`MasterData/` path, which {@see HsePortalService} already
 * supports (endpoints need not be rooted at `MasterData/`).
 *
 * ── UNVERIFIED portal field names (TODO — task 11 integration testing) ───────
 * Unlike the `MasterData/*` endpoints, the `training-courses/all` field names were
 * NOT captured by the design probe, so they are UNVERIFIED. {@see mapPortalItem()}
 * below maps best-guess camelCase portal keys to the staging columns we have and is
 * deliberately tolerant of missing keys (every read null-coalesces). The exact
 * `training-courses/all` field names MUST be confirmed against the live endpoint
 * during integration testing (task 11) and this mapping corrected if they differ.
 *
 * ── Column reconciliation ────────────────────────────────────────────────────
 *   - {@see mapPortalItem()} projects portal → staging mirror `m_master_course`
 *     (course_code, course_name, course_category, duration, duration_unit,
 *     validity_from, validity_to, course_type, trainer_name).
 *   - {@see stagingToMasterMap()} projects staging → master `m_course`:
 *       course_code    → course_no_epms   (the portal's EPMS course code = the PK)
 *       course_name    → course_name
 *       course_category→ course_category
 *       duration       → course_duration
 *       validity_from  → course_validity_from
 *       validity_to    → course_validity_to
 *       course_type    → course_type
 *     The master's `course_no` has no distinct staging source (the mirror carries a
 *     single course_code, mapped to the PK course_no_epms) and remains NULL, as do
 *     the sync columns course_sync_status / course_hse_portal_id (left to their DDL
 *     default / null — not populated during Replace). The staging columns
 *     `duration_unit` and `trainer_name` have no master column in m_course and are
 *     therefore DROPPED on Replace.
 *
 * Audit columns: the master uses course_*-prefixed audit columns rather than the base
 * default created_by/created_date/..., so {@see addAuditFields()} is overridden.
 *
 * _Requirements: 1.1, 1.2, 2.2, 4.1_
 */
class CourseController extends BaseHseMasterController
{
    protected function modelClass(): string
    {
        return Course::class;
    }

    protected function tableName(): string
    {
        return 'm_course';
    }

    protected function stagingTable(): string
    {
        return 'm_master_course';
    }

    protected function resourceName(): string
    {
        return 'Course';
    }

    protected function viewPrefix(): string
    {
        return 'admin.masters.hse.course';
    }

    protected function routePrefix(): string
    {
        return 'masters.hse.course';
    }

    protected function hseEndpoint(): string
    {
        return 'training-courses/all';
    }

    /** Natural/business key (varchar) used for dedupe + ordering. */
    protected function masterKeyColumn(): string
    {
        return 'course_no_epms';
    }

    protected function datatableColumns(): array
    {
        return [
            'course_no_epms' => 'Course No (EPMS)',
            'course_name' => 'Name',
            'course_category' => 'Category',
            'course_duration' => 'Duration',
            'course_type' => 'Type',
            'course_validity_from' => 'Valid From',
            'course_validity_to' => 'Valid To',
        ];
    }

    protected function csvHeaders(): array
    {
        return [
            'course_no_epms',
            'course_name',
            'course_category',
            'course_duration',
            'course_type',
            'course_validity_from',
            'course_validity_to',
        ];
    }

    /**
     * Map one portal JSON item to a staging row.
     *
     * TODO (task 11 — integration testing): the `training-courses/all` field names
     * are UNVERIFIED. The camelCase keys below are best guesses derived from the
     * staging columns; confirm them against the live endpoint and correct if they
     * differ. Every read null-coalesces so an unexpected/missing portal key stages
     * as NULL rather than throwing.
     */
    protected function mapPortalItem(array $item): array
    {
        return [
            // Best-guess portal key → staging column. CONFIRM in task 11.
            'course_code' => $item['courseNoEpms'] ?? $item['courseNo'] ?? null,
            'course_name' => $item['courseName'] ?? null,
            'course_category' => $item['courseCategory'] ?? null,
            'duration' => $item['duration'] ?? null,
            'duration_unit' => $item['durationUnit'] ?? null,
            'validity_from' => $item['validityFrom'] ?? null,
            'validity_to' => $item['validityTo'] ?? null,
            'course_type' => $item['courseType'] ?? null,
            'trainer_name' => $item['trainerName'] ?? null,
        ];
    }

    /**
     * Staging → master column projection ([staging_col => master_col]).
     *
     * course_code→course_no_epms maps the portal's EPMS course code onto the PK.
     * The master's course_no and the sync columns (course_sync_status,
     * course_hse_portal_id) have no staging source and remain NULL / default. The
     * staging columns duration_unit / trainer_name have no m_course column and are
     * NOT listed here (dropped on Replace).
     */
    protected function stagingToMasterMap(): array
    {
        return [
            'course_code' => 'course_no_epms',
            'course_name' => 'course_name',
            'course_category' => 'course_category',
            'duration' => 'course_duration',
            'validity_from' => 'course_validity_from',
            'validity_to' => 'course_validity_to',
            'course_type' => 'course_type',
        ];
    }

    /**
     * Inject the master's course_*-prefixed audit columns (not the base
     * created_by/created_date/updated_by/updated_date) before insert during Replace.
     */
    protected function addAuditFields(array $row): array
    {
        $now = now();
        $user = $this->userName();

        $row['course_created_timestamp'] = $row['course_created_timestamp'] ?? $now;
        $row['course_created_by'] = $row['course_created_by'] ?? $user;
        $row['course_updated_timestamp'] = $now;
        $row['course_updated_by'] = $user;

        return $row;
    }

    protected function mapCsvRow(array $row, int $rowNum): ?array
    {
        $code = trim($row['course_no_epms'] ?? '');
        if ($code === '') {
            return null;
        }

        return [
            'course_no_epms' => $code,
            'course_name' => trim($row['course_name'] ?? ''),
            'course_category' => trim($row['course_category'] ?? ''),
            'course_duration' => trim($row['course_duration'] ?? '') === '' ? null : (float) $row['course_duration'],
            'course_type' => trim($row['course_type'] ?? ''),
            'course_validity_from' => $this->parseDate($row['course_validity_from'] ?? ''),
            'course_validity_to' => $this->parseDate($row['course_validity_to'] ?? ''),
        ];
    }

    protected function validateRow(array $row): ?string
    {
        return empty($row['course_no_epms']) ? 'Course No (EPMS) is required.' : null;
    }

    private function parseDate(string $val): ?string
    {
        $val = trim($val);
        if ($val === '') {
            return null;
        }
        $val = str_replace('.', '-', $val);
        try {
            return Carbon::parse($val)->toDateString();
        } catch (\Exception $e) {
            return null;
        }
    }
}
