<?php

namespace App\Http\Controllers\Admin\Masters\Hse;

use App\Http\Controllers\Admin\Masters\BaseGlobalLookupController;
use App\Models\Master\Hse\IncidentTime;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * HSE master: Incident Time — plain CRUD + CSV (NO portal sync).
 *
 * The live HSE Portal exposes no incident-time endpoint (verified against the
 * portal Swagger), so this master is managed entirely through CRUD + CSV; there
 * is no Get / Refresh Info / Replace three-step flow here.
 *
 * Table `m_incident_time` is global/non-tenant (no company_id) with a VARCHAR
 * natural PK (`incident_time_name`) and CUSTOM audit columns — it has NO
 * created_at/updated_at. The default BaseGlobalLookupController save/update
 * write created_at/updated_at and treat the key as an int, so this controller
 * overrides save(), update(), edit(), and destroy() to (a) key by the string
 * PK and (b) write only the real audit columns:
 *   incident_time_created_timestamp / incident_time_created_by
 *   incident_time_updated_timestamp / incident_time_updated_by
 *
 * @see IncidentTime
 */
class IncidentTimeController extends BaseGlobalLookupController
{
    protected function tableName(): string
    {
        return 'm_incident_time';
    }

    protected function resourceName(): string
    {
        return 'Incident Time';
    }

    protected function primaryKey(): string
    {
        return 'incident_time_name';
    }

    protected function viewPrefix(): string
    {
        return 'admin.masters.hse.incident_time';
    }

    protected function routePrefix(): string
    {
        return 'masters.hse.incident_time';
    }

    protected function datatableColumns(): array
    {
        return [
            'incident_time_name' => 'Incident Time',
            'incident_time_updated_timestamp' => 'Last Updated',
        ];
    }

    protected function csvHeaders(): array
    {
        return ['incident_time_name'];
    }

    protected function formFields(): array
    {
        // R10.2: a non-empty incident_time_name is required.
        return ['incident_time_name' => 'required|string|max:100'];
    }

    protected function mapRow(array $data): array
    {
        return ['incident_time_name' => trim($data['incident_time_name'] ?? '')];
    }

    // ── SAVE (override: custom audit columns, no created_at/updated_at) ─────────
    public function save(Request $request): RedirectResponse
    {
        $request->validate($this->formFields());
        $now = now();
        $user = $this->userName();

        $data = $this->mapRow($request->all());
        $data['incident_time_created_timestamp'] = $now;
        $data['incident_time_created_by'] = $user;
        $data['incident_time_updated_timestamp'] = $now;
        $data['incident_time_updated_by'] = $user;

        DB::table($this->tableName())->insert($data);

        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_CREATE, "Created {$this->resourceName()}");

