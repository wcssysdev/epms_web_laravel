<?php

namespace App\Http\Controllers\Admin\Masters\Hse;

use App\Http\Controllers\Admin\Masters\BaseGlobalLookupController;
use App\Models\Master\Hse\EmailTemplate;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * HSE master: Email Template — full CRUD (EPMS-authored, NO portal sync).
 *
 * The design (Component 5) specifies subclassing a global CRUD base. In THIS
 * codebase the global-lookup scaffold is
 * {@see BaseGlobalLookupController}, which
 * supplies index/add/save/edit/update/destroy/getDatatable/generateCsv (see
 * UomController for the minimal subclass shape).
 *
 * Table `m_hse_email_template` is global/non-tenant (no company_id) with an
 * integer auto-increment PK (`id`) and a UNIQUE `template_code`. It uses CUSTOM
 * audit columns (`created_date`/`updated_date`, model $timestamps=false) — it
 * has NO created_at/updated_at. Because the base save()/update() write
 * created_at/updated_at, this controller overrides save() and update() to:
 *   (a) enforce the UNIQUE template_code guard BEFORE any DB write (R8.4), and
 *   (b) write the real audit columns created_date/created_by (insert) and
 *       updated_date/updated_by (update).
 *
 * `template_body` holds CKEditor HTML — the create/edit Blade view renders a
 * CKEditor editor bound to the template_body textarea.
 *
 * @see EmailTemplate
 */
class EmailTemplateController extends BaseGlobalLookupController
{
    protected function tableName(): string
    {
        return 'm_hse_email_template';
    }

    protected function resourceName(): string
    {
        return 'Email Template';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function viewPrefix(): string
    {
        return 'admin.masters.hse.email_template';
    }

    protected function routePrefix(): string
    {
        return 'masters.hse.email_template';
    }

    protected function datatableColumns(): array
    {
        // R8.1 listing columns; R8.5 shows the active state (rendered as a badge
        // from template_is_active in the index view).
        return [
            'template_code' => 'Code',
            'template_name' => 'Name',
            'template_subject' => 'Subject',
            'template_is_active' => 'Active',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['template_code', 'template_name', 'template_subject', 'template_body'];
    }

    /**
     * R8.3 required fields + R8.4 unique template_code (validation layer).
     *
     * The unique rule rejects a duplicate code before persistence; save()/update()
     * also run an explicit pre-write guard (defence-in-depth) so the rejection is
     * guaranteed to happen before any INSERT/UPDATE touches the table. On update
     * the current row is ignored so a template can be saved without changing its
     * own code.
     */
    protected function formFields(): array
    {
        return $this->rules();
    }

    protected function rules($ignoreId = null): array
    {
        $unique = Rule::unique($this->tableName(), 'template_code');
        if ($ignoreId !== null) {
            $unique = $unique->ignore($ignoreId, $this->primaryKey());
        }

        return [
            'template_code' => ['required', 'string', 'max:50', $unique],
            'template_name' => ['required', 'string', 'max:100'],
            'template_subject' => ['required', 'string', 'max:255'],
            'template_body' => ['required', 'string'],
        ];
    }

    protected function mapRow(array $data): array
    {
        return [
            'template_code' => trim($data['template_code'] ?? ''),
            'template_name' => trim($data['template_name'] ?? ''),
            'template_subject' => trim($data['template_subject'] ?? ''),
            // CKEditor HTML stored as-is (admin-only, matches CI3 behaviour).
            'template_body' => $data['template_body'] ?? '',
            'template_is_active' => isset($data['template_is_active']) && (int) $data['template_is_active'] === 1 ? 1 : 0,
        ];
    }

    // ── SAVE (override: unique guard before write + custom audit columns) ──────
    public function save(Request $request): RedirectResponse
    {
        $request->validate($this->rules());

        $code = trim($request->input('template_code', ''));

        // R8.4 explicit pre-write guard: reject a duplicate code BEFORE any INSERT.
        if ($this->codeExists($code)) {
            return back()
                ->withInput()
                ->withErrors(['template_code' => 'Template code "'.$code.'" already exists.']);
        }

        $now = now();
        $user = $this->userName();

        $data = $this->mapRow($request->all());
        $data['created_date'] = $now;
        $data['created_by'] = $user;
        $data['updated_date'] = $now;
        $data['updated_by'] = $user;

        DB::table($this->tableName())->insert($data);

        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_CREATE, "Created {$this->resourceName()} [{$code}]");

        return redirect()->route($this->routePrefix().'.index')
            ->with('success', $this->resourceName().' added successfully.');
    }

    // ── UPDATE (override: unique guard before write + custom audit columns) ────
    public function update(Request $request, int $id): RedirectResponse
    {
        $request->validate($this->rules($id));

        $code = trim($request->input('template_code', ''));

        // R8.4 explicit pre-write guard: reject a duplicate code (ignoring self)
        // BEFORE any UPDATE.
        if ($this->codeExists($code, $id)) {
            return back()
                ->withInput()
                ->withErrors(['template_code' => 'Template code "'.$code.'" already exists.']);
        }

        $data = $this->mapRow($request->all());
        $data['updated_date'] = now();
        $data['updated_by'] = $this->userName();

        DB::table($this->tableName())->where($this->primaryKey(), $id)->update($data);

        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_UPDATE, "Updated {$this->resourceName()} #{$id}");

        return redirect()->route($this->routePrefix().'.index')
            ->with('success', $this->resourceName().' updated successfully.');
    }

