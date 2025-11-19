<?php

/**
 * إعادة تعيين كلمة المرور للمستخدم في Keycloak
 */

require __DIR__.'/vendor/autoload.php';

use GuzzleHttp\Client;
use App\Models\Subscription;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n========================================\n";
echo "إعادة تعيين كلمة المرور في Keycloak\n";
echo "========================================\n\n";

// Configuration
$keycloakUrl = 'http://localhost:8090';
$adminUser = 'admin';
$adminPassword = 'admin123';
$realmId = 'tenant-29';
$userEmail = 'qweqdwqrfeg@sdfg.com';
$subscriptionId = 29;

// Create HTTP client
$client = new Client([
    'base_uri' => $keycloakUrl,
    'verify' => false,
    'timeout' => 30,
]);

try {
    // Step 1: Get admin access token
    echo "🔐 الحصول على Access Token...\n";
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

    // Step 2: Find user by email
    echo "🔍 البحث عن المستخدم...\n";
    echo "   Email: $userEmail\n";
    echo "   Realm: $realmId\n";

    $usersResponse = $client->get("/admin/realms/$realmId/users", [
        'headers' => [
            'Authorization' => "Bearer $accessToken",
        ],
        'query' => [
            'email' => $userEmail,
        ],
    ]);

    $users = json_decode($usersResponse->getBody(), true);

    if (empty($users)) {
        echo "❌ المستخدم غير موجود في Keycloak!\n\n";
        exit(1);
    }

    $userId = $users[0]['id'];
    $username = $users[0]['username'];
    echo "✅ تم العثور على المستخدم\n";
    echo "   User ID: $userId\n";
    echo "   Username: $username\n\n";

    // Step 3: Generate new password
    $newPassword = bin2hex(random_bytes(8));
    echo "🔑 إنشاء كلمة مرور جديدة...\n";

    // Step 4: Reset password
    echo "🔄 إعادة تعيين كلمة المرور...\n";
    $client->put("/admin/realms/$realmId/users/$userId/reset-password", [
        'headers' => [
            'Authorization' => "Bearer $accessToken",
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'type' => 'password',
            'value' => $newPassword,
            'temporary' => true,
        ],
    ]);

    echo "✅ تم إعادة تعيين كلمة المرور بنجاح!\n\n";

    // Step 5: Update subscription meta in database
    echo "💾 تحديث قاعدة البيانات...\n";
    $subscription = Subscription::find($subscriptionId);

    if ($subscription) {
        $meta = $subscription->meta;
        $meta['keycloak']['admin_temp_password'] = $newPassword;
        $subscription->update(['meta' => $meta]);
        echo "✅ تم تحديث قاعدة البيانات\n\n";
    }

    // Display results
    echo "========================================\n";
    echo "✅ اكتمل بنجاح!\n";
    echo "========================================\n\n";

    echo "📋 معلومات الدخول:\n";
    echo "   🌐 Admin Console: $keycloakUrl/admin/$realmId/console\n";
    echo "   📧 Email: $userEmail\n";
    echo "   🔑 Password: $newPassword\n";
    echo "   ⚠️  (مؤقتة - سيُطلب منك تغييرها عند الدخول)\n\n";

    echo "========================================\n\n";

} catch (\Exception $e) {
    echo "\n❌ خطأ: " . $e->getMessage() . "\n\n";

    if ($e instanceof \GuzzleHttp\Exception\RequestException) {
        if ($e->hasResponse()) {
            $response = $e->getResponse();
            echo "Status Code: " . $response->getStatusCode() . "\n";
            echo "Response: " . $response->getBody() . "\n\n";
        }
    }

    exit(1);
}
