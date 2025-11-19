<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\KeycloakService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class KeycloakIntegrationController extends Controller
{
    protected $keycloakService;

    public function __construct(KeycloakService $keycloakService)
    {
        $this->keycloakService = $keycloakService;
    }

    /**
     * Sync roles from a product to tenant's Keycloak realm
     *
     * Expected request body:
     * {
     *   "subscription_id": 1,
     *   "product": "training",
     *   "roles": [
     *     {"name": "admin", "description": "Administrator role"},
     *     {"name": "user", "description": "Regular user role"}
     *   ]
     * }
     */
    public function syncRoles(Request $request)
    {
        $validated = $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
            'product' => 'required|string',
            'roles' => 'required|array',
            'roles.*.name' => 'required|string',
            'roles.*.description' => 'nullable|string',
        ]);

        try {
            $subscription = Subscription::findOrFail($validated['subscription_id']);

            if (!$subscription->keycloak_realm_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Keycloak realm not created yet for this subscription',
                ], 400);
            }

            $realmId = $subscription->keycloak_realm_id;
            $product = $validated['product'];

            // Create or get product client in Keycloak
            $productUrl = $this->getProductUrl($product);
            $clientInfo = $this->keycloakService->addProductClient($realmId, $product, $productUrl);

            // Sync roles to the client
            $this->keycloakService->syncProductRoles($realmId, $clientInfo['client_id'], $validated['roles']);

            Log::info("Roles synced successfully for product {$product} in realm {$realmId}");

            return response()->json([
                'success' => true,
                'message' => 'Roles synced successfully',
                'realm_id' => $realmId,
                'client_id' => $clientInfo['client_id'],
                'client_secret' => $clientInfo['client_secret'],
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to sync roles: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync roles: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get realm information for a subscription
     */
    public function getRealmInfo(Request $request)
    {
        $validated = $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
        ]);

        $subscription = Subscription::with('user')->findOrFail($validated['subscription_id']);

        if (!$subscription->keycloak_realm_id) {
            return response()->json([
                'success' => false,
                'message' => 'Keycloak realm not created yet',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'realm_id' => $subscription->keycloak_realm_id,
            'realm_url' => config('services.keycloak.url') . '/realms/' . $subscription->keycloak_realm_id,
            'admin_console_url' => config('services.keycloak.url') . '/admin/' . $subscription->keycloak_realm_id . '/console',
        ]);
    }

    /**
     * Get realm ID and info by tenant_id (for Laravel products)
     *
     * This endpoint allows Laravel products to get the shared realm_id
     * when creating a new tenant, so all products use the same Keycloak realm
     *
     * Expected request body:
     * {
     *   "tenant_id": "services-user-89766-1763517818",
     *   "product": "services"
     * }
     *
     * Response:
     * {
     *   "success": true,
     *   "realm_id": "tenant-1",
     *   "keycloak_url": "http://localhost:8090",
     *   "auth_endpoint": "http://localhost:8090/realms/tenant-1/protocol/openid-connect/auth",
     *   "token_endpoint": "http://localhost:8090/realms/tenant-1/protocol/openid-connect/token",
     *   "user_id": 1
     * }
     */
    public function getRealmByTenantId(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => 'required|string',
            'product' => 'required|string|in:services,training',
        ]);

        $tenantId = $validated['tenant_id'];
        $product = $validated['product'];

        try {
            // Extract user_id from tenant_id format: services-user-{user_id}-{timestamp}
            // Or: training-user-{user_id}-{timestamp}
            preg_match('/user-(\d+)-/', $tenantId, $matches);
            $userId = $matches[1] ?? null;

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid tenant_id format. Expected format: {product}-user-{id}-{timestamp}',
                ], 400);
            }

            // Find the subscription for this user that has a Keycloak realm
            // Priority: kayan_erp subscription (main product that creates realm)
            $subscription = Subscription::where('user_id', $userId)
                ->whereNotNull('keycloak_realm_id')
                ->orderByRaw("CASE WHEN product = 'kayan_erp' THEN 1 ELSE 2 END")
                ->first();

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No Keycloak realm found for this user. Please ensure Kayan ERP subscription is created first.',
                ], 404);
            }

            $realmId = $subscription->keycloak_realm_id;
            $keycloakUrl = config('services.keycloak.url', 'http://localhost:8090');

            Log::info("Realm lookup successful for tenant {$tenantId}: realm_id={$realmId}, user_id={$userId}");

            return response()->json([
                'success' => true,
                'realm_id' => $realmId,
                'keycloak_url' => $keycloakUrl,
                'auth_endpoint' => "{$keycloakUrl}/realms/{$realmId}/protocol/openid-connect/auth",
                'token_endpoint' => "{$keycloakUrl}/realms/{$realmId}/protocol/openid-connect/token",
                'userinfo_endpoint' => "{$keycloakUrl}/realms/{$realmId}/protocol/openid-connect/userinfo",
                'user_id' => (int)$userId,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to get realm for tenant {$tenantId}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve realm information: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get product URL based on product name
     */
    private function getProductUrl($product)
    {
        $urls = [
            'training' => 'http://localhost:5000',
            'services' => 'http://localhost:7000',
            'kayan_erp' => 'http://localhost:8000',
        ];

        return $urls[$product] ?? 'http://localhost';
    }
}