    /**
     * Returns true when a row with the given template_code already exists,
     * optionally ignoring the row identified by $ignoreId (self, on update).
     */
    protected function codeExists(string $code, ?int $ignoreId = null): bool
    {
        $query = DB::table($this->tableName())->where('template_code', $code);
        if ($ignoreId !== null) {
            $query->where($this->primaryKey(), '!=', $ignoreId);
        }

        return $query->exists();
    }

    // ───────────────────────────────────────────────────────────────────────────
    // Task 8.3: preview($id) + placeholderSamples() — placeholder-token substitution.
    //
    // Route (registered by task 9.1):
    //   GET masters/hse/email_template/{id}/preview
    //       -> EmailTemplateController@preview
    //       name: masters.hse.email_template.preview
    // ───────────────────────────────────────────────────────────────────────────

    /**
     * Known placeholder tokens (brace-wrapped) => realistic sample value.
     *
     * The token set mirrors the placeholders used by the CI3 HSE email templates
     * (db_update_2026.txt `m_hse_email_template` INSERT / CR_HSE_2026.txt):
     *   {estate_code} {date} {time} {report_code} {employee_code} {employee_name}
     *   {division_code} {location} {description} {severity} {type}
     *
     * Keys are stored WITH the braces so a single str_replace over the subject/body
     * substitutes them directly (R9.1). Values are in-memory samples only — nothing
     * here touches the stored row (R9.2).
     *
     * @return array<string,string> token (with braces) => sample value
     */
    protected function placeholderSamples(): array
    {
        $now = now();

        return [
            '{estate_code}' => 'EST01',
            '{date}' => $now->format('Y-m-d'),
            '{time}' => $now->format('H:i'),
            '{report_code}' => 'ACC-2026-0001',
            '{employee_code}' => 'EMP00123',
            '{employee_name}' => 'John Doe',
            '{division_code}' => 'DIV01',
            '{location}' => 'Block A',
            '{description}' => 'Worker slipped on wet platform while loading FFB.',
            '{severity}' => 'Major',
            '{type}' => 'Slip / Trip / Fall',
        ];
    }

    /**
     * R9.1–R9.3 — render a preview of the email template with every known
     * placeholder token replaced by a sample value.
     *
     * Looks up the template by id. If it does not exist, R9.3 requires an error
     * indicating the template was not found — this mirrors the base edit()
     * convention of aborting with 404 for a missing row.
     *
     * On a match, every token from placeholderSamples() is substituted in BOTH
     * template_subject and template_body via str_replace. This is a pure read plus
     * in-memory string replace: the stored row is passed through untouched, so the
     * persisted template is never mutated (R9.2 — "Preview purity").
     */
    public function preview(int $id)
    {
        $row = DB::table($this->tableName())->where($this->primaryKey(), $id)->first();

        // R9.3 — template not found.
        abort_unless($row, 404, 'Email template not found.');

        $samples = $this->placeholderSamples();
        $tokens = array_keys($samples);
        $values = array_values($samples);

        // R9.1/R9.2 — substitute in-memory only; $row is left unchanged.
        $renderedSubject = str_replace($tokens, $values, (string) $row->template_subject);
        $renderedBody = str_replace($tokens, $values, (string) $row->template_body);

        return view($this->viewPrefix().'.preview', [
            'resourceName' => $this->resourceName(),
            'routePrefix' => $this->routePrefix(),
            'row' => $row,
            'renderedSubject' => $renderedSubject,
            'renderedBody' => $renderedBody,
            'samples' => $samples,
        ]);
    }
}
