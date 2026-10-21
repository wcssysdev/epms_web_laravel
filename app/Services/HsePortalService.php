<?php

namespace App\Services;

use App\Models\Global\CompanyConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HSE Portal Integration Service — replaces CI3 hse_helper.php
 * (get_master_data_from_hse() / post_data_to_hse_api()).
 *
 * Talks to the external HSE Portal REST API over HTTP + JSON with an
 * `x-api-key` header. This is a DIFFERENT transport from SapService
 * (which uses XML + Basic Auth), so it is kept as a separate service.
 *
 * Verified contract (mirrors hse_helper.php):
 *   - Headers: Content-Type: application/json, x-api-key: <configured key>
 *   - TLS: CURLOPT_SSL_VERIFYPEER / CURLOPT_SSL_VERIFYHOST = false
 *           → Http::withOptions(['verify' => false])
 *   - Timeout: 30s
 *   - Base URL: m_company_config.hse_api_url (per-company), trailing slash trimmed.
 *   - API key: application/environment-configured (NOT a DB column) via
 *              config('services.hse.api_key').
 *   - Endpoint roots: MasterData/*, training-courses/*, hirarc/* — the service
 *     makes NO assumption about a MasterData/ prefix.
 *   - Response: HTTP 200 → json_decode(...) into an array (top-level JSON array
 *     for master resources); otherwise raw body returned with the status code.
 *     Shape: ['data' => array|string, 'status_code' => int].
 */
class HsePortalService
{
    /** cURL/Http timeout in seconds (mirrors CI3 hse_helper CURLOPT_TIMEOUT). */
    private const TIMEOUT = 30;

