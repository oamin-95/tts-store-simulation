<?php

/**
 * إصلاح مشكلة تسجيل الدخول في Keycloak
 * - حذف المستخدم القديم
 * - إنشاء مستخدم جديد بكلمة مرور بسيطة
 */

require __DIR__.'/vendor/autoload.php';

use GuzzleHttp\Client;
use App\Models\Subscription;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$subscriptionId = $argv[1] ?? 32;

echo "\n========================================\n";
echo "إصلاح مشكلة Keycloak\n";
echo "========================================\n\n";

$subscription = Subscription::with('user')->find($subscriptionId);

if (!$subscription || !$subscription->keycloak_realm_id) {
    echo "❌ الاشتراك غير صحيح\n";
    exit(1);
}

$keycloakUrl = 'http://localhost:8090';
$realmId = $subscription->keycloak_realm_id;
$userEmail = $subscription->user->email;

echo "Realm: $realmId\n";
echo "User: $userEmail\n\n";

$client = new Client([
    'base_uri' => $keycloakUrl,
    'verify' => false,
    'timeout' => 30,
]);

try {
    // Get token
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

    // Find existing user
    echo "🔍 البحث عن المستخدم الموجود...\n";
    $usersResponse = $client->get("/admin/realms/$realmId/users", [
        'headers' => ['Authorization' => "Bearer $accessToken"],
        'query' => ['email' => $userEmail],
    ]);

    $users = json_decode($usersResponse->getBody(), true);

    // Delete old user if exists
    if (!empty($users)) {
        $oldUserId = $users[0]['id'];
        echo "🗑️  حذف المستخدم القديم...\n";

        $client->delete("/admin/realms/$realmId/users/$oldUserId", [
            'headers' => ['Authorization' => "Bearer $accessToken"],
        ]);

        echo "✅ تم الحذف\n\n";
    }

    // Create new user with simple password
    $newPassword = 'admin123';

    echo "👤 إنشاء مستخدم جديد...\n";
    $client->post("/admin/realms/$realmId/users", [
        'headers' => [
            'Authorization' => "Bearer $accessToken",
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'username' => $userEmail,
            'email' => $userEmail,
            'enabled' => true,
            'emailVerified' => true,
            'credentials' => [
                [
                    'type' => 'password',
                    'value' => $newPassword,
                    'temporary' => false,
                ]
            ],
        ],
    ]);

    echo "✅ تم إنشاء المستخدم\n\n";

    // Update database
    echo "💾 تحديث قاعدة البيانات...\n";
    $meta = $subscription->meta;
    $meta['keycloak']['admin_temp_password'] = $newPassword;
    $meta['keycloak']['is_temporary'] = false;
    $meta['keycloak']['fixed_at'] = now()->toISOString();
    $subscription->update(['meta' => $meta]);

    echo "✅ تم التحديث\n\n";

    echo "========================================\n";
    echo "✅ تم الإصلاح بنجاح!\n";
    echo "========================================\n\n";

    echo "📋 معلومات الدخول الجديدة:\n";
    echo "   🌐 Admin Console: $keycloakUrl/admin/$realmId/console\n";
    echo "   📧 Email: $userEmail\n";
    echo "   🔑 Password: $newPassword\n";
    echo "   ✅ كلمة المرور بسيطة وسهلة\n\n";

    echo "⚠️  الآن:\n";
    echo "   1. حدّث صفحة Dashboard (F5)\n";
    echo "   2. استخدم كلمة المرور الجديدة: $newPassword\n\n";

    echo "========================================\n\n";

} catch (\Exception $e) {
    echo "\n❌ خطأ: " . $e->getMessage() . "\n\n";

    if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
        echo "Response: " . $e->getResponse()->getBody() . "\n\n";
    }

    exit(1);
}
