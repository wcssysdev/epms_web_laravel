<?php
/**
 * EPMS Mobile API Login Test
 * ===========================
 * Login ke API Laravel lalu tampilkan token + ringkasan payload.
 * Token bisa langsung dipakai ke _api_test_helper.php.
 *
 * Usage:
 *   php _api_login_test.php <user_login> <password> [role_hint]
 *   php _api_login_test.php wh_clerk mypass warehouse_clerk
 *
 * Atau set via env:
 *   set EPMS_USER=wh_clerk && set EPMS_PASS=mypass && php _api_login_test.php
 */

$user  = $argv[1] ?? getenv('EPMS_USER') ?: null;
$pass  = $argv[2] ?? getenv('EPMS_PASS') ?: null;
$API   = getenv('EPMS_API') ?: 'http://127.0.0.1:8000';

if (! $user || ! $pass) {
    echo "Usage: php _api_login_test.php <user_login> <password>\n";
    exit(1);
}

$loginUrl = rtrim($API, '/') . '/api/v1_1/auth/login';
echo "POST $loginUrl (user_login=$user, is_empty=1)\n";

$ch = curl_init($loginUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_POSTFIELDS     => http_build_query([
        'user_login' => $user,
        'password'   => $pass,
        'is_empty'   => '1',
    ]),
]);
$result = curl_exec($ch);
$http   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP $http\n";
$j = json_decode($result, true);

if ($http !== 200 || ! is_array($j)) {
    echo "Error: " . ($j['message'] ?? substr($result, 0, 300)) . "\n";
    exit(1);
}

$token      = $j['global']['M_Config_Schema'][0]['token']       ?? null;
$userId     = $j['global']['M_Config_Schema'][0]['user_id']     ?? null;
$roles      = $j['global']['Roles_Schema']                       ?? [];
$loginDate  = $j['global']['M_Config_Schema'][0]['login_date']  ?? null;
$resetFlags = $j['reset_master_data']                            ?? [];

echo "\n=== Login OK ===\n";
echo "user_id   : $userId\n";
echo "login_date: $loginDate\n";
echo "roles     : " . json_encode(array_column($roles, 'user_roles')) . "\n";
echo "token     : $token\n";

echo "\nreset_master_data flags:\n";
foreach ($resetFlags as $k => $v) echo "  $k = $v\n";

$roleKeys = array_keys(array_diff_key($j, ['reset_master_data'=>1,'global'=>1]));
if (! empty($roleKeys)) {
    echo "\nrole-specific buckets: " . implode(', ', $roleKeys) . "\n";
    foreach ($roleKeys as $rk) {
        echo "  [$rk] keys: " . implode(', ', array_keys($j[$rk])) . "\n";
    }
}

echo "\n=== Token for upload test ===\n";
echo "EPMS_TOKEN=$token\n";
echo "\nRun upload test:\n";
echo "  set EPMS_TOKEN=$token && php _api_test_helper.php harvest_clerk\n";
