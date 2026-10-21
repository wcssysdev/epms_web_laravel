<?php

namespace App\Http\Controllers\Admin\Masters\Hse;

use App\Http\Controllers\Admin\Masters\BaseGlobalLookupController;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * HSE master: Email Receiver — full CRUD (EPMS-authored, no portal sync).
 *
 * Extends BaseGlobalLookupController (the global lookup CRUD base that ships
 * index/getDatatable/add/save/edit/update/destroy/generateCsv). Email Receiver
 * differs from a plain lookup in three ways, so those behaviours are overridden
 * here:
 *   1. A duplicate guard on (receiver_email, receiver_notification_type) must run
 *      BEFORE any insert/update (Requirement 7.3).
 *   2. Audit columns are the HSE custom names created_date/updated_date with an
 *      integer user id in created_by/updated_by — NOT the base's created_at/
 *      updated_at + userName(). save()/update() are overridden so the base's
 *      created_at/updated_at are never written.
 *   3. Level / Department / Notification Type are Select2 dropdowns backed by
 *      preserved static option sets; add()/edit() pass those sets to the views.
 *
 * Table m_hse_email_receiver is global / non-tenant (no company_id).
 *
 * Routes are registered centrally in task 9.1 — NOT here. The routes that 9.1
 * must add for this controller (route-name prefix `masters.hse.email_receiver`):
 *   GET    email-receiver                 -> index      (name .index)
 *   GET    email-receiver/datatable       -> getDatatable (name .datatable)
 *   GET    email-receiver/add             -> add        (name .add)
 *   POST   email-receiver                 -> save       (name .save)
 *   GET    email-receiver/{id}/edit       -> edit       (name .edit)
 *   PUT    email-receiver/{id}            -> update     (name .update)
 *   DELETE email-receiver/{id}            -> destroy    (name .destroy)
 * (No generate-csv route — Email Receiver has no CSV template; see index view.)
 */
class EmailReceiverController extends BaseGlobalLookupController
{
    protected function tableName(): string
    {
        return 'm_hse_email_receiver';
    }

    protected function resourceName(): string
    {
        return 'Email Receiver';
    }

    protected function primaryKey(): string
    {
        return 'id';
    }

    protected function viewPrefix(): string
    {
        return 'admin.masters.hse.email_receiver';
    }

    protected function routePrefix(): string
    {
        return 'masters.hse.email_receiver';
    }

    protected function datatableColumns(): array
    {
        return [
            'receiver_name' => 'Name',
            'receiver_email' => 'Email',
            'receiver_level' => 'Level',
            'receiver_department' => 'Department',
            'receiver_notification_type' => 'Notification Type',
            'receiver_is_active' => 'Active',        // R7.5 — display active state
        ];
    }

    protected function csvHeaders(): array
    {
        // Email Receiver has no CSV template (not required by task 8.1). Kept
        // defined because the base declares it abstract; the index view omits
        // the CSV button and 9.1 registers no generate-csv route.
        return [
            'receiver_name',
            'receiver_email',
            'receiver_level',
            'receiver_department',
            'receiver_notification_type',
            'receiver_is_active',
        ];
    }

    protected function formFields(): array
    {
        // R7.2: name required; email required + valid; level + department required;
        // notification type required.
        return [
            'receiver_name' => 'required|string|max:100',
            'receiver_email' => 'required|email|max:150',
            'receiver_level' => 'required|string|max:50',
            'receiver_department' => 'required|string|max:100',
            'receiver_notification_type' => 'required|string|max:100',
        ];
    }

    /**
     * Map request → the m_hse_email_receiver business columns (no audit here;
     * audit is applied in the overridden save()/update()). receiver_is_active is
     * a checkbox: present → 1, absent → 0 (mirrors CI3).
     */
    protected function mapRow(array $data): array
    {
        return [
            'receiver_name' => trim($data['receiver_name'] ?? ''),
            'receiver_email' => trim($data['receiver_email'] ?? ''),
            'receiver_level' => trim($data['receiver_level'] ?? ''),
            'receiver_department' => trim($data['receiver_department'] ?? ''),
            'receiver_notification_type' => trim($data['receiver_notification_type'] ?? 'All'),
            'receiver_is_active' => ! empty($data['receiver_is_active']) ? 1 : 0,
        ];
    }

    // ── Static option sets (preserved from CI3) ──────────────────────────────
    // Source of truth: C:\xampp\htdocs\epms24\application\controllers\masters\
    // hse\Email_receiver.php (_get_departments/_get_levels/_get_notification_types).
    // These are preserved VERBATIM from the CI3 controller. When editing, the CI3
    // code appends the row's current value if it is not already in the set so a
    // legacy value is never silently dropped — replicated via the $current arg.

