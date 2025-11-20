<?php

/**
 * اختبار منح صلاحية realm-management على مستوى Master
 */

use GuzzleHttp\Client;

require __DIR__.'/vendor/autoload.php';

$keycloakUrl = 'http://localhost:8090';
$clientId = 'saas-marketplace-admin';
$clientSecret = 'M1VVIsCH9WsrSOWJwul9MoB3o4MIKZ1W';
$adminUser = 'admin';
$adminPassword = 'admin123';

$client = new Client([
    'base_uri' => $keycloakUrl,
    'verify' => false,
    'timeout' => 30,
]);

echo "🔧 اختبار منح صلاحيات realm-management\n";
echo "=======================================\n\n";

// Get admin token (لمنح الصلاحيات)
echo "Step 1: الحصول على Admin Token...\n";
$response = $client->post('/realms/master/protocol/openid-connect/token', [
    'form_params' => [
        'grant_type' => 'password',
        'client_id' => 'admin-cli',
        'username' => $adminUser,
        'password' => $adminPassword,
    ],
]);

$adminTokenData = json_decode($response->getBody()->getContents(), true);
$adminToken = $adminTokenData['access_token'];
echo "✅ نجح!\n\n";

// Get service account user ID
echo "Step 2: الحصول على Service Account User ID...\n";
$response = $client->get('/admin/realms/master/users', [
    'headers' => ['Authorization' => 'Bearer ' . $adminToken],
    'query' => ['username' => 'service-account-saas-marketplace-admin'],
]);

$users = json_decode($response->getBody()->getContents(), true);
if (empty($users)) {
    echo "❌ Service Account User لم يتم العثور عليه!\n";
    exit(1);
}

$serviceAccountUserId = $users[0]['id'];
echo "✅ User ID: {$serviceAccountUserId}\n\n";

// Get realm-management client ID in Master realm
echo "Step 3: الحصول على realm-management client في Master...\n";
$response = $client->get('/admin/realms/master/clients', [
    'headers' => ['Authorization' => 'Bearer ' . $adminToken],
    'query' => ['clientId' => 'realm-management'],
]);

$clients = json_decode($response->getBody()->getContents(), true);
if (empty($clients)) {
    echo "❌ realm-management client لم يتم العثور عليه!\n";
    exit(1);
}

$realmManagementId = $clients[0]['id'];
echo "✅ Client ID: {$realmManagementId}\n\n";

// Get all available roles from realm-management
echo "Step 4: الحصول على جميع الأدوار المتاحة...\n";
$response = $client->get("/admin/realms/master/clients/{$realmManagementId}/roles", [
    'headers' => ['Authorization' => 'Bearer ' . $adminToken],
]);

$allRoles = json_decode($response->getBody()->getContents(), true);
echo "✅ عدد الأدوار المتاحة: " . count($allRoles) . "\n";

// Find realm-admin role
$realmAdminRole = null;
foreach ($allRoles as $role) {
    if ($role['name'] === 'realm-admin') {
        $realmAdminRole = $role;
        break;
    }
}

if (!$realmAdminRole) {
    echo "❌ realm-admin role غير موجود!\n";
    exit(1);
}
echo "✅ realm-admin role موجود: {$realmAdminRole['id']}\n\n";

// Check current role mappings
echo "Step 5: فحص الصلاحيات الحالية للـ Service Account...\n";
$response = $client->get("/admin/realms/master/users/{$serviceAccountUserId}/role-mappings/clients/{$realmManagementId}", [
    'headers' => ['Authorization' => 'Bearer ' . $adminToken],
]);

$currentRoles = json_decode($response->getBody()->getContents(), true);
echo "الصلاحيات الحالية من realm-management:\n";
foreach ($currentRoles as $role) {
    echo "  - {$role['name']}\n";
}
echo "\n";

// Check if realm-admin is already assigned
$hasRealmAdmin = false;
foreach ($currentRoles as $role) {
    if ($role['name'] === 'realm-admin') {
        $hasRealmAdmin = true;
        break;
    }
}

if ($hasRealmAdmin) {
    echo "✅ realm-admin موجود بالفعل في Master realm!\n\n";
} else {
    echo "⚠️  realm-admin غير موجود، سأقوم بإضافته...\n";

    try {
        $response = $client->post(
            "/admin/realms/master/users/{$serviceAccountUserId}/role-mappings/clients/{$realmManagementId}",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $adminToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [$realmAdminRole],
            ]
        );
        echo "✅ تم إضافة realm-admin بنجاح!\n\n";
    } catch (\Exception $e) {
        echo "❌ فشل: " . $e->getMessage() . "\n\n";
    }
}

// Now test with new token
echo "Step 6: اختبار مع Token جديد...\n";
$response = $client->post('/realms/master/protocol/openid-connect/token', [
    'form_params' => [
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
    ],
]);

$serviceTokenData = json_decode($response->getBody()->getContents(), true);
$serviceToken = $serviceTokenData['access_token'];

// Decode to check roles
$parts = explode('.', $serviceToken);
$payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);

echo "الصلاحيات في Token:\n";
echo "  Realm Access: " . json_encode($payload['realm_access']['roles'] ?? []) . "\n";
if (isset($payload['resource_access']['master']['realm-management'])) {
    echo "  Resource Access (realm-management): " . json_encode($payload['resource_access']['master']['realm-management']['roles']) . "\n";
}
echo "\n";

// Test creating realm
echo "Step 7: اختبار إنشاء Realm...\n";
$testRealmId = 'test-final-' . time();

try {
    $response = $client->post('/admin/realms', [
        'headers' => [
            'Authorization' => 'Bearer ' . $serviceToken,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'realm' => $testRealmId,
            'enabled' => true,
        ],
    ]);
    echo "✅ نجح إنشاء Realm!\n\n";
    $realmCreated = true;
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $realmCreated = false;
}

// Test managing users in new realm
if ($realmCreated) {
    echo "Step 8: اختبار إدارة Users في الـ Realm الجديد...\n";

    try {
        $response = $client->get("/admin/realms/{$testRealmId}/users", [
            'headers' => ['Authorization' => 'Bearer ' . $serviceToken],
        ]);
        echo "✅ نجح الاستعلام عن Users!\n\n";

        // Try creating a user
        echo "Step 9: اختبار إنشاء User...\n";
        $response = $client->post("/admin/realms/{$testRealmId}/users", [
            'headers' => [
                'Authorization' => 'Bearer ' . $serviceToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'username' => 'test@example.com',
                'email' => 'test@example.com',
                'enabled' => true,
            ],
        ]);
        echo "✅ نجح إنشاء User!\n\n";

    } catch (\Exception $e) {
        echo "❌ فشل: " . $e->getMessage() . "\n\n";
    }

    // Cleanup
    echo "🧹 التنظيف...\n";
    try {
        $client->delete("/admin/realms/{$testRealmId}", [
            'headers' => ['Authorization' => 'Bearer ' . $serviceToken],
        ]);
        echo "✅ تم حذف Realm\n";
    } catch (\Exception $e) {
        echo "⚠️  لم يتم الحذف (استخدم Admin Console)\n";
    }
}

echo "\n=======================================\n";
echo "انتهى الاختبار\n";
