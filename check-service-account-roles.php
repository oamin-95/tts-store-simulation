<?php

/**
 * فحص صلاحيات Service Account
 */

use GuzzleHttp\Client;

require __DIR__.'/vendor/autoload.php';

$keycloakUrl = 'http://localhost:8090';
$clientId = 'saas-marketplace-admin';
$clientSecret = 'M1VVIsCH9WsrSOWJwul9MoB3o4MIKZ1W';

$client = new Client([
    'base_uri' => $keycloakUrl,
    'verify' => false,
    'timeout' => 30,
]);

echo "🔍 فحص صلاحيات Service Account\n";
echo "================================\n\n";

// Get access token
$response = $client->post('/realms/master/protocol/openid-connect/token', [
    'form_params' => [
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
    ],
]);

$tokenData = json_decode($response->getBody()->getContents(), true);
$accessToken = $tokenData['access_token'];

echo "✅ تم الحصول على Token\n\n";

// Decode JWT to see roles
$parts = explode('.', $accessToken);
$payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);

echo "📋 Resource Access (الصلاحيات):\n";
echo "================================\n";
print_r($payload['resource_access'] ?? 'لا يوجد');
echo "\n\n";

echo "📋 Realm Access (صلاحيات Realm):\n";
echo "================================\n";
print_r($payload['realm_access'] ?? 'لا يوجد');
echo "\n\n";

echo "📋 Scope:\n";
echo "================================\n";
echo $payload['scope'] ?? 'لا يوجد';
echo "\n\n";
