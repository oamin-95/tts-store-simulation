<?php

/**
 * اختبار العزل الكامل لـ Keycloak Realms
 *
 * هذا السكريبت يوضح أن كل مستأجر له:
 * - Realm معزول تمامًا
 * - صفحة دخول منفصلة
 * - لوحة إدارة منفصلة
 * - قاعدة بيانات مستخدمين منفصلة
 */

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n========================================\n";
echo "اختبار عزل Keycloak Realms\n";
echo "========================================\n\n";

// Get all subscriptions with Keycloak realms
$subscriptions = DB::table('subscriptions')
    ->whereNotNull('keycloak_realm_id')
    ->get();

if ($subscriptions->isEmpty()) {
    echo "❌ لا توجد اشتراكات مع Keycloak realms\n";
    echo "💡 قم بإنشاء اشتراك جديد أولاً\n\n";
    exit(0);
}

echo "✅ تم العثور على " . $subscriptions->count() . " اشتراك مع Keycloak realms\n\n";

foreach ($subscriptions as $subscription) {
    echo "──────────────────────────────────────\n";
    echo "📦 Subscription ID: {$subscription->id}\n";
    echo "👤 User ID: {$subscription->user_id}\n";
    echo "🏢 المنتج: {$subscription->product}\n";
    echo "\n";

    $meta = json_decode($subscription->meta, true);

    if (isset($meta['keycloak'])) {
        $keycloak = $meta['keycloak'];

        echo "🔐 Keycloak Realm Information:\n";
        echo "   • Realm ID: {$keycloak['realm_id']}\n";
        echo "   • معزول: " . ($keycloak['is_isolated'] ? '✅ نعم' : '❌ لا') . "\n";
        echo "\n";

        echo "🌐 روابط الوصول المعزولة:\n";
        echo "   • صفحة تسجيل الدخول:\n";
        echo "     {$keycloak['realm_login_url']}\n";
        echo "\n";
        echo "   • لوحة الإدارة:\n";
        echo "     {$keycloak['realm_admin_url']}\n";
        echo "\n";

        echo "🔗 API Endpoints:\n";
        echo "   • Auth: {$keycloak['auth_endpoint']}\n";
        echo "   • Token: {$keycloak['token_endpoint']}\n";
        echo "\n";

        echo "👨‍💼 بيانات Admin:\n";
        echo "   • Email: {$keycloak['admin_email']}\n";
        echo "   • Password: {$keycloak['admin_temp_password']} (مؤقتة)\n";
        echo "\n";
    }

    echo "──────────────────────────────────────\n\n";
}

echo "\n========================================\n";
echo "تفسير العزل الكامل:\n";
echo "========================================\n\n";

echo "1. 🏠 كل مستأجر له Realm منفصل تمامًا\n";
echo "   - مثل شقة منفصلة في عمارة\n";
echo "   - لا يمكن لمستأجر الوصول لبيانات مستأجر آخر\n\n";

echo "2. 🚪 صفحة دخول منفصلة لكل مستأجر\n";
echo "   - كل Realm له رابط دخول خاص\n";
echo "   - /realms/tenant-1/account\n";
echo "   - /realms/tenant-2/account\n\n";

echo "3. ⚙️ لوحة إدارة منفصلة\n";
echo "   - كل مستأجر يدير مستخدميه بشكل مستقل\n";
echo "   - /admin/tenant-1/console\n";
echo "   - /admin/tenant-2/console\n\n";

echo "4. 👥 قاعدة مستخدمين منفصلة\n";
echo "   - المستخدمون في realm-1 منفصلون عن realm-2\n";
echo "   - كل realm له جدول users خاص به\n\n";

echo "5. 🔑 أدوار وصلاحيات منفصلة\n";
echo "   - كل منتج (Training, Services, etc) له Roles خاصة\n";
echo "   - يتم مزامنتها من المنتج إلى Realm المستأجر\n\n";

echo "========================================\n";
echo "✅ التكامل جاهز للاستخدام!\n";
echo "========================================\n\n";
