<?php

/**
 * فحص الصلاحيات الدنيا المطلوبة للـ Service Account
 *
 * الهدف: معرفة الصلاحيات المحددة التي يحتاجها Service Account
 * بدلاً من صلاحيات admin الكاملة
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

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║         فحص الصلاحيات الحالية للـ Service Account            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// Get admin token
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

// Get service account user
echo "Step 2: الحصول على Service Account User...\n";
$response = $client->get('/admin/realms/master/users', [
    'headers' => ['Authorization' => 'Bearer ' . $adminToken],
    'query' => ['username' => 'service-account-saas-marketplace-admin'],
]);

$users = json_decode($response->getBody()->getContents(), true);
$serviceAccountUserId = $users[0]['id'];
echo "✅ User ID: {$serviceAccountUserId}\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "الصلاحيات الحالية\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Check Realm Roles
echo "1️⃣ Realm Roles (على مستوى Master Realm):\n";
echo "─────────────────────────────────────────────────\n";
$response = $client->get("/admin/realms/master/users/{$serviceAccountUserId}/role-mappings/realm", [
    'headers' => ['Authorization' => 'Bearer ' . $adminToken],
]);

$realmRoles = json_decode($response->getBody()->getContents(), true);
if (empty($realmRoles)) {
    echo "   لا توجد realm roles\n\n";
} else {
    foreach ($realmRoles as $role) {
        echo "   ✓ {$role['name']}\n";
    }
    echo "\n";
}

// Check master-realm client roles
echo "2️⃣ master-realm Client Roles:\n";
echo "─────────────────────────────────────────────────\n";

// Get master-realm client ID
$response = $client->get('/admin/realms/master/clients', [
    'headers' => ['Authorization' => 'Bearer ' . $adminToken],
    'query' => ['clientId' => 'master-realm'],
]);

$clients = json_decode($response->getBody()->getContents(), true);
if (!empty($clients)) {
    $masterRealmClientId = $clients[0]['id'];

    $response = $client->get("/admin/realms/master/users/{$serviceAccountUserId}/role-mappings/clients/{$masterRealmClientId}", [
        'headers' => ['Authorization' => 'Bearer ' . $adminToken],
    ]);

    $masterRealmRoles = json_decode($response->getBody()->getContents(), true);
    if (empty($masterRealmRoles)) {
        echo "   لا توجد master-realm roles\n\n";
    } else {
        foreach ($masterRealmRoles as $role) {
            echo "   ✓ {$role['name']}\n";
        }
        echo "\n";
    }
} else {
    echo "   ❌ master-realm client غير موجود!\n\n";
}

// Check all client role mappings
echo "3️⃣ جميع Client Role Mappings:\n";
echo "─────────────────────────────────────────────────\n";

$response = $client->get("/admin/realms/master/users/{$serviceAccountUserId}/role-mappings", [
    'headers' => ['Authorization' => 'Bearer ' . $adminToken],
]);

$allMappings = json_decode($response->getBody()->getContents(), true);

if (isset($allMappings['clientMappings']) && !empty($allMappings['clientMappings'])) {
    foreach ($allMappings['clientMappings'] as $clientId => $mapping) {
        echo "\n   Client: {$clientId}\n";
        foreach ($mapping['mappings'] as $role) {
            echo "     ✓ {$role['name']}\n";
        }
    }
    echo "\n";
} else {
    echo "   لا توجد client mappings\n\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "الصلاحيات الدنيا المطلوبة لتطبيق المتجر\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "بناءً على احتياجات المتجر، الصلاحيات المطلوبة هي:\n\n";

echo "📋 Realm Roles (Master Realm):\n";
echo "─────────────────────────────────────────────────\n";
echo "   ✓ create-realm         (لإنشاء Realms جديدة)\n";
echo "   ✓ admin               (لإدارة كل Realm بعد إنشائه)\n\n";

echo "📋 master-realm Client Roles:\n";
echo "─────────────────────────────────────────────────\n";
echo "   ✓ manage-users        (لإدارة المستخدمين في Realms)\n";
echo "   ✓ view-users          (للاستعلام عن المستخدمين)\n";
echo "   ✓ manage-clients      (لإدارة Clients في Realms)\n";
echo "   ✓ view-clients        (للاستعلام عن Clients)\n";
echo "   ✓ manage-realm        (لإدارة إعدادات الـ Realm)\n";
echo "   ✓ view-realm          (للاستعلام عن معلومات الـ Realm)\n\n";

echo "💡 ملاحظات:\n";
echo "─────────────────────────────────────────────────\n";
echo "1. الصلاحية 'admin' على مستوى Realm Roles هي الأهم\n";
echo "   لأنها تعطي صلاحيات كاملة على جميع Realms\n\n";
echo "2. صلاحيات master-realm تعطي صلاحيات تفصيلية\n";
echo "   لكن 'admin' realm role تغني عنها\n\n";
echo "3. بدون 'admin' realm role، نحتاج جميع الصلاحيات التفصيلية\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "الصلاحيات الموصى بها (Minimal & Secure)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "الخيار الأول (الأبسط):\n";
echo "─────────────────────────────────────────────────\n";
echo "Realm Roles:\n";
echo "   • create-realm\n";
echo "   • admin\n\n";

echo "الخيار الثاني (أكثر أماناً - Fine-Grained):\n";
echo "─────────────────────────────────────────────────\n";
echo "Realm Roles:\n";
echo "   • create-realm\n\n";
echo "master-realm Client Roles:\n";
echo "   • create-client\n";
echo "   • manage-clients\n";
echo "   • manage-users\n";
echo "   • manage-realm\n";
echo "   • view-clients\n";
echo "   • view-users\n";
echo "   • view-realm\n";
echo "   • query-users\n";
echo "   • query-clients\n";
echo "   • query-realms\n\n";

echo "🎯 التوصية:\n";
echo "─────────────────────────────────────────────────\n";
echo "للتطبيق الحالي، الخيار الأول (create-realm + admin)\n";
echo "هو الأنسب لأن:\n";
echo "  • بسيط وسهل الإدارة\n";
echo "  • يعمل مع Session Refresh بشكل مثالي\n";
echo "  • يعطي صلاحيات كاملة على الـ Realms المُنشأة\n\n";

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                  ✅ الفحص مكتمل                              ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