    /** @return array<string,string> department value => label */
    protected function departmentOptions(?string $current = null): array
    {
        $departments = [
            'Estate' => 'Estate',
            'Mill' => 'Mill',
            'Workshop' => 'Workshop',
            'Office' => 'Office',
            'HSE Department' => 'HSE Departemen',  // label verbatim from CI3
            'All' => 'All',
        ];
        if (! empty($current) && ! isset($departments[$current])) {
            $departments[$current] = $current;
        }

        return $departments;
    }

    /** @return array<int,string> level values */
    protected function levelOptions(?string $current = null): array
    {
        $levels = [
            'Estate Manager',
            'Division Head',
            'HSE Officer',
            'HSE Staff',
            'Safety Committee',
            'Manager',
            'Coordinator',
            'Officer',
        ];
        if (! empty($current) && ! in_array($current, $levels, true)) {
            $levels[] = $current;
        }

        return $levels;
    }

    /** @return array<int,string> notification type values */
    protected function notificationTypeOptions(?string $current = null): array
    {
        $types = [
            'All',
            'Incident',
            'Accident',
            'Near Miss',
            'Disease',
            'HIRARC',
            'AFR',
        ];
        if (! empty($current) && ! in_array($current, $types, true)) {
            $types[] = $current;
        }

        return $types;
    }

    // ── ADD form (override to inject option sets) ────────────────────────────
    public function add()
    {
        return view($this->viewPrefix().'.form', [
            'resourceName' => $this->resourceName(),
            'routePrefix' => $this->routePrefix(),
            'item' => null,
            'departments' => $this->departmentOptions(),
            'levels' => $this->levelOptions(),
            'notificationTypes' => $this->notificationTypeOptions(),
        ]);
    }

    // ── EDIT form (override to inject option sets, incl. current value) ──────
    public function edit(int $id)
    {
        $item = DB::table($this->tableName())->where($this->primaryKey(), $id)->first();
        abort_unless($item, 404);

        return view($this->viewPrefix().'.form', [
            'resourceName' => $this->resourceName(),
            'routePrefix' => $this->routePrefix(),
            'item' => $item,
            'departments' => $this->departmentOptions($item->receiver_department),
            'levels' => $this->levelOptions($item->receiver_level),
            'notificationTypes' => $this->notificationTypeOptions($item->receiver_notification_type),
        ]);
    }

    // ── SAVE (override: duplicate guard + HSE audit columns) ─────────────────
    public function save(Request $request): RedirectResponse
    {
        $request->validate($this->formFields());

        $data = $this->mapRow($request->all());

        // R7.3: reject duplicate (receiver_email, receiver_notification_type)
        // BEFORE the insert. No write occurs on a duplicate.
        if ($this->duplicateExists($data['receiver_email'], $data['receiver_notification_type'])) {
            return $this->duplicateRedirect($request);
        }

        $data['created_by'] = $this->userId();
        $data['created_date'] = now();

        DB::table($this->tableName())->insert($data);

        AuditService::log(
            AuditService::TYPE_MASTER,
            AuditService::ACTION_CREATE,
            "Created {$this->resourceName()}"
        );

        return redirect()->route($this->routePrefix().'.index')
            ->with('success', $this->resourceName().' added successfully.');
    }

    // ── UPDATE (override: duplicate guard excluding self + HSE audit) ────────
    public function update(Request $request, int $id): RedirectResponse
    {
        $request->validate($this->formFields());

        $data = $this->mapRow($request->all());

        // R7.3: duplicate check excluding the current row.
        if ($this->duplicateExists($data['receiver_email'], $data['receiver_notification_type'], $id)) {
            return $this->duplicateRedirect($request, $id);
        }

        $data['updated_by'] = $this->userId();
        $data['updated_date'] = now();

        DB::table($this->tableName())->where($this->primaryKey(), $id)->update($data);

        AuditService::log(
            AuditService::TYPE_MASTER,
            AuditService::ACTION_UPDATE,
            "Updated {$this->resourceName()} #{$id}"
        );

        return redirect()->route($this->routePrefix().'.index')
            ->with('success', $this->resourceName().' updated successfully.');
    }

    // ── Duplicate helpers ────────────────────────────────────────────────────

    /**
     * True when another row already has the same (receiver_email,
     * receiver_notification_type). $excludeId skips the current row on update.
     */
    protected function duplicateExists(string $email, string $notificationType, ?int $excludeId = null): bool
    {
        $query = DB::table($this->tableName())
            ->where('receiver_email', $email)
            ->where('receiver_notification_type', $notificationType);

        if ($excludeId !== null) {
            $query->where($this->primaryKey(), '!=', $excludeId);
        }

        return $query->exists();
    }

    /** Redirect back to the form with the duplicate validation error + old input. */
    protected function duplicateRedirect(Request $request, ?int $id = null): RedirectResponse
    {
        $target = $id !== null
            ? redirect()->route($this->routePrefix().'.edit', $id)
            : redirect()->route($this->routePrefix().'.add');

        return $target
            ->withInput()
            ->withErrors([
                'receiver_email' => 'Email address with this notification type already exists.',
            ]);
    }
}
