<?php

/**
 * تشخيص شامل لمشكلة Keycloak
 */

require __DIR__.'/vendor/autoload.php';

use GuzzleHttp\Client;

$realmId = $argv[1] ?? 'tenant-32';

echo "\n════════════════════════════════════\n";
echo "🔍 تشخيص شامل لـ Keycloak Realm\n";
echo "════════════════════════════════════\n\n";

$keycloakUrl = 'http://localhost:8090';
$client = new Client([
    'base_uri' => $keycloakUrl,
    'verify' => false,
    'timeout' => 30,
]);

try {
    // Get admin token
    echo "🔐 الحصول على Admin Token...\n";
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

    // 1. Get Realm Info
    echo "📋 معلومات Realm:\n";
    echo "─────────────────────────────────\n";
    $realmResponse = $client->get("/admin/realms/$realmId", [
        'headers' => ['Authorization' => "Bearer $accessToken"],
    ]);

    $realm = json_decode($realmResponse->getBody(), true);
    echo "   Realm: {$realm['realm']}\n";
    echo "   Display Name: {$realm['displayName']}\n";
    echo "   Enabled: " . ($realm['enabled'] ? 'Yes' : 'No') . "\n";
    echo "   Login With Email: " . ($realm['loginWithEmailAllowed'] ? 'Yes' : 'No') . "\n";
    echo "   Registration Allowed: " . ($realm['registrationAllowed'] ? 'Yes' : 'No') . "\n\n";

    // 2. Get Clients
    echo "🔧 Clients في الRealm:\n";
    echo "─────────────────────────────────\n";
    $clientsResponse = $client->get("/admin/realms/$realmId/clients", [
        'headers' => ['Authorization' => "Bearer $accessToken"],
    ]);

    $clients = json_decode($clientsResponse->getBody(), true);
    $adminConsoleClient = null;

    foreach ($clients as $c) {
        if ($c['clientId'] == 'security-admin-console') {
            $adminConsoleClient = $c;
            echo "   ✅ security-admin-console موجود\n";
            echo "      Enabled: " . ($c['enabled'] ? 'Yes' : 'No') . "\n";
            echo "      Public Client: " . ($c['publicClient'] ? 'Yes' : 'No') . "\n";
            echo "      Standard Flow: " . ($c['standardFlowEnabled'] ? 'Yes' : 'No') . "\n";
            echo "      Direct Access: " . ($c['directAccessGrantsEnabled'] ? 'Yes' : 'No') . "\n";

            if (isset($c['redirectUris'])) {
                echo "      Redirect URIs:\n";
                foreach ($c['redirectUris'] as $uri) {
                    echo "         - $uri\n";
                }
            }

            if (isset($c['webOrigins'])) {
                echo "      Web Origins:\n";
                foreach ($c['webOrigins'] as $origin) {
                    echo "         - $origin\n";
                }
            }
        }
    }

    if (!$adminConsoleClient) {
        echo "   ❌ security-admin-console غير موجود!\n";
    }

    echo "\n";

    // 3. Get Users
    echo "👥 المستخدمون:\n";
    echo "─────────────────────────────────\n";
    $usersResponse = $client->get("/admin/realms/$realmId/users", [
        'headers' => ['Authorization' => "Bearer $accessToken"],
    ]);

    $users = json_decode($usersResponse->getBody(), true);

    foreach ($users as $user) {
        echo "   Username: {$user['username']}\n";
        echo "   Email: {$user['email']}\n";
        echo "   Enabled: " . ($user['enabled'] ? 'Yes' : 'No') . "\n";
        echo "   Email Verified: " . ($user['emailVerified'] ? 'Yes' : 'No') . "\n";

        // Get user roles
        $rolesResponse = $client->get("/admin/realms/$realmId/users/{$user['id']}/role-mappings/realm", [
            'headers' => ['Authorization' => "Bearer $accessToken"],
        ]);

        $roles = json_decode($rolesResponse->getBody(), true);
        if (!empty($roles)) {
            echo "   Roles:\n";
            foreach ($roles as $role) {
                echo "      - {$role['name']}\n";
            }
        }
    }

    echo "\n════════════════════════════════════\n\n";

} catch (\Exception $e) {
    echo "\n❌ خطأ: " . $e->getMessage() . "\n\n";

    if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
        echo "Response: " . $e->getResponse()->getBody() . "\n\n";
    }

    exit(1);
}
