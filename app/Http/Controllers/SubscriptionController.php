<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Bus;
use App\Models\Subscription;
use App\Jobs\CreateKayanERPSite;
use App\Jobs\CreateTenantKeycloakRealm;

class SubscriptionController extends Controller
{
    /**
     * Subscribe to a product (Training, Services, or Kayan ERP)
     */
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'product' => 'required|in:training,services,kayan_erp',
        ]);

        $user = Auth::user();
        $product = $validated['product'];

        // Check if already subscribed
        $existing = Subscription::where('user_id', $user->id)
            ->where('product', $product)
            ->first();

        if ($existing) {
            return back()->with('error', 'أنت مشترك بالفعل في هذا المنتج');
        }

        try {
            $subscription = $this->createSubscription($user, $product);

            // Different messages based on product
            if ($product === 'kayan_erp') {
                $message = 'تم طلب الاشتراك بنجاح! جاري إنشاء موقع Kayan ERP الخاص بك (قد يستغرق 5-7 دقائق). سيتم إشعارك عند الانتهاء.';
            } else {
                $message = 'تم الاشتراك بنجاح! يمكنك الآن الوصول إلى المنتج';
            }

            return redirect('/dashboard')->with('success', $message);

        } catch (\Exception $e) {
            return back()->with('error', 'حدث خطأ أثناء الاشتراك: ' . $e->getMessage());
        }
    }

    /**
     * Create subscription based on product type
     */
    private function createSubscription($user, $product)
    {
        switch ($product) {
            case 'training':
                return $this->subscribeTraining($user);

            case 'services':
                return $this->subscribeServices($user);

            case 'kayan_erp':
                return $this->subscribeKayanERP($user);

            default:
                throw new \Exception('منتج غير صحيح');
        }
    }

    /**
     * Subscribe to Training Platform
     */
    private function subscribeTraining($user)
    {
        // Call Training Platform API to create tenant
        $response = Http::post('http://localhost:5000/api/tenants/create', [
            'user_id' => $user->id,
            'company_name' => $user->company_name,
            'email' => $user->email,
        ]);

        if (!$response->successful()) {
            throw new \Exception('فشل إنشاء tenant في Training Platform: ' . $response->body());
        }

        $data = $response->json();

        // Save subscription with admin credentials
        return Subscription::create([
            'user_id' => $user->id,
            'product' => 'training',
            'tenant_id' => $data['tenant_id'],
            'domain' => $data['domain'],
            'url' => $data['admin_url'] ?? 'http://localhost:5000/admin',
            'is_active' => true,
            'meta' => [
                'admin_url' => $data['admin_url'] ?? 'http://localhost:5000/admin',
                'admin_username' => $data['admin_username'] ?? $data['admin_email'],
                'admin_email' => $data['admin_email'],
                'admin_password' => $data['admin_password'],
                'admin_user_id' => $data['admin_user_id'],
                'login_credentials' => $data['login_credentials'] ?? [
                    'username' => $data['admin_email'],
                    'password' => $data['admin_password'],
                    'url' => $data['admin_url'] ?? 'http://localhost:5000/admin'
                ],
                'created_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Subscribe to Services Platform
     */
    private function subscribeServices($user)
    {
        // Call Services Platform API to create tenant
        $response = Http::post('http://localhost:7000/api/tenants/create', [
            'user_id' => $user->id,
            'company_name' => $user->company_name,
            'email' => $user->email,
        ]);

        if (!$response->successful()) {
            throw new \Exception('فشل إنشاء tenant في Services Platform: ' . $response->body());
        }

        $data = $response->json();

        // Save subscription with admin credentials
        return Subscription::create([
            'user_id' => $user->id,
            'product' => 'services',
            'tenant_id' => $data['tenant_id'],
            'domain' => $data['domain'],
            'url' => $data['admin_url'] ?? 'http://localhost:7000/admin',
            'is_active' => true,
            'meta' => [
                'admin_url' => $data['admin_url'] ?? 'http://localhost:7000/admin',
                'admin_username' => $data['admin_username'] ?? $data['admin_email'],
                'admin_email' => $data['admin_email'],
                'admin_password' => $data['admin_password'],
                'admin_user_id' => $data['admin_user_id'],
                'login_credentials' => $data['login_credentials'] ?? [
                    'username' => $data['admin_email'],
                    'password' => $data['admin_password'],
                    'url' => $data['admin_url'] ?? 'http://localhost:7000/admin'
                ],
                'created_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Subscribe to Kayan ERP
     * Creates a fully isolated Frappe/ERPNext site for each tenant
     * Uses Queue Jobs for asynchronous processing
     */
    private function subscribeKayanERP($user)
    {
        // Generate admin password for the new site
        $adminPassword = 'admin' . rand(1000, 9999);

        // Create subscription with pending status
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'product' => 'kayan_erp',
            'tenant_id' => null, // Will be filled when site is created
            'domain' => null,
            'url' => null,
            'is_active' => false, // Will be activated when site is ready
            'status' => 'pending',
            'meta' => [
                'company_name' => $user->company_name,
                'email' => $user->email,
                'admin_password' => $adminPassword,
                'requested_at' => now()->toISOString(),
            ],
        ]);

        // Dispatch jobs to queue for background processing in sequence
        // First create Keycloak realm, then create Kayan ERP site
        Bus::chain([
            new CreateTenantKeycloakRealm($subscription, $user),
            new CreateKayanERPSite($subscription, $user, $adminPassword),
        ])->dispatch();

        return $subscription;
    }

    /**
     * Cancel subscription
     */
    public function cancel(Request $request, Subscription $subscription)
    {
        // Check ownership
        if ($subscription->user_id !== Auth::id()) {
            abort(403);
        }

        $subscription->update(['is_active' => false]);

        // TODO: Call respective platform API to deactivate tenant

        return redirect('/dashboard')->with('success', 'تم إلغاء الاشتراك');
    }
}
