<?php
/**
 * EPMS Mobile API Test Helper
 * ============================
 * Mengambil payload nyata dari tph.res_data (CI3) sesuai role,
 * strip konten photo (base64), lalu kirim ke API Laravel /api/v1_1/in/upload.
 *
 * Usage:
 *   php _api_test_helper.php <role_key> [limit]
 *   php _api_test_helper.php harvest_clerk
 *   php _api_test_helper.php transport_clerk 3
 *
 * Role keys yang tersedia:
 *   field_staff, harvest_clerk, transport_clerk,
 *   harvest_clerk_coconut, transport_clerk_coconut, mill_grader
 *
 * Set TOKEN ke user_token yang valid (login dulu via POST /api/v1_1/auth/login).
 */

$TOKEN = getenv('EPMS_TOKEN') ?: ($argv[3] ?? null);
$ROLE  = $argv[1] ?? null;
$LIMIT = (int) ($argv[2] ?? 1);
$API   = getenv('EPMS_API')   ?: 'http://127.0.0.1:8000/api/v1_1/in/upload';

if (! $ROLE) {
    echo "Usage: php _api_test_helper.php <role_key> [limit] [token]\n";
    echo "Available roles: field_staff, harvest_clerk, transport_clerk,\n";
    echo "                 harvest_clerk_coconut, transport_clerk_coconut, mill_grader\n";
    exit(1);
}

// ── Role → filter pattern (matches key in res_data.res_text) ─────────────────
$PATTERNS = [
    'field_staff'             => 'T_Attendance_Schema_List_Panen',
    'harvest_clerk'           => 'T_OPH_Schema_List',
    'transport_clerk'         => 'T_FDN_Detail_Schema_List',
    'harvest_clerk_coconut'   => 'T_Coconut_OPH_Schema_List',
    'transport_clerk_coconut' => 'T_FDN_Coconut_Schema_List',
    'mill_grader'             => 'T_OPH_MG_Schema_List',
    'muster_chit'             => 'T_Harvester_Assignment_Schema_List',
    'goods_gi'                => 'data_t_gi',
    'goods_gr'                => 'data_t_gr',
];

if (! isset($PATTERNS[$ROLE])) {
    echo "Unknown role: $ROLE\n";
    echo "Available: " . implode(', ', array_keys($PATTERNS)) . "\n";
    exit(1);
}

$pattern = $PATTERNS[$ROLE];
echo "=== Fetching '$ROLE' payload from tph.res_data (pattern: $pattern) ===\n";

// ── Connect to CI3 DB (tph) ───────────────────────────────────────────────────
try {
    $pdo = new PDO("pgsql:host=localhost;port=5432;dbname=tph", "postgres", "wilmar123", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    echo "DB connect failed: " . $e->getMessage() . "\n"; exit(1);
}

$stmt = $pdo->prepare(
    "SELECT res_id, res_text, res_timestamp
     FROM res_data
     WHERE res_text LIKE :pat
     ORDER BY res_id DESC
     LIMIT :lim"
);
$stmt->execute([':pat' => "%{$pattern}%", ':lim' => $LIMIT]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "No records found in tph.res_data for pattern '$pattern'.\n";
    exit(0);
}

echo "Found " . count($rows) . " record(s). Processing...\n\n";

foreach ($rows as $idx => $row) {
    echo "--- Record #" . ($idx + 1) . " (res_data.res_id={$row['res_id']}, ts={$row['res_timestamp']}) ---\n";

    $payload = json_decode($row['res_text'], true);
    if (! is_array($payload)) {
        echo "  !! Could not decode JSON. Skipping.\n"; continue;
    }

    // ── Strip photo content (base64 strings are large and unnecessary for testing)
    $payload = stripPhotos($payload);

    $epmsData = json_encode($payload);
    echo "  Payload size: " . number_format(strlen($epmsData)) . " chars (photos stripped)\n";
    echo "  Top-level keys: " . implode(', ', array_keys($payload)) . "\n";

    if (! $TOKEN) {
        echo "  [SKIP] No token provided. Set EPMS_TOKEN env or pass as 3rd argument.\n";
        echo "  Tip: login via: curl -s -X POST $API/../auth/login -d 'user_login=...&password=...&is_empty=1'\n";
        echo "  Payload saved to: /tmp/epms_payload_{$ROLE}_{$idx}.json\n\n";
        file_put_contents(sys_get_temp_dir() . "/epms_payload_{$ROLE}_{$idx}.json", json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        continue;
    }

    // ── POST to Laravel API ────────────────────────────────────────────────────
    echo "  POSTing to $API ...\n";
    $ch = curl_init($API);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'X-Api-Key: ' . $TOKEN,
        ],
        CURLOPT_POSTFIELDS => ['epms_data' => $epmsData],
    ]);
    $result = curl_exec($ch);
    $http   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($err) {
        echo "  !! cURL error: $err\n";
    } else {
        $j = json_decode($result, true);
        echo "  HTTP $http | " . ($j['message'] ?? substr($result, 0, 120)) . "\n";
    }
    echo "\n";
}

// ── Helper: recursively strip photo/base64 fields ─────────────────────────────
function stripPhotos(mixed $data): mixed
{
    if (is_string($data)) {
        // If value looks like base64 (>200 chars, no spaces) replace with empty string.
        if (strlen($data) > 200 && preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $data)) {
            return '';
        }
        return $data;
    }
    if (is_array($data)) {
        $out = [];
        foreach ($data as $k => $v) {
            // Keys likely to contain photo/base64 data.
            if (is_string($k) && preg_match('/photo|image|gambar|base64|picture/i', $k)) {
                $out[$k] = '';
            } else {
                $out[$k] = stripPhotos($v);
            }
        }
        return $out;
    }
    return $data;
}
