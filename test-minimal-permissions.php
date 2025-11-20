<?php

/**
 * اختبار شامل مع الصلاحيات الدنيا المحددة (بدون admin)
 *
 * Service Account الجديد يحتوي على:
 * - Realm Role: create-realm
 * - master-realm Client Roles: manage-users, view-users, query-users,
 *   manage-clients, view-clients, manage-realm, view-realm,
 *   query-groups, manage-authorization
 *
 * بدون صلاحية admin الشاملة!
 */

use GuzzleHttp\Client;

require __DIR__.'/vendor/autoload.php';

$keycloakUrl = 'http://localhost:8090';
$serviceClientId = 'saas-marketplace-admin';
$serviceClientSecret = 'NkOMJJrTWDLWx095pm4HYxFuCI6zcjhf';

$client = new Client([
    'base_uri' => $keycloakUrl,
    'verify' => false,
    'timeout' => 30,
]);

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║    اختبار Service Account مع الصلاحيات المحددة فقط           ║\n";
echo "║              (بدون admin - Fine-Grained)                    ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Helper function
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

$testRealmId = 'fine-grained-test-' . time();
$allTestsPassed = true;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🔐 الأمان: Client Credentials + صلاحيات محددة فقط\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Test 1: Get initial token
echo "Test 1️⃣: الحصول على Service Account Token\n";
echo "─────────────────────────────────────────────────\n";

try {
    $token1 = getServiceAccountToken($client, $keycloakUrl, $serviceClientId, $serviceClientSecret);

    // Decode token to check permissions
    $parts = explode('.', $token1);
    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);

    echo "✅ نجح! Token تم الحصول عليه\n";
    echo "   Grant Type: client_credentials ✓\n\n";

    echo "📋 الصلاحيات في Token:\n";
    echo "   Realm Roles:\n";
    foreach ($payload['realm_access']['roles'] ?? [] as $role) {
        echo "     • {$role}\n";
    }

    echo "\n   master-realm Client Roles:\n";
    foreach ($payload['resource_access']['master-realm']['roles'] ?? [] as $role) {
        echo "     • {$role}\n";
    }
    echo "\n";

    // Check if admin role exists (should NOT exist)
    $hasAdminRole = in_array('admin', $payload['realm_access']['roles'] ?? []);
    if ($hasAdminRole) {
        echo "⚠️  تحذير: صلاحية admin موجودة!\n\n";
    } else {
        echo "✅ جيد: لا توجد صلاحية admin شاملة (Fine-Grained)\n\n";
    }

} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
    exit(1);
}

// Test 2: Create Realm
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
            'displayName' => 'Fine-Grained Permissions Test',
        ],
    ]);
    echo "✅ نجح! Realm تم إنشاؤه\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
    exit(1);
}

// CRITICAL: Session Refresh
echo "🔑 CRITICAL: Session Refresh - الحصول على Token جديد\n";
echo "─────────────────────────────────────────────────\n";

try {
    $token2 = getServiceAccountToken($client, $keycloakUrl, $serviceClientId, $serviceClientSecret);
    echo "✅ Token جديد تم الحصول عليه\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
    exit(1);
}

// Test 3: Query Users
echo "Test 3️⃣: الاستعلام عن Users (باستخدام Token الجديد)\n";
echo "─────────────────────────────────────────────────\n";

try {
    $response = $client->get("/admin/realms/{$testRealmId}/users", [
        'headers' => ['Authorization' => 'Bearer ' . $token2],
    ]);
    $users = json_decode($response->getBody()->getContents(), true);
    echo "✅ نجح! عدد المستخدمين: " . count($users) . "\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    echo "   السبب المحتمل: الصلاحيات التفصيلية لا تعمل على Realms جديدة\n\n";
    $allTestsPassed = false;
}

// Test 4: Create User
echo "Test 4️⃣: إنشاء User جديد\n";
echo "─────────────────────────────────────────────────\n";

