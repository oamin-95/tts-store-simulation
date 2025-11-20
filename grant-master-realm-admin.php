<?php

/**
 * منح Service Account صلاحية realm-admin من master-realm client
 * هذا هو الحل الصحيح!
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

echo "🔧 منح Service Account صلاحية realm-admin من master-realm\n";
echo "=========================================================\n\n";

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

// Get service account user ID
echo "Step 2: الحصول على Service Account User ID...\n";
$response = $client->get('/admin/realms/master/users', [
    'headers' => ['Authorization' => 'Bearer ' . $adminToken],
    'query' => ['username' => 'service-account-saas-marketplace-admin'],
]);

$users = json_decode($response->getBody()->getContents(), true);
$serviceAccountUserId = $users[0]['id'];
echo "✅ User ID: {$serviceAccountUserId}\n\n";

// Get master-realm client ID (هذا هو الصحيح!)
echo "Step 3: الحصول على master-realm client...\n";
$response = $client->get('/admin/realms/master/clients', [
    'headers' => ['Authorization' => 'Bearer ' . $adminToken],
    'query' => ['clientId' => 'master-realm'],
]);

$clients = json_decode($response->getBody()->getContents(), true);
if (empty($clients)) {
    echo "❌ master-realm client لم يتم العثور عليه!\n";
    echo "   المتاح:\n";

    // List all clients
    $response = $client->get('/admin/realms/master/clients', [
        'headers' => ['Authorization' => 'Bearer ' . $adminToken],
    ]);
    $allClients = json_decode($response->getBody()->getContents(), true);
    foreach ($allClients as $c) {
        echo "   - {$c['clientId']}\n";
    }
    exit(1);
}

$masterRealmClientId = $clients[0]['id'];
echo "✅ Client ID: {$masterRealmClientId}\n\n";

// Get realm-admin role from master-realm client
echo "Step 4: الحصول على realm-admin role من master-realm...\n";
$response = $client->get("/admin/realms/master/clients/{$masterRealmClientId}/roles", [
    'headers' => ['Authorization' => 'Bearer ' . $adminToken],
]);

$roles = json_decode($response->getBody()->getContents(), true);

echo "الأدوار المتاحة في master-realm:\n";
foreach ($roles as $role) {
    echo "  - {$role['name']}\n";
}
echo "\n";

$realmAdminRole = null;
foreach ($roles as $role) {
    if ($role['name'] === 'realm-admin') {
        $realmAdminRole = $role;
        break;
    }
}

if (!$realmAdminRole) {
    echo "❌ realm-admin role غير موجود في master-realm!\n";
    exit(1);
}
echo "✅ realm-admin role موجود\n\n";

// Check current mappings
echo "Step 5: فحص الصلاحيات الحالية...\n";
$response = $client->get("/admin/realms/master/users/{$serviceAccountUserId}/role-mappings/clients/{$masterRealmClientId}", [
    'headers' => ['Authorization' => 'Bearer ' . $adminToken],
]);

$currentRoles = json_decode($response->getBody()->getContents(), true);
$hasRealmAdmin = false;

foreach ($currentRoles as $role) {
    if ($role['name'] === 'realm-admin') {
        $hasRealmAdmin = true;
        break;
    }
}

if ($hasRealmAdmin) {
    echo "✅ realm-admin موجود بالفعل!\n\n";
} else {
    echo "⚠️  realm-admin غير موجود، سأقوم بإضافته...\n";

    try {
        $response = $client->post(
            "/admin/realms/master/users/{$serviceAccountUserId}/role-mappings/clients/{$masterRealmClientId}",
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

echo "=========================================================\n";
echo "✅ اكتمل!\n";
echo "=========================================================\n\n";
echo "الآن قم بإعادة تشغيل test-service-account.php\n";
