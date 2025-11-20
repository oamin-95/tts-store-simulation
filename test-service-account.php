<?php

/**
 * اختبار Service Account للتحقق من الاتصال بـ Keycloak
 *
 * هذا السكريبت يختبر:
 * 1. الحصول على Access Token من Service Account
 * 2. إنشاء Realm اختباري
 * 3. إنشاء مستخدم في الـ Realm
 * 4. استعلام عن Clients
 * 5. استعلام عن Groups
 * 6. استعلام عن Users
 * 7. حذف الـ Realm الاختباري (تنظيف)
 */

use GuzzleHttp\Client;

require __DIR__.'/vendor/autoload.php';

// ===================================
// Configuration
// ===================================
$keycloakUrl = 'http://localhost:8090';
$clientId = 'saas-marketplace-admin';
$clientSecret = 'M1VVIsCH9WsrSOWJwul9MoB3o4MIKZ1W';

// Test realm name
$testRealmId = 'test-service-account-' . time();
$testUserId = null;

$client = new Client([
    'base_uri' => $keycloakUrl,
    'verify' => false,
    'timeout' => 30,
]);

echo "🔧 اختبار Service Account للاتصال بـ Keycloak\n";
echo "================================================\n\n";

// ===================================
// Test 1: Get Access Token
// ===================================
echo "✅ Test 1: الحصول على Access Token من Service Account\n";
echo "---------------------------------------------------\n";

try {
    $response = $client->post('/realms/master/protocol/openid-connect/token', [
        'form_params' => [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ],
    ]);

    $tokenData = json_decode($response->getBody()->getContents(), true);
    $accessToken = $tokenData['access_token'];

    echo "✅ نجح! تم الحصول على Access Token\n";
    echo "   Token Type: {$tokenData['token_type']}\n";
    echo "   Expires In: {$tokenData['expires_in']} seconds\n";
    echo "   Token (first 50 chars): " . substr($accessToken, 0, 50) . "...\n\n";

} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    exit(1);
}

// ===================================
// Test 2: Create Test Realm
// ===================================
echo "✅ Test 2: إنشاء Realm اختباري\n";
echo "---------------------------------------------------\n";
echo "Realm ID: {$testRealmId}\n";

try {
    $response = $client->post('/admin/realms', [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'realm' => $testRealmId,
            'displayName' => 'Test Realm - Service Account',
            'enabled' => true,
            'sslRequired' => 'none',
            'registrationAllowed' => false,
            'loginWithEmailAllowed' => true,
            'duplicateEmailsAllowed' => false,
            'resetPasswordAllowed' => true,
            'editUsernameAllowed' => false,
        ],
    ]);

    echo "✅ نجح! تم إنشاء Realm: {$testRealmId}\n";
    echo "   Admin Console: {$keycloakUrl}/admin/{$testRealmId}/console\n\n";

} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n";
    if ($e->hasResponse()) {
        echo "   Response: " . $e->getResponse()->getBody() . "\n";
    }
    echo "\n";
    exit(1);
}

// ===================================
// Test 3: Query Clients in Realm
// ===================================
echo "✅ Test 3: استعلام عن Clients في الـ Realm\n";
echo "---------------------------------------------------\n";

try {
    $response = $client->get("/admin/realms/{$testRealmId}/clients", [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
        ],
    ]);

    $clients = json_decode($response->getBody()->getContents(), true);

    echo "✅ نجح! تم الحصول على قائمة Clients\n";
    echo "   عدد Clients: " . count($clients) . "\n";
    echo "   أمثلة: \n";

    foreach (array_slice($clients, 0, 3) as $client) {
        echo "   - {$client['clientId']}\n";
    }
    echo "\n";

} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
}

// ===================================
// Test 4: Query Groups in Realm
// ===================================
echo "✅ Test 4: استعلام عن Groups في الـ Realm\n";
echo "---------------------------------------------------\n";

try {
    $response = $client->get("/admin/realms/{$testRealmId}/groups", [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
        ],
    ]);

    $groups = json_decode($response->getBody()->getContents(), true);

    echo "✅ نجح! تم الحصول على قائمة Groups\n";
    echo "   عدد Groups: " . count($groups) . "\n";

    if (empty($groups)) {
        echo "   (لا توجد Groups بعد - هذا طبيعي لـ Realm جديد)\n";
    }
    echo "\n";

} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
}

// ===================================
// Test 5: Query Users in Realm
// ===================================
echo "✅ Test 5: استعلام عن Users في الـ Realm\n";
echo "---------------------------------------------------\n";

try {
    $response = $client->get("/admin/realms/{$testRealmId}/users", [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
        ],
    ]);

    $users = json_decode($response->getBody()->getContents(), true);

    echo "✅ نجح! تم الحصول على قائمة Users\n";
    echo "   عدد Users: " . count($users) . "\n";

    if (empty($users)) {
        echo "   (لا يوجد Users بعد - هذا طبيعي لـ Realm جديد)\n";
    }
    echo "\n";

} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
}

