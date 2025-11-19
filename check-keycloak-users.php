<?php

require __DIR__.'/vendor/autoload.php';

use GuzzleHttp\Client;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$keycloakUrl = 'http://localhost:8090';
$adminUser = 'admin';
$adminPassword = 'admin123';
$realmId = $argv[1] ?? 'tenant-30';

echo "\n════════════════════════════════════\n";
echo "🔍 فحص المستخدمين في Keycloak\n";
echo "════════════════════════════════════\n\n";
echo "Realm: $realmId\n\n";

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
            'username' => $adminUser,
            'password' => $adminPassword,
            'grant_type' => 'password',
        ],
    ]);

    $tokenData = json_decode($tokenResponse->getBody(), true);
    $accessToken = $tokenData['access_token'];
    echo "✅ تم الحصول على Token\n\n";

    // Get users in realm
    echo "👥 جلب المستخدمين من Realm '$realmId'...\n";
    $usersResponse = $client->get("/admin/realms/$realmId/users", [
        'headers' => [
            'Authorization' => "Bearer $accessToken",
        ],
    ]);

    $users = json_decode($usersResponse->getBody(), true);

    if (empty($users)) {
        echo "❌ لا يوجد مستخدمين في هذا الRealm!\n\n";
    } else {
        echo "✅ تم العثور على " . count($users) . " مستخدم:\n\n";

        foreach ($users as $user) {
            echo "   ────────────────────\n";
            echo "   ID: {$user['id']}\n";
            echo "   Username: {$user['username']}\n";
            echo "   Email: {$user['email']}\n";
            echo "   Enabled: " . ($user['enabled'] ? 'Yes' : 'No') . "\n";
            echo "   Email Verified: " . ($user['emailVerified'] ? 'Yes' : 'No') . "\n";
            echo "   Created: " . date('Y-m-d H:i:s', $user['createdTimestamp'] / 1000) . "\n";
        }
    }

    echo "\n════════════════════════════════════\n\n";

} catch (\Exception $e) {
    echo "\n❌ خطأ: " . $e->getMessage() . "\n\n";

    if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
        $response = $e->getResponse();
        echo "Status Code: " . $response->getStatusCode() . "\n";
        echo "Response: " . $response->getBody() . "\n\n";
    }

    exit(1);
}