try {
    $response = $client->post("/admin/realms/{$testRealmId}/users", [
        'headers' => [
            'Authorization' => 'Bearer ' . $token2,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'username' => 'testuser@example.com',
            'email' => 'testuser@example.com',
            'enabled' => true,
            'credentials' => [[
                'type' => 'password',
                'value' => 'Test123!',
                'temporary' => false,
            ]],
        ],
    ]);
    echo "✅ نجح! User تم إنشاؤه\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
}

// Test 5: Query Groups
echo "Test 5️⃣: الاستعلام عن Groups\n";
echo "─────────────────────────────────────────────────\n";

try {
    $response = $client->get("/admin/realms/{$testRealmId}/groups", [
        'headers' => ['Authorization' => 'Bearer ' . $token2],
    ]);
    $groups = json_decode($response->getBody()->getContents(), true);
    echo "✅ نجح! عدد Groups: " . count($groups) . "\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
}

// Test 6: Create Client
echo "Test 6️⃣: إنشاء Client جديد\n";
echo "─────────────────────────────────────────────────\n";

try {
    $response = $client->post("/admin/realms/{$testRealmId}/clients", [
        'headers' => [
            'Authorization' => 'Bearer ' . $token2,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'clientId' => 'test-client',
            'enabled' => true,
            'protocol' => 'openid-connect',
        ],
    ]);
    echo "✅ نجح! Client تم إنشاؤه\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
}

// Test 7: Query Clients
echo "Test 7️⃣: الاستعلام عن Clients\n";
echo "─────────────────────────────────────────────────\n";

try {
    $response = $client->get("/admin/realms/{$testRealmId}/clients", [
        'headers' => ['Authorization' => 'Bearer ' . $token2],
    ]);
    $clients = json_decode($response->getBody()->getContents(), true);
    echo "✅ نجح! عدد Clients: " . count($clients) . "\n\n";
} catch (\Exception $e) {
    echo "❌ فشل: " . $e->getMessage() . "\n\n";
    $allTestsPassed = false;
}

// Cleanup
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

// Final Results
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                      النتيجة النهائية                        ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

if ($allTestsPassed) {
    echo "🎉 نجح! الصلاحيات التفصيلية تعمل بنجاح!\n\n";

    echo "✅ الحل الآمن مع العزل:\n";
    echo "─────────────────────────────────────────────────\n";
    echo "1. Service Account بصلاحيات محددة فقط (بدون admin)\n";
    echo "2. Session Refresh بعد إنشاء كل Realm\n";
    echo "3. جميع العمليات نجحت بدون 403 Forbidden\n\n";

    echo "🔐 الأمان:\n";
    echo "─────────────────────────────────────────────────\n";
    echo "✅ لا صلاحية admin شاملة\n";
    echo "✅ صلاحيات محددة على master-realm فقط\n";
    echo "✅ OAuth 2.0 Client Credentials فقط\n";
    echo "✅ لا username/password\n\n";

    echo "📋 الصلاحيات المستخدمة:\n";
    echo "─────────────────────────────────────────────────\n";
    echo "Realm Roles:\n";
    echo "  • create-realm\n\n";
    echo "master-realm Client Roles:\n";
    echo "  • manage-users\n";
    echo "  • view-users\n";
    echo "  • query-users\n";
    echo "  • manage-clients\n";
    echo "  • view-clients\n";
    echo "  • manage-realm\n";
    echo "  • view-realm\n";
    echo "  • query-groups\n";
    echo "  • manage-authorization\n\n";

} else {
    echo "❌ فشلت بعض الاختبارات\n\n";
    echo "💡 إذا فشلت الاختبارات:\n";
    echo "   قد نحتاج إلى صلاحيات إضافية أو حل بديل\n\n";
}

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║            اختبار الصلاحيات التفصيلية مكتمل                  ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
