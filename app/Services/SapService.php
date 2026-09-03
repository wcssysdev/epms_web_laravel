<?php

namespace App\Services;

use App\Models\Global\Company;
use App\Models\Global\CompanyConfig;
use Illuminate\Support\Facades\Log;

/**
 * SAP master-data integration.
 *
 * Mirrors the CI4 `get_master_data_from_sap()` helper: an HTTP GET carrying a
 * JSON/XML body, HTTP Basic auth, SSL verification off, expecting an XML
 * response with an IT_EXPORT->item list.
 *
 * Adds a DEV SIMULATION mode: when the company's Estate Settings has no
 * sap_api_url configured, the service generates deterministic dummy rows so the
 * two-step Get → Refresh flow can be exercised end-to-end without a live SAP.
 */
class SapService
{
    /**
     * Resolve country/company context from Estate Settings for the given company.
     *
     * EPMS uses company_code as its tenant key. country_code / country_no come
     * from the country the company belongs to (Estate Settings declares the
     * country the EPMS instance serves). Defaults: MY / 1.
     *
     * @return array{company_code:string,country_code:string,country_no:string,config:?CompanyConfig}
     */
    public function context(int $companyId): array
    {
        $company = Company::with('country', 'config')->find($companyId);

        return [
            'company_code' => $company?->company_code ?? '',
            'country_code' => strtoupper($company?->country?->code ?? 'MY'),
            'country_no'   => (string) ($company?->country?->prefix ?? '1'),
            'config'       => $company?->config,
        ];
    }

    /**
     * Fetch master rows from SAP for a given staging URN.
     *
     * @param  string  $urn      e.g. 'ZEPMS_EM_ESTATE_OUT'
     * @param  array   $filters  SAP request filters, e.g. ['BUKRS' => '*', 'LAND1' => 'MY']
     * @param  array   $context  from context(): company_code / country_code / country_no / config
     * @param  array   $sampleColumns  SAP field names used to shape simulated rows
     * @return array{status_code:int,data:array,simulated:bool}
     */
    public function fetchMasterData(string $urn, array $filters, array $context, array $sampleColumns): array
    {
        $config = $context['config'] ?? null;
        $apiUrl = $config?->sap_api_url;

        // ── DEV simulation: no SAP endpoint configured ──────────────────────
        if (empty($apiUrl)) {
            return [
                'status_code' => 200,
                'data'        => $this->simulateRows($context, $sampleColumns),
                'simulated'   => true,
            ];
        }

        // ── Real SAP call ───────────────────────────────────────────────────
        try {
            $body = json_encode(['urn:' . $urn => $filters]);

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_USERPWD        => $config->sap_user_id . ':' . $config->sap_password,
                CURLOPT_HTTPHEADER     => ['Content-Type:application/xml'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_CUSTOMREQUEST  => 'GET',
                CURLOPT_TIMEOUT        => 120,
            ]);
            $result   = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200 || $result === false) {
                Log::warning('SAP fetch failed', ['urn' => $urn, 'http' => $httpCode, 'err' => $curlErr]);
                return ['status_code' => $httpCode ?: 500, 'data' => [], 'simulated' => false];
            }

            return [
                'status_code' => 200,
                'data'        => $this->parseXml($result),
                'simulated'   => false,
            ];
        } catch (\Throwable $e) {
            Log::error('SAP fetch exception', ['urn' => $urn, 'msg' => $e->getMessage()]);
            return ['status_code' => 500, 'data' => [], 'simulated' => false];
        }
    }

    /** Parse SAP XML into a list of associative rows (IT_EXPORT->item). */
    private function parseXml(string $xml): array
    {
        try {
            $doc = new \SimpleXMLElement($xml);
        } catch (\Throwable $e) {
            Log::warning('SAP XML parse failed', ['msg' => $e->getMessage()]);
            return [];
        }

        $rows = [];
        if (isset($doc->IT_EXPORT->item)) {
            foreach ($doc->IT_EXPORT->item as $item) {
                $rows[] = array_map(fn ($v) => trim((string) $v), (array) $item);
            }
        }
        return $rows;
    }

    /**
     * Generate deterministic dummy SAP rows for dev. Each row includes exactly
     * the requested SAP field columns, populated with recognisable sample data.
     */
    private function simulateRows(array $context, array $sampleColumns): array
    {
        $company = $context['company_code'] ?: '1TEST';
        $rows    = [];

        for ($i = 1; $i <= 3; $i++) {
            $row = [];
            foreach ($sampleColumns as $col) {
                $row[$col] = $this->sampleValue($col, $company, $i);
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /** Produce a plausible value for a given SAP field. */
    private function sampleValue(string $col, string $company, int $i): string
    {
        return match ($col) {
            'BUKRS' => $company,
            'ESTNR' => 'EST' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'DIVNR' => 'D' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'SPART' => 'D' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'BLOCK' => 'B' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'NAME1' => 'SAMPLE ESTATE ' . $i,
            'VTEXT' => 'SAMPLE DIVISION ' . $i,
            'BNAME' => 'SAMPLE BLOCK ' . $i,
            'WERKS' => 'P' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'BSTATE' => 'MATURE',
            'BHA'    => (string) (10 + $i),
            'POINT'  => (string) (100 + $i),
            'PLBLK'  => '1',
            'CROP_TYPE' => 'PALM',
            'KDATB'  => '2020.01.01',
            'KDATE'  => '2099.12.31',
            'EMPNR'  => 'EMP' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'ENAME'  => 'SAMPLE EMPLOYEE ' . $i,
            'PRFNR'  => 'PRF01',
            'JBCDE'  => 'HARV',
            'JBTYP'  => 'FIELD',
            'SEX'    => $i % 2 === 0 ? 'F' : 'M',
            'STATS'  => 'ACTIVE',
            'WOPXD'  => '2099.12.31',
            'DEPNR'  => 'FIELD',
            // Activity
            'ACTVT_NO'    => 'ACT' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'ACTVT_NAME'  => 'SAMPLE ACTIVITY ' . $i,
            'AMEIN'       => 'Hectare',
            'AMEIN2'      => 'HA',
            'BLOCK'       => 'X',
            'COST_CENTER' => '',
            'AUC'         => '',
            'ORDER_NUMBER'=> '',
            'BLOCK_LC'    => '',
            'BLOCK_IMMATURE' => '',
            'BLOCK_SCOUT' => '',
            'BLOCK_MATURE'=> 'X',
            'WRK_GRP'     => 'GRP' . $i,
            'DTWBS'       => '',
            // Vendor
            'LIFNR'  => 'VEND' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            // Material
            'MATNR'  => 'MAT' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'MAKTX'  => 'SAMPLE MATERIAL ' . $i,
            'MEINS'  => 'KG',
            'MTART'  => 'ZFER',
            'MATKL'  => 'GRP' . $i,
            'LGORT'  => 'SL01',
            'CHARG'  => '',
            // Worktype
            'AUART'  => 'WT' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'BEZEI'  => 'SAMPLE WORKTYPE ' . $i,
            // Work Center
            'ARBPL'  => 'WC' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'KTEXT'  => 'SAMPLE WORK CENTER ' . $i,
            // Cost Center
            'KOSTL'  => 'CC' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'LTEXT'  => 'SAMPLE COST CENTER ' . $i,
            'GSBER'  => 'BA0' . $i,
            'DATAB'  => '2020.01.01',
            'DATBI'  => '2099.12.31',
            default  => 'VAL' . $i,
        };
    }
}
