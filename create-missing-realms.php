<?php

/**
 * إنشاء Keycloak Realms للاشتراكات الموجودة
 *
 * يستخدم هذا السكريبت لإنشاء Realms لجميع الاشتراكات
 * التي لا تملك keycloak_realm_id
 */

require __DIR__.'/vendor/autoload.php';

use App\Jobs\CreateTenantKeycloakRealm;
use App\Models\Subscription;
use App\Models\User;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n========================================\n";
echo "إنشاء Keycloak Realms للاشتراكات الموجودة\n";
echo "========================================\n\n";

// Get subscriptions without Keycloak realm
$subscriptions = Subscription::whereNull('keycloak_realm_id')
    ->with('user')
    ->get();

if ($subscriptions->isEmpty()) {
    echo "✅ جميع الاشتراكات لديها Keycloak Realms بالفعل!\n\n";
    exit(0);
}

echo "📊 وجدت " . $subscriptions->count() . " اشتراك بحاجة لإنشاء Realm\n\n";

$created = 0;
$failed = 0;

foreach ($subscriptions as $subscription) {
    echo "──────────────────────────────────────\n";
    echo "Subscription ID: {$subscription->id}\n";
    echo "User: {$subscription->user->email}\n";
    echo "Product: {$subscription->product}\n";

    try {
        // Dispatch job to create Keycloak realm
        CreateTenantKeycloakRealm::dispatch($subscription, $subscription->user);

        echo "✅ تم إضافة Job لإنشاء Realm\n";
        $created++;

    } catch (\Exception $e) {
        echo "❌ فشل: {$e->getMessage()}\n";
        $failed++;
    }
}

echo "\n========================================\n";
echo "الملخص:\n";
echo "========================================\n";
echo "✅ Jobs تم إضافتها: {$created}\n";
echo "❌ Jobs فشلت: {$failed}\n";
echo "\n";

if ($created > 0) {
    echo "⚠️  الآن قم بتشغيل Queue Worker:\n";
    echo "   php artisan queue:work\n\n";
    echo "💡 أو شغلها يدوياً لمرة واحدة:\n";
    echo "   php artisan queue:work --once --tries=1\n\n";
}

echo "========================================\n\n";