    public function __construct(
        private string $baseUrl,
        private string $apiKey,
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    /**
     * Build the service for the CURRENT company.
     *
     * Resolves the current company's CompanyConfig the same way SapService does
     * (CompanyConfig::where('company_id', ...)) and reads m_company_config.hse_api_url
     * via the Eloquent model ($config->hse_api_url). The API key is read from
     * application config (config/services.php 'hse.api_key'), NOT from the DB.
     */
    public static function fromConfig(): self
    {
        $companyId = current_company_id();

        $config = $companyId !== null
            ? CompanyConfig::where('company_id', $companyId)->first()
            : null;

        $baseUrl = (string) ($config?->hse_api_url ?? '');
        $apiKey = (string) config('services.hse.api_key', '');

        return new self($baseUrl, $apiKey);
    }

    /**
     * GET {baseUrl}/{endpoint}.
     *
     * @param  string  $endpoint  e.g. 'MasterData/category-of-injuries',
     *                            'training-courses/all', 'hirarc/all'
     * @return array{data: array|string, status_code: int}
     */
    public function get(string $endpoint): array
    {
        return $this->request('GET', $endpoint);
    }

    /**
     * POST {baseUrl}/{endpoint} with a JSON body.
     *
     * @return array{data: array|string, status_code: int}
     */
    public function post(string $endpoint, array $body = []): array
    {
        return $this->request('POST', $endpoint, $body);
    }

    /**
     * Issue the HTTP request, preserving the exact hse_helper.php transport.
     *
     * @return array{data: array|string, status_code: int}
     */
    private function request(string $method, string $endpoint, array $body = []): array
    {
        // Configuration error: base URL unset/empty/whitespace → abort fetch.
        if (trim($this->baseUrl) === '') {
            Log::warning('HsePortalService: hse_api_url is not configured for the current company.');

            return [
                'data' => 'HSE Portal API URL is not configured.',
                'status_code' => 0,
            ];
        }

        // Join base URL + endpoint with exactly one '/'.
        $url = $this->baseUrl.'/'.ltrim($endpoint, '/');

        try {
            $client = Http::withOptions(['verify' => false]) // SSL_VERIFYPEER/HOST = false
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(self::TIMEOUT);

            $response = $method === 'POST'
                ? $client->post($url, $body)
                : $client->get($url);
        } catch (\Throwable $e) {
            Log::error('HsePortalService '.$method.' error: '.$e->getMessage().' | url: '.$url);

            return [
                'data' => $e->getMessage(),
                'status_code' => 0,
            ];
        }

        $status = $response->status();

        // HTTP 200 → decode JSON into an array; otherwise return raw body.
        // POST also treats 201 as success (mirrors hse_helper post_data_to_hse_api).
        $decodeOk = $status === 200 || ($method === 'POST' && $status === 201);

        if ($decodeOk) {
            $decoded = json_decode($response->body(), true);
            $data = json_last_error() === JSON_ERROR_NONE ? $decoded : $response->body();
        } else {
            $data = $response->body();
        }

        return [
            'data' => $data,
            'status_code' => $status,
        ];
    }

    /** Hard upper bound on page requests, guarding against an infinite loop. */
    private const MAX_PAGES = 1000;

    /**
     * Convenience: GET a paginated MasterData resource and return a normalized,
     * flat, order-preserving item array.
     *
     * Transport: reuses {@see get()} unchanged (x-api-key, TLS, 30s timeout,
     * URL join, config-error handling). This method adds pagination + response
     * shape normalization ONLY; it performs NO DB side effects — it just returns
     * the collected items (or [] on a fetch error, leaving prior data untouched).
     *
     * Pagination: appends `?pageNumber=N&pageSize=M` (using `&` when the endpoint
     * already carries a query string). Pages are requested in order from
     * pageNumber=1 and each page's items are concatenated in order. The loop
     * stops when a page returns fewer items than $pageSize (the last page),
     * returns an empty/no-items page, or the hard bound of self::MAX_PAGES is
     * reached.
     *
     * Response shapes (per page, from a 200 body):
     *   - top-level JSON array  → used directly (verified live-portal shape);
     *   - { "items": [...] }    → `items` used (defensive fallback);
     *   - any other 200 shape   → treated as a fetch error for that page.
     *
     * Fetch-error handling:
     *   - Non-200 status from get(): the page is a fetch error. On page 1 this
     *     returns []; on a later page, pagination stops and the items already
     *     collected are returned.
     *   - An unrecognized 200 body shape is handled identically to a non-200.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchMasterData(string $endpoint, int $pageSize = 200): array
    {
        // A non-positive page size can never satisfy the "fewer than pageSize"
        // stop condition sensibly; treat as a single-page request.
        $pageSize = max(1, $pageSize);

        $sep = str_contains($endpoint, '?') ? '&' : '?';
        $items = [];

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $res = $this->get($endpoint.$sep.'pageNumber='.$page.'&pageSize='.$pageSize);

            // Non-200 → fetch error for this page.
            if (($res['status_code'] ?? 0) !== 200) {
                break;
            }

            $pageItems = $this->normalizePage($res['data'] ?? null);

            // Unrecognized 200 shape → treat as a fetch error for this page.
            if ($pageItems === null) {
                break;
            }

            // Empty / no-items page → nothing more to collect.
            if ($pageItems === []) {
                break;
            }

            // Preserve element order across pages.
            foreach ($pageItems as $item) {
                $items[] = $item;
            }

            // A short page means we have reached the last page.
            if (count($pageItems) < $pageSize) {
                break;
            }
        }

        return $items;
    }

    /**
     * Normalize a single 200 response body into a flat list of items.
     *
     * @param  mixed  $data  Decoded 200 body from {@see get()}.
     * @return array<int, mixed>|null Flat item list, or null when the body is
     *                                an unrecognized shape (not a list and not
     *                                a `{items:[...]}` envelope).
     */
    private function normalizePage(mixed $data): ?array
    {
        // Verified live-portal shape: a top-level JSON array.
        if (is_array($data) && array_is_list($data)) {
            return $data;
        }

        // Defensive fallback: { "items": [...] } envelope.
        if (is_array($data) && isset($data['items']) && is_array($data['items'])) {
            return array_values($data['items']);
        }

        // Anything else (scalar, string body, non-list map without items) is
        // not a recognized master-data payload.
        return null;
    }
}
