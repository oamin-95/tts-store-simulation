<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\KeycloakService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    protected $keycloak;

    public function __construct(KeycloakService $keycloak)
    {
        $this->keycloak = $keycloak;
    }

    /**
     * Display product details with users and groups
     */
    public function show($product)
    {
        $user = Auth::user();

        // Find active subscription for this product
        $subscription = Subscription::where('user_id', $user->id)
            ->where('product', $product)
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return redirect()->route('dashboard')->with('error', 'لا يوجد اشتراك نشط لهذا المنتج');
        }

        if (!$user->keycloak_realm_id) {
            return redirect()->route('dashboard')->with('error', 'لا يوجد Keycloak Realm');
        }

        try {
            // Get users and groups from Keycloak
            $users = $this->keycloak->getUsers($user->keycloak_realm_id);
            $groups = $this->keycloak->getGroups($user->keycloak_realm_id);

            // Product names mapping
            $productNames = [
                'training' => 'منصة التدريب',
                'services' => 'منصة الخدمات',
                'kayan_erp' => 'كيان ERP',
            ];

            // Get product URL
            $productConfig = config("products.{$product}");

            // For Kayan ERP, use subscription URL (includes dynamic port)
            // For other products, use domain or fallback to config URL
            if ($product === 'kayan_erp' && $subscription->url) {
                $productUrl = $subscription->url;
            } elseif ($subscription->domain) {
                $productUrl = "http://{$subscription->domain}:5000";
            } else {
                $productUrl = $productConfig['url'] ?? '#';
            }

            return view('products.show', [
                'subscription' => $subscription,
                'product' => $product,
                'productName' => $productNames[$product] ?? $product,
                'productUrl' => $productUrl,
                'users' => $users,
                'groups' => $groups,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to load product details for {$product}: " . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'فشل في تحميل بيانات المنتج');
        }
    }
}
