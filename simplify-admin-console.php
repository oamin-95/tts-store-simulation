<?php

/**
 * تبسيط إعدادات Admin Console - إزالة PKCE
 */

require __DIR__.'/vendor/autoload.php';

use GuzzleHttp\Client;

$realmId = $argv[1] ?? 'tenant-32';

echo "\n════════════════════════════════════\n";
echo "🔧 تبسيط إعدادات Admin Console\n";
echo "════════════════════════════════════\n\n";

$keycloakUrl = 'http://localhost:8090';
$client = new Client([
    'base_uri' => $keycloakUrl,
    'verify' => false,
    'timeout' => 30,
]);

try {
    // Get admin token
    echo "🔐 الحصول على Token...\n";
    $tokenResponse = $client->post('/realms/master/protocol/openid-connect/token', [
        'form_params' => [
            'client_id' => 'admin-cli',
            'username' => 'admin',
            'password' => 'admin123',
            'grant_type' => 'password',
        ],
    ]);

    $accessToken = json_decode($tokenResponse->getBody(), true)['access_token'];
    echo "✅ تم\n\n";

    // Get security-admin-console client
    echo "🔍 البحث عن security-admin-console...\n";
    $clientsResponse = $client->get("/admin/realms/$realmId/clients", [
        'headers' => ['Authorization' => "Bearer $accessToken"],
    ]);

    $clients = json_decode($clientsResponse->getBody(), true);
    $adminConsoleClient = null;

    foreach ($clients as $c) {
        if ($c['clientId'] == 'security-admin-console') {
            $adminConsoleClient = $c;
            break;
        }
    }

    if (!$adminConsoleClient) {
        echo "❌ Client غير موجود\n";
        exit(1);
    }

    echo "✅ تم العثور عليه\n\n";
    $clientInternalId = $adminConsoleClient['id'];

    // Simplified settings - disable PKCE
    echo "⚙️  تبسيط الإعدادات...\n";

    $simplifiedSettings = [
        'clientId' => 'security-admin-console',
        'name' => 'security-admin-console',
        'enabled' => true,
        'publicClient' => true,
        'protocol' => 'openid-connect',
        'standardFlowEnabled' => true,
        'implicitFlowEnabled' => true, // Enable implicit flow
        'directAccessGrantsEnabled' => true,
        'bearerOnly' => false,
        'consentRequired' => false,
        'fullScopeAllowed' => true,
        'frontchannelLogout' => true,
        'attributes' => [
            'post.logout.redirect.uris' => '+',
            'oauth2.device.authorization.grant.enabled' => 'false',
            'oidc.ciba.grant.enabled' => 'false',
            'backchannel.logout.session.required' => 'true',
            'backchannel.logout.revoke.offline.tokens' => 'false',
        ],
        'redirectUris' => [
            "$keycloakUrl/admin/$realmId/console/*",
            "http://localhost:8090/admin/$realmId/console/*",
        ],
        'webOrigins' => [
            '+',
        ],
        'baseUrl' => "$keycloakUrl/admin/$realmId/console/",
    ];

    $client->put("/admin/realms/$realmId/clients/$clientInternalId", [
        'headers' => [
            'Authorization' => "Bearer $accessToken",
            'Content-Type' => 'application/json',
        ],
        'json' => $simplifiedSettings,
    ]);

    echo "✅ تم التحديث\n\n";

    // Also add admin role to the user
    echo "👤 إضافة Admin roles للمستخدم...\n";

    // Get users
    $usersResponse = $client->get("/admin/realms/$realmId/users", [
        'headers' => ['Authorization' => "Bearer $accessToken"],
        'query' => ['email' => 'wedwefw@safsdfg.com'],
    ]);

    $users = json_decode($usersResponse->getBody(), true);

    if (!empty($users)) {
        $userId = $users[0]['id'];

        // Get realm management client
        $realmMgmtResponse = $client->get("/admin/realms/$realmId/clients", [
            'headers' => ['Authorization' => "Bearer $accessToken"],
            'query' => ['clientId' => 'realm-management'],
        ]);

        $realmMgmtClients = json_decode($realmMgmtResponse->getBody(), true);

        if (!empty($realmMgmtClients)) {
            $realmMgmtClientId = $realmMgmtClients[0]['id'];

            // Get available roles
            $rolesResponse = $client->get("/admin/realms/$realmId/clients/$realmMgmtClientId/roles", [
                'headers' => ['Authorization' => "Bearer $accessToken"],
            ]);

            $roles = json_decode($rolesResponse->getBody(), true);

            // Find realm-admin role
            $adminRole = null;
            foreach ($roles as $role) {
                if ($role['name'] == 'realm-admin') {
                    $adminRole = $role;
                    break;
                }
            }

            if ($adminRole) {
                // Assign realm-admin role to user
                try {
                    $client->post("/admin/realms/$realmId/users/$userId/role-mappings/clients/$realmMgmtClientId", [
                        'headers' => [
                            'Authorization' => "Bearer $accessToken",
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [$adminRole],
                    ]);

                    echo "✅ تم إضافة realm-admin role\n\n";
                } catch (\Exception $e) {
                    echo "⚠️  Role ربما مضاف بالفعل\n\n";
                }
            }
        }
    }

    echo "════════════════════════════════════\n";
    echo "✅ اكتمل!\n";
    echo "════════════════════════════════════\n\n";

    echo "💡 الآن:\n";
    echo "1. افتح نافذة Incognito جديدة\n";
    echo "2. اذهب إلى: $keycloakUrl/admin/$realmId/console\n";
    echo "3. استخدم: wedwefw@safsdfg.com / admin123\n\n";

} catch (\Exception $e) {
    echo "\n❌ خطأ: " . $e->getMessage() . "\n\n";

    if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
        echo "Response: " . $e->getResponse()->getBody() . "\n\n";
    }

    exit(1);
}
