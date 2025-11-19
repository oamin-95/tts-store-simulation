<?php

require __DIR__.'/vendor/autoload.php';

use App\Models\User;
use App\Models\Subscription;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$searchTerm = $argv[1] ?? 'sadwadfwaf';

echo "\n🔍 البحث عن: $searchTerm\n";
echo "════════════════════════════════════\n\n";

// Search
$user = User::where('email', 'like', "%$searchTerm%")
    ->orWhere('name', 'like', "%$searchTerm%")
    ->orWhere('company_name', 'like', "%$searchTerm%")
    ->first();

if (!$user) {
    echo "❌ لم يتم العثور على المستخدم\n\n";
    echo "📋 آخر 5 مستخدمين:\n";
    $users = User::orderBy('created_at', 'desc')->take(5)->get();
    foreach ($users as $u) {
        echo "   ────────────────────\n";
        echo "   ID: $u->id\n";
        echo "   Email: $u->email\n";
        echo "   Company: $u->company_name\n";
        echo "   Created: $u->created_at\n";

        $subs = Subscription::where('user_id', $u->id)->get();
        if ($subs->isNotEmpty()) {
            foreach ($subs as $sub) {
                echo "      └─ [$sub->product] Keycloak: " . ($sub->keycloak_realm_id ?? 'NULL') . "\n";
            }
        }
    }
    exit(0);
}

echo "✅ تم العثور على المستخدم:\n";
echo "   ID: $user->id\n";
echo "   Email: $user->email\n";
echo "   Company: $user->company_name\n";
echo "   Created: $user->created_at\n\n";

$subs = Subscription::where('user_id', $user->id)->orderBy('created_at', 'desc')->get();
echo "📋 الاشتراكات (" . count($subs) . "):\n";

if ($subs->isEmpty()) {
    echo "   (لا توجد اشتراكات)\n\n";
} else {
    foreach ($subs as $sub) {
        echo "   ───────────────────────────────────\n";
        echo "   ID: $sub->id\n";
        echo "   Product: $sub->product\n";
        echo "   Keycloak Realm: " . ($sub->keycloak_realm_id ?? 'NULL') . "\n";
        echo "   Active: " . ($sub->is_active ? 'Yes' : 'No') . "\n";
        echo "   Created: $sub->created_at\n";
        echo "   Updated: $sub->updated_at\n";

        if ($sub->meta) {
            $hasKeycloak = isset($sub->meta['keycloak']);
            $hasPassword = isset($sub->meta['admin_password']);
            echo "   Meta: Keycloak=" . ($hasKeycloak ? 'Yes' : 'No') . ", Password=" . ($hasPassword ? 'Yes' : 'No') . "\n";
        }
    }
}

echo "\n════════════════════════════════════\n\n";
