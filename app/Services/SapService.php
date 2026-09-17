<?php

namespace App\Services;

use App\Models\Global\CompanyConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SAP Integration Service â€” replicates CI3 sap_helper.php.
 *
 * Sends transaction payloads to SAP BTP/CPI via HTTP POST with Basic Auth,
 * parses the XML response, and updates integration_status on each record.
 *
 * SAP status codes (mirroring CI3):
 *   -1 = draft (editable)
 *    0 = locked / queued (closing_is_approved=1, not yet sent)
 *    2 = success (sent to SAP, REMARK empty)
 *    3 = lost connection
 *    4 = failed (sent to SAP, REMARK not empty)
 *    5 = adjustment (returned from SAP for correction)
 */
class SapService
{
    public const STATUS_DRAFT       = -1;
    public const STATUS_LOCKED      = 0;
    public const STATUS_SUCCESS     = 2;
    public const STATUS_LOST_CONN   = 3;
    public const STATUS_FAILED      = 4;
    public const STATUS_ADJUSTMENT  = 5;

    private string $apiUrl;
    private string $userId;
    private string $password;

    public function __construct(?CompanyConfig $config = null)
    {
        $this->apiUrl   = $config?->sap_api_url   ?? '';
        $this->userId   = $config?->sap_user_id   ?? '';
        $this->password = $config?->sap_password  ?? '';
    }

    public static function forCompany(int $companyId): self
    {
        $cfg = CompanyConfig::where('company_id', $companyId)->first();
        return new self($cfg);
    }

    /**
     * Send a batch of records to SAP and update integration_status.
     *
     * @param  array   $items         Rows already aliased to SAP field names (EMPNR, BUDAT, etc.)
     * @param  string  $urnHeader     e.g. "urn:ZEPMS_ATTENDANCE_IN"
     * @param  string  $imCode        e.g. "IM_ATTD"
     * @param  string  $table         Laravel table to update (e.g. 't_attendance')
     * @param  string  $pkColumn      PK column name in $table (e.g. 'id')
     * @param  string  $uniqueIdField SAP response field name that maps back to $pkColumn
     *                                (e.g. 'UNIQUE_ID'); strip estate prefix if needed
     * @param  bool    $stripEstatePrefix CI3 strips estate_code prefix for attd/overtime UNIQUE_ID
     * @return array{sent:int,success:int,failed:int,error:?string}
     */
    public function send(
        array  $items,
        string $urnHeader,
        string $imCode,
        string $table,
        string $pkColumn      = 'id',
        string $uniqueIdField = 'UNIQUE_ID',
        bool   $stripEstatePrefix = false
    ): array {
        if (empty($items)) {
            return ['sent' => 0, 'success' => 0, 'failed' => 0, 'error' => null];
        }
        if (empty($this->apiUrl)) {
            return ['sent' => 0, 'success' => 0, 'failed' => 0, 'error' => 'SAP API URL not configured.'];
        }

        $requestBody = [$urnHeader => [$imCode => ['item' => $items]]];
        $body        = json_encode($requestBody);

        try {
            $ch = curl_init($this->apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_USERPWD        => $this->userId . ':' . $this->password,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/xml'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'GET',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT        => 60,
            ]);
            $result   = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            Log::error('SAP send curl error: ' . $e->getMessage());
            // Mark all as lost connection
            DB::table($table)->whereIn($pkColumn, array_column($items, $uniqueIdField))
                ->update(['integration_status' => self::STATUS_LOST_CONN]);
            return ['sent' => count($items), 'success' => 0, 'failed' => 0, 'error' => $e->getMessage()];
        }

        if ($httpCode !== 200 || $curlErr) {
            Log::error("SAP HTTP {$httpCode}: " . $curlErr);
            DB::table($table)->whereIn($pkColumn, array_column($items, $uniqueIdField))
                ->update(['integration_status' => self::STATUS_LOST_CONN]);
            return ['sent' => count($items), 'success' => 0, 'failed' => 0, 'error' => "HTTP {$httpCode}: {$curlErr}"];
        }

        // Parse XML response
        try {
            $xml     = new \SimpleXMLElement($result);
            $exported = $xml->EX_EXPORT->item ?? [];
        } catch (\Throwable $e) {
            Log::error('SAP XML parse error: ' . $e->getMessage() . ' | body: ' . substr($result, 0, 500));
            return ['sent' => count($items), 'success' => 0, 'failed' => 0, 'error' => 'XML parse error: ' . $e->getMessage()];
        }

        $success = 0;
        $failed  = 0;

        foreach ($exported as $item) {
            $item    = (array) $item;
            $rawId   = (string) ($item[$uniqueIdField] ?? '');
            $remark  = (string) ($item['REMARK'] ?? '');

            if ($rawId === '') continue;

            // CI3 strips estate prefix for attendance/overtime UNIQUE_ID
            $dbId = $stripEstatePrefix ? substr($rawId, 2) : $rawId;

            if ($remark === '') {
                DB::table($table)->where($pkColumn, $dbId)->update([
                    'integration_status' => self::STATUS_SUCCESS,
                    'remark'             => null,
                ]);
                $success++;
            } else {
                DB::table($table)->where($pkColumn, $dbId)->update([
                    'integration_status' => self::STATUS_FAILED,
                    'remark'             => $remark,
                ]);
                $failed++;
            }
        }

