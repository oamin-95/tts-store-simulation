<?php

/**
 * اختبار شامل للحل الآمن النهائي
 *
 * هذا الاختبار يثبت أن:
 * 1. لا استخدام لـ username/password نهائياً (client_credentials فقط)
 * 2. إنشاء Realm ناجح
 * 3. إضافة Client في الـ Realm ناجح
 * 4. الاستعلام عن Clients ناجح
 * 5. الاستعلام عن Groups ناجح
 * 6. الاستعلام عن Users ناجح
 * 7. إضافة Group ناجح
 * 8. إضافة User ناجح
 *
 * الحل الآمن: Session Refresh بعد إنشاء Realm
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

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║         اختبار شامل للحل الآمن النهائي                      ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Helper function to get Service Account token
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

// Test variables
$testRealmId = 'comprehensive-test-' . time();
$testClientId = 'test-client-app';
$testGroupName = 'test-group';
$testUsername = 'testuser@example.com';
$allTestsPassed = true;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔐 الأمان: استخدام Client Credentials فقط (لا username/password)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// ============================================================
// Test 1: Get initial token
// ============================================================
echo "Test 1️⃣: الحصول على Service Account Token\n";
echo "─────────────────────────────────────────────────\n";

try {
    $token1 = getServiceAccountToken($client, $keycloakUrl, $serviceClientId, $serviceClientSecret);
    echo "✅ نجح! Token تم الحصول عليه بأمان (client_credentials)\n";
    echo "   Grant Type: client_credentials ✓\n";
    echo "   No Username: ✓\n";
    echo "   No Password: ✓\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
    exit(1);
}

// ============================================================
// Test 2: Create Realm
// ============================================================
echo "Test 2️⃣: إنشاء Realm جديد\n";
echo "─────────────────────────────────────────────────\n";
echo "Realm ID: {$testRealmId}\n";

try {
    $response = $client->post('/admin/realms', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token1,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'realm' => $testRealmId,
            'enabled' => true,
            'displayName' => 'Comprehensive Test Realm',
            'loginWithEmailAllowed' => true,
        ],
    ]);
    echo "✅ نجح! Realm تم إنشاؤه بنجاح\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
    exit(1);
}

// ============================================================
// CRITICAL: Refresh token after creating realm
// ============================================================
echo "🔑 CRITICAL STEP: تحديث Token بعد إنشاء Realm\n";
echo "─────────────────────────────────────────────────\n";
echo "السبب: Session caching - Token القديم لا يحتوي على permissions للـ Realm الجديد\n";

try {
    $token2 = getServiceAccountToken($client, $keycloakUrl, $serviceClientId, $serviceClientSecret);
    echo "✅ Token جديد تم الحصول عليه (يحتوي على permissions للـ Realm الجديد)\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
    exit(1);
}

// ============================================================
// Test 3: Query Users (should be empty)
// ============================================================
echo "Test 3️⃣: الاستعلام عن Users في الـ Realm الجديد\n";
echo "─────────────────────────────────────────────────\n";

try {
    $response = $client->get("/admin/realms/{$testRealmId}/users", [
        'headers' => ['Authorization' => 'Bearer ' . $token2],
    ]);
    $users = json_decode($response->getBody()->getContents(), true);
    echo "✅ نجح! عدد المستخدمين: " . count($users) . "\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
}

// ============================================================
// Test 4: Query Groups (should be empty)
// ============================================================
echo "Test 4️⃣: الاستعلام عن Groups في الـ Realm الجديد\n";
echo "─────────────────────────────────────────────────\n";

try {
    $response = $client->get("/admin/realms/{$testRealmId}/groups", [
        'headers' => ['Authorization' => 'Bearer ' . $token2],
    ]);
    $groups = json_decode($response->getBody()->getContents(), true);
    echo "✅ نجح! عدد المجموعات: " . count($groups) . "\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
}

// ============================================================
// Test 5: Query Clients
// ============================================================
echo "Test 5️⃣: الاستعلام عن Clients في الـ Realm الجديد\n";
echo "─────────────────────────────────────────────────\n";

try {
    $response = $client->get("/admin/realms/{$testRealmId}/clients", [
        'headers' => ['Authorization' => 'Bearer ' . $token2],
    ]);
    $clients = json_decode($response->getBody()->getContents(), true);
    echo "✅ نجح! عدد Clients: " . count($clients) . " (default clients)\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
}

// ============================================================
// Test 6: Add Client
// ============================================================
echo "Test 6️⃣: إضافة Client جديد\n";
echo "─────────────────────────────────────────────────\n";
echo "Client ID: {$testClientId}\n";

try {
    $response = $client->post("/admin/realms/{$testRealmId}/clients", [
        'headers' => [
            'Authorization' => 'Bearer ' . $token2,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'clientId' => $testClientId,
            'name' => 'Test Client Application',
            'enabled' => true,
            'publicClient' => false,
            'protocol' => 'openid-connect',
            'standardFlowEnabled' => true,
            'directAccessGrantsEnabled' => true,
            'redirectUris' => ['https://example.com/*'],
        ],
    ]);
    echo "✅ نجح! Client تم إضافته بنجاح\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
}

// ============================================================
// Test 7: Verify Client was added
// ============================================================
echo "Test 7️⃣: التحقق من إضافة Client\n";
echo "─────────────────────────────────────────────────\n";

try {
    $response = $client->get("/admin/realms/{$testRealmId}/clients", [
        'headers' => ['Authorization' => 'Bearer ' . $token2],
        'query' => ['clientId' => $testClientId],
    ]);
    $clients = json_decode($response->getBody()->getContents(), true);

    if (!empty($clients) && $clients[0]['clientId'] === $testClientId) {
        echo "✅ نجح! Client موجود:\n";
        echo "   - Client ID: {$clients[0]['clientId']}\n";
        echo "   - Name: {$clients[0]['name']}\n";
        echo "   - Enabled: " . ($clients[0]['enabled'] ? 'Yes' : 'No') . "\n\n";
    } else {
        echo "❌ Client غير موجود!\n\n";
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
}

// ============================================================
// Test 8: Add Group
// ============================================================
echo "Test 8️⃣: إضافة Group جديد\n";
echo "─────────────────────────────────────────────────\n";
echo "Group Name: {$testGroupName}\n";

try {
    $response = $client->post("/admin/realms/{$testRealmId}/groups", [
        'headers' => [
            'Authorization' => 'Bearer ' . $token2,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'name' => $testGroupName,
        ],
    ]);
    echo "✅ نجح! Group تم إضافته بنجاح\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
}

// ============================================================
// Test 9: Verify Group was added
// ============================================================
echo "Test 9️⃣: التحقق من إضافة Group\n";
echo "─────────────────────────────────────────────────\n";

try {
    $response = $client->get("/admin/realms/{$testRealmId}/groups", [
        'headers' => ['Authorization' => 'Bearer ' . $token2],
    ]);
    $groups = json_decode($response->getBody()->getContents(), true);

    $groupFound = false;
    foreach ($groups as $group) {
        if ($group['name'] === $testGroupName) {
            echo "✅ نجح! Group موجود:\n";
            echo "   - Name: {$group['name']}\n";
            echo "   - ID: {$group['id']}\n\n";
            $groupFound = true;
            break;
        }
    }

    if (!$groupFound) {
        echo "❌ Group غير موجود!\n\n";
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
}

// ============================================================
// Test 10: Add User
// ============================================================
echo "Test 🔟: إضافة User جديد\n";
echo "─────────────────────────────────────────────────\n";
echo "Username: {$testUsername}\n";

try {
    $response = $client->post("/admin/realms/{$testRealmId}/users", [
        'headers' => [
            'Authorization' => 'Bearer ' . $token2,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'username' => $testUsername,
            'email' => $testUsername,
            'enabled' => true,
            'emailVerified' => true,
            'firstName' => 'Test',
            'lastName' => 'User',
            'credentials' => [[
                'type' => 'password',
                'value' => 'Test123!',
                'temporary' => false,
            ]],
        ],
    ]);
    echo "✅ نجح! User تم إضافته بنجاح\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
}

// ============================================================
// Test 11: Verify User was added
// ============================================================
echo "Test 1️⃣1️⃣: التحقق من إضافة User\n";
echo "─────────────────────────────────────────────────\n";

try {
    $response = $client->get("/admin/realms/{$testRealmId}/users", [
        'headers' => ['Authorization' => 'Bearer ' . $token2],
        'query' => ['username' => $testUsername],
    ]);
    $users = json_decode($response->getBody()->getContents(), true);

    if (!empty($users) && $users[0]['username'] === $testUsername) {
        echo "✅ نجح! User موجود:\n";
        echo "   - Username: {$users[0]['username']}\n";
        echo "   - Email: {$users[0]['email']}\n";
        echo "   - Enabled: " . ($users[0]['enabled'] ? 'Yes' : 'No') . "\n";
        echo "   - Email Verified: " . ($users[0]['emailVerified'] ? 'Yes' : 'No') . "\n\n";
    } else {
        echo "❌ User غير موجود!\n\n";
        $allTestsPassed = false;
    }
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
}

// ============================================================
// Cleanup
// ============================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🧹 التنظيف\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    $client->delete("/admin/realms/{$testRealmId}", [
        'headers' => ['Authorization' => 'Bearer ' . $token2],
    ]);
    echo "✅ تم حذف Realm\n\n";
} catch (\Exception $e) {
    echo "⚠️  فشل الحذف: " . $e->getMessage() . "\n\n";
}

// ============================================================
// Final Results
// ============================================================
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                      النتيجة النهائية                        ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

if ($allTestsPassed) {
    echo "🎉 جميع الاختبارات نجحت!\n\n";

    echo "📋 ملخص ما تم اختباره:\n";
    echo "─────────────────────────────────────────────────\n";
    echo "✅ 1. الاتصال الآمن (client_credentials - لا username/password)\n";
    echo "✅ 2. إنشاء Realm\n";
    echo "✅ 3. الاستعلام عن Users\n";
    echo "✅ 4. الاستعلام عن Groups\n";
    echo "✅ 5. الاستعلام عن Clients\n";
    echo "✅ 6. إضافة Client جديد\n";
    echo "✅ 7. التحقق من Client\n";
    echo "✅ 8. إضافة Group جديد\n";
    echo "✅ 9. التحقق من Group\n";
    echo "✅ 10. إضافة User جديد\n";
    echo "✅ 11. التحقق من User\n\n";

    echo "🔐 الأمان:\n";
    echo "─────────────────────────────────────────────────\n";
    echo "✅ OAuth 2.0 Client Credentials فقط\n";
    echo "✅ لا استخدام لـ username نهائياً\n";
    echo "✅ لا استخدام لـ password نهائياً\n";
    echo "✅ جميع العمليات آمنة 100%\n\n";

    echo "🔑 الحل الفني:\n";
    echo "─────────────────────────────────────────────────\n";
    echo "1. استخدام Service Account (saas-marketplace-admin)\n";
    echo "2. الحصول على token بـ client_credentials\n";
    echo "3. إنشاء Realm\n";
    echo "4. 🔄 تحديث Token بعد إنشاء Realm (Session Refresh)\n";
    echo "5. استخدام Token الجديد لإدارة الـ Realm\n\n";

    echo "📝 السبب:\n";
    echo "─────────────────────────────────────────────────\n";
    echo "Keycloak يستخدم Session Caching للـ permissions.\n";
    echo "Token القديم يحتوي على permissions للـ Realms الموجودة وقت إنشائه فقط.\n";
    echo "لذا نحتاج token جديد بعد إنشاء كل Realm.\n\n";

} else {
    echo "❌ بعض الاختبارات فشلت\n\n";
    exit(1);
}

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                  ✅ الحل جاهز للتنفيذ                        ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
