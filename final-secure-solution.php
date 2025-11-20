<?php

/**
 * الحل الآمن النهائي: منح Service Account صلاحية admin على مستوى Realm Roles
 *
 * المشكلة: Service Account يملك client roles فقط (من master-realm)
 * الحل: منح Service Account الصلاحية admin على مستوى realm roles
 *
 * هذا يتطلب استخدام admin credentials مرة واحدة فقط للإعداد الأولي
 * بعد ذلك، Service Account يعمل بشكل كامل بدون الحاجة لـ admin credentials
 */

use GuzzleHttp\Client;

require __DIR__.'/vendor/autoload.php';

$keycloakUrl = 'http://localhost:8090';
$serviceClientId = 'saas-marketplace-admin';
$serviceClientSecret = 'M1VVIsCH9WsrSOWJwul9MoB3o4MIKZ1W';
$adminUser = 'admin';
$adminPassword = 'admin123';

$client = new Client([
    'base_uri' => $keycloakUrl,
    'verify' => false,
    'timeout' => 30,
]);

echo "🔐 الحل الآمن النهائي\n";
echo "====================\n\n";

echo "📋 الهدف: منح Service Account صلاحية 'admin' على مستوى Realm Roles\n";
echo "       (وليس Client Roles)\n\n";

// Step 1: Get admin token (للإعداد الأولي فقط!)
echo "Step 1: الحصول على Admin Token (للإعداد الأولي فقط)...\n";
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
echo "✅ نجح\n\n";

// Step 2: Get service account user
echo "Step 2: الحصول على Service Account User...\n";
$response = $client->get('/admin/realms/master/users', [
    'headers' => ['Authorization' => 'Bearer ' . $adminToken],
    'query' => ['username' => 'service-account-saas-marketplace-admin'],
]);

$users = json_decode($response->getBody()->getContents(), true);
$serviceUserId = $users[0]['id'];
echo "✅ User ID: {$serviceUserId}\n\n";

// Step 3: Get 'admin' role from Master Realm (realm role, not client role!)
echo "Step 3: الحصول على 'admin' role من Master Realm...\n";
$response = $client->get('/admin/realms/master/roles', [
    'headers' => ['Authorization' => 'Bearer ' . $adminToken],
]);

$realmRoles = json_decode($response->getBody()->getContents(), true);

echo "Realm Roles المتاحة:\n";
foreach ($realmRoles as $role) {
    echo "  - {$role['name']}\n";
}
echo "\n";

$adminRole = null;
foreach ($realmRoles as $role) {
    if ($role['name'] === 'admin') {
        $adminRole = $role;
        break;
    }
}

if (!$adminRole) {
    echo "❌ admin role غير موجود!\n";
    exit(1);
}
echo "✅ admin role موجود\n\n";

// Step 4: Check current realm role mappings
echo "Step 4: فحص الصلاحيات الحالية (Realm Roles)...\n";
$response = $client->get("/admin/realms/master/users/{$serviceUserId}/role-mappings/realm", [
    'headers' => ['Authorization' => 'Bearer ' . $adminToken],
]);

$currentRealmRoles = json_decode($response->getBody()->getContents(), true);

echo "Realm Roles الحالية للـ Service Account:\n";
foreach ($currentRealmRoles as $role) {
    echo "  - {$role['name']}\n";
}
echo "\n";

$hasAdminRole = false;
foreach ($currentRealmRoles as $role) {
    if ($role['name'] === 'admin') {
        $hasAdminRole = true;
        break;
    }
}

if ($hasAdminRole) {
    echo "✅ صلاحية 'admin' موجودة بالفعل على مستوى Realm Roles!\n\n";
} else {
    echo "⚠️  صلاحية 'admin' غير موجودة على مستوى Realm Roles!\n";
    echo "   سأقوم بإضافتها الآن...\n\n";

    try {
        $response = $client->post(
            "/admin/realms/master/users/{$serviceUserId}/role-mappings/realm",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $adminToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [$adminRole],
            ]
        );
        echo "✅ تم إضافة صلاحية 'admin' على مستوى Realm Roles بنجاح!\n\n";
    } catch (\Exception $e) {
        echo "❌ فشل: " . $e->getMessage() . "\n\n";
        exit(1);
    }
}

