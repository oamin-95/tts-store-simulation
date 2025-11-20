<?php

/**
 * اختبار منح الصلاحيات تلقائياً بعد إنشاء Realm
 */

use GuzzleHttp\Client;

require __DIR__.'/vendor/autoload.php';

$keycloakUrl = 'http://localhost:8090';
$clientId = 'saas-marketplace-admin';
$clientSecret = 'M1VVIsCH9WsrSOWJwul9MoB3o4MIKZ1W';

$client = new Client([
    'base_uri' => $keycloakUrl,
    'verify' => false,
    'timeout' => 30,
]);

echo "🔧 اختبار منح الصلاحيات التلقائية\n";
echo "==================================\n\n";

// Step 1: Get access token
echo "Step 1: الحصول على Access Token...\n";
$response = $client->post('/realms/master/protocol/openid-connect/token', [
    'form_params' => [
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
    ],
]);

$tokenData = json_decode($response->getBody()->getContents(), true);
$accessToken = $tokenData['access_token'];
echo "✅ نجح!\n\n";

// Step 2: Create realm
$testRealmId = 'test-auto-perms-' . time();
echo "Step 2: إنشاء Realm: {$testRealmId}...\n";

$response = $client->post('/admin/realms', [
    'headers' => [
        'Authorization' => 'Bearer ' . $accessToken,
        'Content-Type' => 'application/json',
    ],
    'json' => [
        'realm' => $testRealmId,
        'displayName' => 'Test Auto Permissions',
        'enabled' => true,
    ],
]);

echo "✅ تم إنشاء Realm!\n\n";

// Step 3: Get service account user ID
echo "Step 3: الحصول على Service Account User ID...\n";

$response = $client->get('/admin/realms/master/users', [
    'headers' => ['Authorization' => 'Bearer ' . $accessToken],
    'query' => ['username' => 'service-account-saas-marketplace-admin'],
]);

$users = json_decode($response->getBody()->getContents(), true);
$serviceAccountUserId = $users[0]['id'];

echo "✅ Service Account User ID: {$serviceAccountUserId}\n\n";

// Step 4: Get realm-management client ID in the new realm
echo "Step 4: الحصول على realm-management client...\n";

$response = $client->get("/admin/realms/{$testRealmId}/clients", [
    'headers' => ['Authorization' => 'Bearer ' . $accessToken],
    'query' => ['clientId' => 'realm-management'],
]);

$clients = json_decode($response->getBody()->getContents(), true);
$realmManagementId = $clients[0]['id'];

echo "✅ realm-management ID: {$realmManagementId}\n\n";

// Step 5: Get realm-admin role from realm-management
echo "Step 5: الحصول على realm-admin role...\n";

$response = $client->get("/admin/realms/{$testRealmId}/clients/{$realmManagementId}/roles", [
    'headers' => ['Authorization' => 'Bearer ' . $accessToken],
]);

$roles = json_decode($response->getBody()->getContents(), true);
$realmAdminRole = null;

foreach ($roles as $role) {
    if ($role['name'] === 'realm-admin') {
        $realmAdminRole = $role;
        break;
    }
}

if (!$realmAdminRole) {
    echo "❌ لم يتم العثور على realm-admin role!\n";
    exit(1);
}

echo "✅ realm-admin role ID: {$realmAdminRole['id']}\n\n";

// Step 6: Assign realm-admin role to service account
echo "Step 6: منح realm-admin للـ Service Account...\n";

$response = $client->post(
    "/admin/realms/{$testRealmId}/users/{$serviceAccountUserId}/role-mappings/clients/{$realmManagementId}",
    [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ],
        'json' => [$realmAdminRole],
    ]
);

echo "✅ تم منح الصلاحيات!\n\n";

// Step 7: Test - Create a user in the new realm
echo "Step 7: اختبار - إنشاء مستخدم...\n";

try {
    $response = $client->post("/admin/realms/{$testRealmId}/users", [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'username' => 'test@example.com',
            'email' => 'test@example.com',
            'enabled' => true,
            'credentials' => [[
                'type' => 'password',
                'value' => 'test123',
                'temporary' => false,
            ]],
        ],
    ]);

    echo "✅ نجح إنشاء المستخدم!\n\n";

} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
}

// Step 8: Test - Query users
echo "Step 8: اختبار - الاستعلام عن المستخدمين...\n";

try {
    $response = $client->get("/admin/realms/{$testRealmId}/users", [
        'headers' => ['Authorization' => 'Bearer ' . $accessToken],
    ]);

    $users = json_decode($response->getBody()->getContents(), true);
    echo "✅ نجح! عدد المستخدمين: " . count($users) . "\n\n";

} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
}

echo "==================================\n";
echo "✅ الاختبار مكتمل!\n";
echo "==================================\n\n";

echo "Realm: {$testRealmId}\n";
echo "URL: {$keycloakUrl}/admin/{$testRealmId}/console\n\n";

echo "هل تريد حذف الـ Realm؟ (y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim(strtolower($line)) === 'y') {
    try {
        $client->delete("/admin/realms/{$testRealmId}", [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
        ]);
        echo "✅ تم الحذف\n";
    } catch (\Exception $e) {
        echo "❌ فشل الحذف: " . $e->getMessage() . "\n";
    }
}
