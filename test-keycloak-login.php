<?php

/**
 * اختبار تسجيل الدخول في Keycloak
 */

require __DIR__.'/vendor/autoload.php';

use GuzzleHttp\Client;

$realmId = $argv[1] ?? 'tenant-32';
$userEmail = $argv[2] ?? 'wedwefw@safsdfg.com';
$password = $argv[3] ?? 'admin123';

echo "\n════════════════════════════════════\n";
echo "🧪 اختبار تسجيل الدخول في Keycloak\n";
echo "════════════════════════════════════\n\n";

echo "Realm: $realmId\n";
echo "User: $userEmail\n";
echo "Password: $password\n\n";

$keycloakUrl = 'http://localhost:8090';
$client = new Client([
    'base_uri' => $keycloakUrl,
    'verify' => false,
    'timeout' => 30,
]);

try {
    echo "🔐 محاولة الحصول على Token...\n";

    $response = $client->post("/realms/$realmId/protocol/openid-connect/token", [
        'form_params' => [
            'client_id' => 'admin-cli',
            'username' => $userEmail,
            'password' => $password,
            'grant_type' => 'password',
        ],
    ]);

    $tokenData = json_decode($response->getBody(), true);

    echo "✅ نجح تسجيل الدخول!\n\n";
    echo "📋 معلومات Token:\n";
    echo "   Access Token: " . substr($tokenData['access_token'], 0, 50) . "...\n";
    echo "   Expires In: {$tokenData['expires_in']} seconds\n";
    echo "   Token Type: {$tokenData['token_type']}\n\n";

    echo "════════════════════════════════════\n";
    echo "✅ المستخدم وكلمة المرور صحيحة!\n";
    echo "════════════════════════════════════\n\n";

    echo "💡 الآن جرّب الدخول عبر المتصفح:\n";
    echo "   $keycloakUrl/admin/$realmId/console\n\n";

} catch (\GuzzleHttp\Exception\ClientException $e) {
    echo "❌ فشل تسجيل الدخول!\n\n";

    if ($e->hasResponse()) {
        $statusCode = $e->getResponse()->getStatusCode();
        $body = $e->getResponse()->getBody();

        echo "Status Code: $statusCode\n";
        echo "Response: $body\n\n";

        if ($statusCode == 401) {
            echo "⚠️  كلمة المرور أو اسم المستخدم خاطئ!\n";
        } elseif ($statusCode == 400) {
            $data = json_decode($body, true);
            if (isset($data['error'])) {
                echo "⚠️  خطأ: {$data['error']}\n";
                if (isset($data['error_description'])) {
                    echo "   {$data['error_description']}\n";
                }
            }
        }
    }

    exit(1);

} catch (\Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n\n";
    exit(1);
}
