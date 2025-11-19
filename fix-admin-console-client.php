<?php

/**
 * إصلاح إعدادات security-admin-console client
 */

require __DIR__.'/vendor/autoload.php';

use GuzzleHttp\Client;

$realmId = $argv[1] ?? 'tenant-32';

echo "\n════════════════════════════════════\n";
echo "🔧 إصلاح إعدادات Admin Console\n";
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

    // Get all clients
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
        echo "❌ security-admin-console غير موجود!\n\n";
        exit(1);
    }

    echo "✅ تم العثور عليه\n\n";
    $clientInternalId = $adminConsoleClient['id'];

    // Update client settings
    echo "⚙️  تحديث إعدادات Client...\n";

    $updatedSettings = [
        'clientId' => 'security-admin-console',
        'enabled' => true,
        'publicClient' => true,
        'standardFlowEnabled' => true,
        'implicitFlowEnabled' => false,
        'directAccessGrantsEnabled' => true, // Enable direct access
        'attributes' => [
            'pkce.code.challenge.method' => 'S256', // Support PKCE
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
        'json' => $updatedSettings,
    ]);

    echo "✅ تم التحديث\n\n";

    echo "════════════════════════════════════\n";
    echo "✅ تم الإصلاح بنجاح!\n";
    echo "════════════════════════════════════\n\n";

    echo "الآن جرّب:\n";
    echo "1. امسح Cache المتصفح (Ctrl+Shift+Delete)\n";
    echo "2. افتح الرابط في نافذة Incognito/Private\n";
    echo "3. $keycloakUrl/admin/$realmId/console\n\n";

} catch (\Exception $e) {
    echo "\n❌ خطأ: " . $e->getMessage() . "\n\n";

    if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
        echo "Response: " . $e->getResponse()->getBody() . "\n\n";
    }

    exit(1);
}