        return ['sent' => count($items), 'success' => $success, 'failed' => $failed, 'error' => null];
    }

    /**
     * Lock records (set integration_status = 0 = queued/locked).
     * CI3 "lock" = mark as ready to send but not yet sent.
     */
    public function lock(string $table, string $pkColumn, array $ids, ?string $requestId = null): void
    {
        $update = ['integration_status' => self::STATUS_LOCKED];
        if ($requestId !== null) {
            $update['request_id'] = $requestId;
        }
        DB::table($table)->whereIn($pkColumn, $ids)->update($update);
    }

    /**
     * Relock (reopen) adjustment records (status 5 â†’ 0) for re-submission.
     */
    public function relock(string $table, string $pkColumn, array $ids): void
    {
        DB::table($table)->whereIn($pkColumn, $ids)->update([
            'integration_status' => self::STATUS_LOCKED,
            'adjustment_status'  => 2,
        ]);
    }

    /** Generate a request_id (same pattern as CI3). */
    public static function requestId(int $userId): string
    {
        return now()->format('YmdHis') . $userId;
    }

    /**
     * Log an adjustment action to t_adjustment (mirrors CI3 closing controllers).
     * Called after relock() for audit trail.
     */
    public function logAdjustment(
        array   $ids,
        string  $adjustmentType,
        string  $adjustedBy,
        ?int    $companyId = null,
        string  $note      = 'Adjustment / Reopen',
        ?string $transactionDate = null
    ): void {
        $rows = [];
        foreach ($ids as $id) {
            $rows[] = [
                'company_id'       => $companyId,
                'global_id'        => (string) $id,
                'note'             => $note,
                'date'             => now()->toDateString(),
                'time'             => now()->format('H:i:s'),
                'employee'         => null,
                'adjustment_type'  => $adjustmentType,
                'transaction_date' => $transactionDate ?? now()->toDateString(),
                'ajust_by'         => $adjustedBy,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        }
        if (! empty($rows)) {
            DB::table('t_adjustment')->insert($rows);
        }
    }

    // ─── Master Data Fetch (used by Grouping & Masters SAP sync) ───────────

    /**
     * Build a context array for the given company:
     * company_code, country_code, country_no, and the CompanyConfig record.
     * Mirrors the CI3 pattern used in master-data controllers.
     */
    public function context(int $companyId): array
    {
        $config = CompanyConfig::where('company_id', $companyId)->first();

        // company_code = estate_code (mirrors CI3 usage)
        $companyCode = $config?->estate_code ?? '';

        // country_code: first 2 chars of profile_code, fallback 'MY'
        $countryCode = $config?->profile_code
            ? strtoupper(substr($config->profile_code, 0, 2))
            : 'MY';

        return [
            'company_code' => $companyCode,
            'country_code' => $countryCode,
            'country_no'   => null,
            'config'       => $config,
        ];
    }

    /**
     * Fetch master data from SAP API.
     * Mirrors CI3 sap_helper->get_master_data().
     *
     * @param  string $urn      SAP URN / endpoint key  (e.g. 'ZEPMS_MEMBER_OUT')
     * @param  array  $filters  Key-value pairs for the SAP request payload
     * @param  array  $ctx      Context array returned by context()
     * @param  array  $columns  Expected column keys in the response items
     * @return array{status_code:int, data:array}
     */
    public function fetchMasterData(string $urn, array $filters, array $ctx, array $columns): array
    {
        $config = $ctx['config'] ?? null;

        if (empty($config?->sap_api_url)) {
            Log::warning("fetchMasterData: SAP API URL not configured for company.");
            return ['status_code' => 0, 'data' => []];
        }

        // Mirror CI3 payload format: {"urn:URN": {filters}}
        // If filters is empty, format is: {"urn:URN": ""}
        $payloadArray = empty($filters)
            ? ["urn:{$urn}" => ""]
            : ["urn:{$urn}" => $filters];

        $body = json_encode($payloadArray);

        try {
            $ch = curl_init($config->sap_api_url);
            curl_setopt_array($ch, [
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_USERPWD        => $config->sap_user_id . ':' . $config->sap_password,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/xml'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'GET',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT        => 60,
            ]);

            $result   = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            Log::error('fetchMasterData curl error: ' . $e->getMessage());
            return ['status_code' => 0, 'data' => []];
        }

        if ($curlErr || $httpCode !== 200) {
            Log::error("fetchMasterData HTTP {$httpCode}: {$curlErr} | body: " . substr((string) $result, 0, 300));
            return ['status_code' => $httpCode ?: 0, 'data' => []];
        }

        // CI3 returns XML (<SimpleXMLElement>). Parse XML first, fallback to JSON.
        $items = [];
        try {
            $xml = @simplexml_load_string($result);
            if ($xml !== false) {
                // Common SAP master data response has IT_EXPORT->item or EX_EXPORT->item
                $export = $xml->IT_EXPORT->item ?? $xml->EX_EXPORT->item ?? $xml->item ?? null;
                if ($export !== null) {
                    foreach ($export as $node) {
                        $items[] = (array) $node;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('fetchMasterData XML parse failed, trying JSON: ' . $e->getMessage());
        }

        // Fallback to JSON if XML didn't parse items
        if (empty($items)) {
            $json = json_decode($result, true);
            if (is_array($json)) {
                $raw = $json['item']
                    ?? $json['data']
                    ?? $json['IT_EXPORT']['item']
                    ?? $json['EX_EXPORT']['item']
                    ?? [];

                foreach ($raw as $i) {
                    $items[] = (array) $i;
                }
            }
        }

        return ['status_code' => $httpCode, 'data' => $items];
    }
}
