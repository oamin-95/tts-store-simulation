<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Subscription;
use App\Models\User;

echo "=== التحقق من إعداد Keycloak Realms ===\n\n";

// Check all subscriptions
$subscriptions = Subscription::with('user')->get();

echo "عدد الاشتراكات: " . $subscriptions->count() . "\n\n";

foreach ($subscriptions as $sub) {
    echo "الاشتراك #{$sub->id}:\n";
    echo "  المستخدم: {$sub->user->name} (ID: {$sub->user_id})\n";
    echo "  المنتج: {$sub->product}\n";
    echo "  Realm ID: " . ($sub->keycloak_realm_id ?? 'NULL') . "\n";
    echo "  Tenant ID: " . ($sub->tenant_id ?? 'NULL') . "\n";
    echo "  ---\n";
}

echo "\n=== API Endpoint للحصول على Realm Info ===\n";
echo "يجب أن يكون هناك API endpoint في الـ Marketplace لإرجاع realm_id للمستخدم:\n";
echo "POST /api/keycloak/realm-info\n";
echo "Body: {\"subscription_id\": 1}\n";
echo "Response: {\"realm_id\": \"tenant-80\", \"realm_url\": \"...\"}\n\n";

echo "الحل المقترح: منتجات Laravel تستدعي هذا الـ API عند إنشاء tenant جديد\n";