        return redirect()->route($this->routePrefix().'.index')
            ->with('success', $this->resourceName().' added successfully.');
    }

    // ── EDIT (override: string PK) ─────────────────────────────────────────────
    public function edit($id)
    {
        $item = DB::table($this->tableName())->where($this->primaryKey(), $id)->first();
        abort_unless($item, 404);

        return view($this->viewPrefix().'.form', [
            'resourceName' => $this->resourceName(),
            'routePrefix' => $this->routePrefix(),
            'item' => $item,
        ]);
    }

    // ── UPDATE (override: string PK + custom audit columns) ────────────────────
    public function update(Request $request, $id): RedirectResponse
    {
        $request->validate($this->formFields());

        $data = $this->mapRow($request->all());
        $data['incident_time_updated_timestamp'] = now();
        $data['incident_time_updated_by'] = $this->userName();

        DB::table($this->tableName())->where($this->primaryKey(), $id)->update($data);

        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_UPDATE, "Updated {$this->resourceName()} #{$id}");

        return redirect()->route($this->routePrefix().'.index')
            ->with('success', $this->resourceName().' updated successfully.');
    }

    // ── DELETE (override: string PK) ───────────────────────────────────────────
    public function destroy($id): RedirectResponse
    {
        DB::table($this->tableName())->where($this->primaryKey(), $id)->delete();

        AuditService::log(AuditService::TYPE_MASTER, AuditService::ACTION_DELETE, "Deleted {$this->resourceName()} #{$id}");

        return redirect()->route($this->routePrefix().'.index')
            ->with('success', $this->resourceName().' deleted.');
    }

    // ════════════════════════════════════════════════════════════════════════
    // CSV IMPORT (R10.3) — upload → preview → save, reusing the shared Blade views
    //
    // BaseGlobalLookupController ships only the CSV TEMPLATE download (generateCsv),
    // with no upload handler. R10.3 requires Incident Time to "support CSV upload"
    // consistent with the other masters, so a focused upload pipeline is implemented
    // here mirroring BaseMasterController's upload/preview/save/cancel contract and
    // the shared `admin.masters._shared.upload` / `.preview` Blade views (which key
    // off $routePrefix + $resourceName and the .preview/.save-uploaded-data/.cancel
    // route names).
    // ════════════════════════════════════════════════════════════════════════

    /** Session bucket for the validated preview rows (same naming as BaseMasterController). */
    private function previewSessionKey(): string
    {
        return 'master_preview_'.$this->tableName();
    }

    // ── UPLOAD form ────────────────────────────────────────────────────────────
    public function upload()
    {
        return view('admin.masters._shared.upload', [
            'resourceName' => $this->resourceName(),
            'routePrefix' => $this->routePrefix(),
        ]);
    }

    // ── PREVIEW uploaded CSV ─────────────────────────────────────────────────────
    public function preview(Request $request)
    {
        // R5.3 / R10.3: reject an invalid MIME type or extension before parsing.
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('csv_file');
        $rows = array_map('str_getcsv', file($file->getRealPath()));

        if (empty($rows)) {
            return back()->with('error', 'CSV file is empty.');
        }

        $headers = array_map('trim', array_shift($rows));
        $preview = [];
        $errors = [];
        $valid = [];

        foreach ($rows as $i => $row) {
            // Skip a trailing blank line.
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }

            $assoc = array_combine($headers, array_pad($row, count($headers), ''));
            $mapped = ['incident_time_name' => trim((string) ($assoc['incident_time_name'] ?? ''))];

            // R5.4 / R10.2: a row with an empty incident_time_name fails validation.
            $error = $mapped['incident_time_name'] === '' ? 'Incident Time is required.' : null;

            if ($error) {
                $errors[] = 'Row '.($i + 2).": {$error}";
            } else {
                $valid[] = $mapped;
            }

            if (count($preview) < 100) {
                $preview[] = ['data' => $mapped, 'error' => $error];
            }
        }

        Session::put($this->previewSessionKey(), $valid);

        return view('admin.masters._shared.preview', [
            'resourceName' => $this->resourceName(),
            'routePrefix' => $this->routePrefix(),
            'preview' => $preview,
            'validCount' => count($valid),
            'errorCount' => count($errors),
            'errors' => array_slice($errors, 0, 20),
            'headers' => array_keys($this->datatableColumns()),
        ]);
    }

    // ── SAVE uploaded data (dedupe + non-empty key, custom audit columns) ────────
    public function saveUploadedData(): RedirectResponse
    {
        $rows = Session::get($this->previewSessionKey(), []);

        if (empty($rows)) {
            return redirect()->route($this->routePrefix().'.index')
                ->with('error', 'No preview data found. Please upload again.');
        }

        // R5.5: natural key must stay globally unique — skip keys already in the master.
        $existingKeys = DB::table($this->tableName())
            ->pluck($this->primaryKey())
            ->map(fn ($v) => (string) $v)
            ->flip();

        $now = now();
        $user = $this->userName();

        $seen = [];
        $toInsert = [];
        $skipped = 0;

        foreach ($rows as $row) {
            $key = trim((string) ($row['incident_time_name'] ?? ''));

            // R5.5: require a non-empty natural key, then dedupe within the batch and
            // against the existing master rows.
            if ($key === '' || isset($seen[$key]) || $existingKeys->has($key)) {
                $skipped++;

                continue;
            }

            $seen[$key] = true;
            $toInsert[] = [
                'incident_time_name' => $key,
                'incident_time_created_timestamp' => $now,
                'incident_time_created_by' => $user,
                'incident_time_updated_timestamp' => $now,
                'incident_time_updated_by' => $user,
            ];
        }

        if (empty($toInsert)) {
            return redirect()->route($this->routePrefix().'.index')
                ->with('error', 'No new incident time rows to import (all rows were empty or duplicates).');
        }

        DB::transaction(function () use ($toInsert) {
            foreach (array_chunk($toInsert, 500) as $chunk) {
                DB::table($this->tableName())->insert($chunk);
            }
        });

        Session::forget($this->previewSessionKey());

        $inserted = count($toInsert);

        AuditService::log(
            AuditService::TYPE_MASTER,
            AuditService::ACTION_CREATE,
            'Uploaded '.$inserted.' rows to '.$this->resourceName().' via CSV'
            .($skipped > 0 ? " ({$skipped} skipped — empty or duplicate)" : '')
        );

        $msg = $inserted.' '.$this->resourceName().' records saved successfully.'
            .($skipped > 0 ? " {$skipped} row(s) skipped (empty or duplicate)." : '');

        return redirect()->route($this->routePrefix().'.index')->with('success', $msg);
    }

    // ── CANCEL preview ───────────────────────────────────────────────────────────
    public function cancelUpload(): RedirectResponse
    {
        Session::forget($this->previewSessionKey());

        return redirect()->route($this->routePrefix().'.index');
    }
}
