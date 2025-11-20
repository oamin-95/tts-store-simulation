<?php

/**
 * تحليل مشكلة الصلاحيات بالتفصيل
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

echo "🔍 تحليل مشكلة الصلاحيات\n";
echo "========================\n\n";

// =======================
// Test 1: Service Account Token
// =======================
echo "📋 Test 1: Service Account Token\n";
echo "--------------------------------\n";

$response = $client->post('/realms/master/protocol/openid-connect/token', [
    'form_params' => [
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
    ],
]);

$serviceTokenData = json_decode($response->getBody()->getContents(), true);
$serviceToken = $serviceTokenData['access_token'];

$parts = explode('.', $serviceToken);
$servicePayload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);

echo "Realm Access Roles:\n";
print_r($servicePayload['realm_access']['roles'] ?? []);
echo "\n";

echo "Resource Access (master-realm client):\n";
if (isset($servicePayload['resource_access']['master-realm'])) {
    print_r($servicePayload['resource_access']['master-realm']['roles']);
} else {
    echo "  ❌ لا توجد صلاحيات على master-realm!\n";
}
echo "\n\n";

// =======================
// Test 2: Admin User Token (للمقارنة)
// =======================
echo "📋 Test 2: Admin User Token (للمقارنة)\n";
echo "--------------------------------------\n";

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

$parts = explode('.', $adminToken);
$adminPayload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);

echo "Realm Access Roles:\n";
print_r($adminPayload['realm_access']['roles'] ?? []);
echo "\n";

echo "Resource Access:\n";
print_r($adminPayload['resource_access'] ?? []);
echo "\n\n";

// =======================
// Test 3: اختبار عملي
// =======================
echo "📋 Test 3: اختبار عملي - إنشاء Realm\n";
echo "-------------------------------------\n";

$testRealmId = 'test-compare-' . time();

// Test with Service Account
echo "محاولة 1: Service Account...\n";
try {
    $response = $client->post('/admin/realms', [
        'headers' => [
            'Authorization' => 'Bearer ' . $serviceToken,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'realm' => $testRealmId . '-service',
            'enabled' => true,
        ],
    ]);
    echo "✅ نجح!\n";
    $serviceRealmCreated = true;
    $serviceRealmId = $testRealmId . '-service';
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n";
    $serviceRealmCreated = false;
}
echo "\n";

// Test with Admin User
echo "محاولة 2: Admin User...\n";
try {
    $response = $client->post('/admin/realms', [
        'headers' => [
            'Authorization' => 'Bearer ' . $adminToken,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'realm' => $testRealmId . '-admin',
            'enabled' => true,
        ],
    ]);
    echo "✅ نجح!\n";
    $adminRealmCreated = true;
    $adminRealmId = $testRealmId . '-admin';
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n";
    $adminRealmCreated = false;
}
echo "\n\n";

// =======================
// Test 4: اختبار إدارة Users في الـ Realm
// =======================
if ($serviceRealmCreated) {
    echo "📋 Test 4: إدارة Users في Realm (Service Account)\n";
    echo "------------------------------------------------\n";

    try {
        $response = $client->get("/admin/realms/{$serviceRealmId}/users", [
            'headers' => ['Authorization' => 'Bearer ' . $serviceToken],
        ]);
        echo "✅ نجح الاستعلام عن Users!\n\n";
    } catch (\Exception $e) {
        echo "❌ فشل: " . $e->getMessage() . "\n\n";
    }
}

if ($adminRealmCreated) {
    echo "📋 Test 5: إدارة Users في Realm (Admin User)\n";
    echo "--------------------------------------------\n";

    try {
        $response = $client->get("/admin/realms/{$adminRealmId}/users", [
            'headers' => ['Authorization' => 'Bearer ' . $adminToken],
        ]);
        echo "✅ نجح الاستعلام عن Users!\n\n";
    } catch (\Exception $e) {
        echo "❌ فشل: " . $e->getMessage() . "\n\n";
    }
}

// =======================
// Cleanup
// =======================
echo "🧹 التنظيف...\n";
if ($serviceRealmCreated) {
    try {
        $client->delete("/admin/realms/{$serviceRealmId}", [
            'headers' => ['Authorization' => 'Bearer ' . $serviceToken],
        ]);
        echo "✅ حذف {$serviceRealmId}\n";
    } catch (\Exception $e) {
        echo "❌ فشل حذف {$serviceRealmId}\n";
    }
}

if ($adminRealmCreated) {
    try {
        $client->delete("/admin/realms/{$adminRealmId}", [
            'headers' => ['Authorization' => 'Bearer ' . $adminToken],
        ]);
        echo "✅ حذف {$adminRealmId}\n";
    } catch (\Exception $e) {
        echo "❌ فشل حذف {$adminRealmId}\n";
    }
}

echo "\n========================\n";
echo "انتهى التحليل\n";