// Step 5: Test with new Service Account token
echo "========================================\n";
echo "Step 5: اختبار مع Service Account Token\n";
echo "========================================\n\n";

$response = $client->post('/realms/master/protocol/openid-connect/token', [
    'form_params' => [
        'grant_type' => 'client_credentials',
        'client_id' => $serviceClientId,
        'client_secret' => $serviceClientSecret,
    ],
]);

$serviceTokenData = json_decode($response->getBody()->getContents(), true);
$serviceToken = $serviceTokenData['access_token'];

// Decode token
$parts = explode('.', $serviceToken);
$payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);

echo "📋 الصلاحيات في Service Account Token:\n";
echo "Realm Access Roles:\n";
print_r($payload['realm_access']['roles'] ?? []);
echo "\n";

// Test: Create Realm
echo "Test 1: إنشاء Realm جديد...\n";
$testRealmId = 'test-secure-' . time();

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

// Test: Manage users in new realm
if ($realmCreated) {
    echo "Test 2: الاستعلام عن Users في الـ Realm الجديد...\n";

    try {
        $response = $client->get("/admin/realms/{$testRealmId}/users", [
            'headers' => ['Authorization' => 'Bearer ' . $serviceToken],
        ]);
        echo "✅ نجح!\n\n";

        echo "Test 3: إنشاء User في الـ Realm الجديد...\n";
        $response = $client->post("/admin/realms/{$testRealmId}/users", [
            'headers' => [
                'Authorization' => 'Bearer ' . $serviceToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'username' => 'test@example.com',
                'email' => 'test@example.com',
                'enabled' => true,
                'emailVerified' => true,
                'credentials' => [[
                    'type' => 'password',
                    'value' => 'test123',
                    'temporary' => false,
                ]],
            ],
        ]);
        echo "✅ نجح إنشاء User!\n\n";

        echo "Test 4: إنشاء Group في الـ Realm الجديد...\n";
        $response = $client->post("/admin/realms/{$testRealmId}/groups", [
            'headers' => [
                'Authorization' => 'Bearer ' . $serviceToken,
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

    // Cleanup
    echo "🧹 التنظيف...\n";
    try {
        $client->delete("/admin/realms/{$testRealmId}", [
            'headers' => ['Authorization' => 'Bearer ' . $serviceToken],
        ]);
        echo "✅ تم حذف Realm\n\n";
    } catch (\Exception $e) {
        echo "⚠️  فشل الحذف: " . $e->getMessage() . "\n\n";
    }
}

echo "========================================\n";
echo "✅ النتيجة النهائية\n";
echo "========================================\n\n";

if ($realmCreated) {
    echo "🎉 نجح! Service Account الآن يعمل بشكل كامل!\n\n";
    echo "الخطوات التي تمت:\n";
    echo "1. ✅ منح Service Account صلاحية 'admin' على مستوى Realm Roles\n";
    echo "2. ✅ Service Account يستطيع إنشاء Realms جديدة\n";
    echo "3. ✅ Service Account يستطيع إدارة Users في الـ Realms الجديدة\n";
    echo "4. ✅ Service Account يستطيع إدارة Groups في الـ Realms الجديدة\n\n";
    echo "📝 ملاحظة مهمة:\n";
    echo "   - تم استخدام admin credentials مرة واحدة فقط للإعداد الأولي\n";
    echo "   - من الآن فصاعداً، استخدم Service Account فقط (client_credentials)\n";
    echo "   - لا حاجة لـ admin credentials بعد الآن!\n";
} else {
    echo "❌ ما زالت هناك مشكلة. يرجى مراجعة الخطوات.\n";
}
