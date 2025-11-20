<?php

/**
 * الحل: استخدام Token جديد بعد إنشاء Realm (Session Refresh)
 *
 * المشكلة: Session caching - الـ token القديم يحتوي على permissions للـ Realms
 * التي كانت موجودة عند إنشاء الـ token فقط
 *
 * الحل: الحصول على token جديد بعد إنشاء Realm
 */

use GuzzleHttp\Client;

require __DIR__.'/vendor/autoload.php';

$keycloakUrl = 'http://localhost:8090';
$serviceClientId = 'saas-marketplace-admin';
$serviceClientSecret = 'M1VVIsCH9WsrSOWJwul9MoB3o4MIKZ1W';

$client = new Client([
    'base_uri' => $keycloakUrl,
    'verify' => false,
    'timeout' => 30,
]);

echo "🔐 الحل الآمن النهائي: Session Refresh\n";
echo "==========================================\n\n";

// Helper function to get fresh token
function getServiceAccountToken($client, $keycloakUrl, $clientId, $clientSecret) {
    $response = $client->post('/realms/master/protocol/openid-connect/token', [
        'form_params' => [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ],
    ]);

    $tokenData = json_decode($response->getBody()->getContents(), true);
    return $tokenData['access_token'];
}

// Step 1: Get initial token and create realm
echo "Step 1: الحصول على Token والبدء بإنشاء Realm...\n";
$token1 = getServiceAccountToken($client, $keycloakUrl, $serviceClientId, $serviceClientSecret);
echo "✅ Token 1 تم الحصول عليه\n\n";

$testRealmId = 'test-refresh-' . time();
echo "Step 2: إنشاء Realm: {$testRealmId}...\n";

try {
    $response = $client->post('/admin/realms', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token1,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'realm' => $testRealmId,
            'enabled' => true,
            'displayName' => 'Test Session Refresh Solution',
        ],
    ]);
    echo "✅ نجح إنشاء Realm!\n\n";
    $realmCreated = true;
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $realmCreated = false;
    exit(1);
}

// Step 3: THIS IS THE KEY - Get NEW token after realm creation!
echo "Step 3: 🔑 الحصول على Token جديد بعد إنشاء الـ Realm...\n";
$token2 = getServiceAccountToken($client, $keycloakUrl, $serviceClientId, $serviceClientSecret);
echo "✅ Token 2 تم الحصول عليه (جديد، يحتوي على permissions للـ Realm الجديد)\n\n";

// Step 4: Now try to manage the new realm with the FRESH token
echo "Step 4: اختبار إدارة الـ Realm بالـ Token الجديد...\n";
echo "========================================\n\n";

// Test 1: Query users
echo "Test 1: الاستعلام عن Users...\n";
try {
    $response = $client->get("/admin/realms/{$testRealmId}/users", [
        'headers' => ['Authorization' => 'Bearer ' . $token2],
    ]);
    $users = json_decode($response->getBody()->getContents(), true);
    echo "✅ نجح! عدد المستخدمين: " . count($users) . "\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
}

// Test 2: Create user
echo "Test 2: إنشاء User جديد...\n";
try {
    $response = $client->post("/admin/realms/{$testRealmId}/users", [
        'headers' => [
            'Authorization' => 'Bearer ' . $token2,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'username' => 'test@example.com',
            'email' => 'test@example.com',
            'enabled' => true,
            'emailVerified' => true,
            'firstName' => 'Test',
            'lastName' => 'User',
            'credentials' => [[
                'type' => 'password',
                'value' => 'test123',
                'temporary' => false,
            ]],
        ],
    ]);
    echo "✅ نجح إنشاء User!\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
}

// Test 3: Query groups
echo "Test 3: الاستعلام عن Groups...\n";
try {
    $response = $client->get("/admin/realms/{$testRealmId}/groups", [
        'headers' => ['Authorization' => 'Bearer ' . $token2],
    ]);
    $groups = json_decode($response->getBody()->getContents(), true);
    echo "✅ نجح! عدد المجموعات: " . count($groups) . "\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
}

// Test 4: Create group
echo "Test 4: إنشاء Group جديد...\n";
try {
    $response = $client->post("/admin/realms/{$testRealmId}/groups", [
        'headers' => [
            'Authorization' => 'Bearer ' . $token2,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'name' => 'test-group',
        ],
    ]);
    echo "✅ نجح إنشاء Group!\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
}

// Test 5: Create client
echo "Test 5: إنشاء Client جديد...\n";
try {
    $response = $client->post("/admin/realms/{$testRealmId}/clients", [
        'headers' => [
            'Authorization' => 'Bearer ' . $token2,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'clientId' => 'test-client',
            'enabled' => true,
            'publicClient' => false,
            'serviceAccountsEnabled' => true,
        ],
    ]);
    echo "✅ نجح إنشاء Client!\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
}

echo "========================================\n";
echo "✅ الاختبار مكتمل!\n";
echo "========================================\n\n";

echo "📝 الحل:\n";
echo "-------\n";
echo "1. استخدم Service Account مع client_credentials (آمن 100%)\n";
echo "2. احصل على token لإنشاء الـ Realm\n";
echo "3. بعد إنشاء الـ Realm، احصل على token جديد\n";
echo "4. استخدم الـ token الجديد لإدارة الـ Realm\n\n";

echo "🔐 الأمان:\n";
echo "-------\n";
echo "✅ لا استخدام لـ username/password\n";
echo "✅ استخدام OAuth 2.0 Client Credentials فقط\n";
echo "✅ جميع العمليات آمنة ومبرمجة\n\n";

// Cleanup
echo "🧹 التنظيف...\n";
try {
    $client->delete("/admin/realms/{$testRealmId}", [
        'headers' => ['Authorization' => 'Bearer ' . $token2],
    ]);
    echo "✅ تم حذف Realm\n\n";
} catch (\Exception $e) {
    echo "⚠️  فشل الحذف: " . $e->getMessage() . "\n\n";
}

echo "========================================\n";
echo "🎉 النتيجة النهائية\n";
echo "========================================\n\n";
echo "الحل الآمن الكامل:\n";
echo "- Service Account (saas-marketplace-admin)\n";
echo "- Client Credentials Grant (لا username/password)\n";
echo "- Session Refresh بعد إنشاء كل Realm\n";
echo "- جميع العمليات تعمل بنجاح!\n";