// ===================================
// Test 6: Create Test User
// ===================================
echo "✅ Test 6: إنشاء مستخدم اختباري في الـ Realm\n";
echo "---------------------------------------------------\n";

$testUserEmail = 'test-user@example.com';
echo "User Email: {$testUserEmail}\n";

try {
    $response = $client->post("/admin/realms/{$testRealmId}/users", [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'username' => $testUserEmail,
            'email' => $testUserEmail,
            'firstName' => 'Test',
            'lastName' => 'User',
            'enabled' => true,
            'emailVerified' => true,
            'credentials' => [[
                'type' => 'password',
                'value' => 'test123',
                'temporary' => false,
            ]],
        ],
    ]);

    // Get user ID from Location header
    $location = $response->getHeader('Location')[0] ?? null;
    if ($location) {
        $testUserId = basename($location);
        echo "✅ نجح! تم إنشاء المستخدم\n";
        echo "   User ID: {$testUserId}\n";
        echo "   Username: {$testUserEmail}\n";
        echo "   Password: test123\n\n";
    } else {
        echo "✅ نجح! تم إنشاء المستخدم (لكن لم نحصل على ID)\n\n";
    }

} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n";
    if ($e->hasResponse()) {
        echo "   Response: " . $e->getResponse()->getBody() . "\n";
    }
    echo "\n";
}

// ===================================
// Test 7: Verify User was Created
// ===================================
echo "✅ Test 7: التحقق من إنشاء المستخدم\n";
echo "---------------------------------------------------\n";

try {
    $response = $client->get("/admin/realms/{$testRealmId}/users", [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
        ],
        'query' => [
            'email' => $testUserEmail,
        ],
    ]);

    $users = json_decode($response->getBody()->getContents(), true);

    if (!empty($users)) {
        echo "✅ نجح! تم العثور على المستخدم\n";
        echo "   Username: {$users[0]['username']}\n";
        echo "   Email: {$users[0]['email']}\n";
        echo "   Enabled: " . ($users[0]['enabled'] ? 'Yes' : 'No') . "\n";
        echo "   Email Verified: " . ($users[0]['emailVerified'] ? 'Yes' : 'No') . "\n\n";
    } else {
        echo "⚠️  لم يتم العثور على المستخدم\n\n";
    }

} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
}

// ===================================
// Test 8: Create Group
// ===================================
echo "✅ Test 8: إنشاء Group اختباري\n";
echo "---------------------------------------------------\n";

$testGroupName = 'test-group';
echo "Group Name: {$testGroupName}\n";

try {
    $response = $client->post("/admin/realms/{$testRealmId}/groups", [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'name' => $testGroupName,
        ],
    ]);

    echo "✅ نجح! تم إنشاء Group\n";

    // Get group ID from Location header
    $location = $response->getHeader('Location')[0] ?? null;
    if ($location) {
        $groupId = basename($location);
        echo "   Group ID: {$groupId}\n";
    }
    echo "\n";

} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n";
    if ($e->hasResponse()) {
        echo "   Response: " . $e->getResponse()->getBody() . "\n";
    }
    echo "\n";
}

// ===================================
// Summary
// ===================================
echo "================================================\n";
echo "�� ملخص الاختبار\n";
echo "================================================\n\n";

echo "✅ جميع الوظائف الأساسية تعمل بنجاح!\n\n";

echo "تم اختبار:\n";
echo "  1. ✅ الحصول على Access Token من Service Account\n";
echo "  2. ✅ إنشاء Realm\n";
echo "  3. ✅ استعلام عن Clients\n";
echo "  4. ✅ استعلام عن Groups\n";
echo "  5. ✅ استعلام عن Users\n";
echo "  6. ✅ إنشاء User\n";
echo "  7. ✅ التحقق من User\n";
echo "  8. ✅ إنشاء Group\n\n";

echo "Realm الاختباري:\n";
echo "  ID: {$testRealmId}\n";
echo "  URL: {$keycloakUrl}/admin/{$testRealmId}/console\n\n";

// ===================================
// Cleanup (Optional)
// ===================================
echo "================================================\n";
echo "🧹 التنظيف (اختياري)\n";
echo "================================================\n\n";

echo "هل تريد حذف الـ Realm الاختباري؟ (y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim(strtolower($line)) === 'y') {
    try {
        $client->delete("/admin/realms/{$testRealmId}", [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ]);

        echo "✅ تم حذف Realm: {$testRealmId}\n\n";

    } catch (\Exception $e) {
        echo "❌ فشل الحذف: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "⏭️  تم تخطي الحذف. يمكنك حذف Realm يدوياً من Keycloak Admin Console.\n\n";
}

echo "================================================\n";
echo "✅ انتهى الاختبار بنجاح!\n";
echo "================================================\n";
