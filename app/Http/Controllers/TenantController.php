<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\KeycloakService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    protected $keycloakService;

    public function __construct(KeycloakService $keycloakService)
    {
        $this->keycloakService = $keycloakService;
    }

    /**
     * Display a listing of tenants
     */
    public function index()
    {
        $tenants = Tenant::with('domains')->get();

        return response()->json([
            'success' => true,
            'data' => $tenants,
        ]);
    }

    /**
     * Store a newly created tenant
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:tenants,email',
            'domain' => 'nullable|string|unique:domains,domain',
        ]);

        try {
            // Create tenant in database
            $tenant = Tenant::create([
                'id' => Str::slug($validated['name']),
                'name' => $validated['name'],
                'email' => $validated['email'],
            ]);

            // Create domain if provided
            if (isset($validated['domain'])) {
                $tenant->domains()->create([
                    'domain' => $validated['domain'],
                ]);
            }

            // Create Keycloak Realm for this tenant
            try {
                $realmId = $this->keycloakService->createTenantRealm(
                    $tenant->id,
                    $tenant->name
                );

                // Update tenant with Keycloak realm ID
                $tenant->update([
                    'keycloak_realm_id' => $realmId,
                ]);

                Log::info("Tenant {$tenant->id} created with Keycloak realm {$realmId}");
            } catch (\Exception $e) {
                Log::error("Failed to create Keycloak realm for tenant {$tenant->id}: " . $e->getMessage());

                // Continue even if Keycloak fails - tenant is still created
                return response()->json([
                    'success' => true,
                    'data' => $tenant,
                    'warning' => 'Tenant created but Keycloak realm creation failed: ' . $e->getMessage(),
                ], 201);
            }

            return response()->json([
                'success' => true,
                'data' => $tenant,
                'message' => 'Tenant created successfully with Keycloak realm',
            ], 201);
        } catch (\Exception $e) {
            Log::error("Failed to create tenant: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create tenant: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified tenant
     */
    public function show($id)
    {
        $tenant = Tenant::with('domains')->find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $tenant,
        ]);
    }

    /**
     * Update the specified tenant
     */
    public function update(Request $request, $id)
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:tenants,email,' . $id,
        ]);

        try {
            $tenant->update($validated);

            return response()->json([
                'success' => true,
                'data' => $tenant,
                'message' => 'Tenant updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to update tenant {$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update tenant: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified tenant
     */
    public function destroy($id)
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        try {
            // Delete Keycloak realm if exists
            if ($tenant->keycloak_realm_id) {
                try {
                    $this->keycloakService->deleteRealm($tenant->keycloak_realm_id);
                } catch (\Exception $e) {
                    Log::error("Failed to delete Keycloak realm for tenant {$id}: " . $e->getMessage());
                }
            }

            // Delete tenant
            $tenant->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tenant deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to delete tenant {$id}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tenant: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add a product to tenant's Keycloak realm
     */
    public function addProduct(Request $request, $tenantId)
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        if (!$tenant->keycloak_realm_id) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant does not have a Keycloak realm',
            ], 400);
        }

        $validated = $request->validate([
            'product_name' => 'required|string',
            'product_url' => 'required|url',
            'redirect_uris' => 'nullable|array',
            'redirect_uris.*' => 'url',
        ]);

        try {
            $clientData = $this->keycloakService->addProductClient(
                $tenant->keycloak_realm_id,
                $validated['product_name'],
                $validated['product_url'],
                $validated['redirect_uris'] ?? []
            );

            return response()->json([
                'success' => true,
                'data' => $clientData,
                'message' => 'Product added to tenant successfully',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to add product to tenant {$tenantId}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to add product: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync product roles to Keycloak
     */
    public function syncProductRoles(Request $request, $tenantId)
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        if (!$tenant->keycloak_realm_id) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant does not have a Keycloak realm',
            ], 400);
        }

        $validated = $request->validate([
            'client_id' => 'required|string',
            'roles' => 'required|array',
            'roles.*.name' => 'required|string',
            'roles.*.description' => 'nullable|string',
        ]);

        try {
            $this->keycloakService->syncProductRoles(
                $tenant->keycloak_realm_id,
                $validated['client_id'],
                $validated['roles']
            );

            return response()->json([
                'success' => true,
                'message' => 'Product roles synced successfully',
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to sync roles for tenant {$tenantId}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync roles: ' . $e->getMessage(),
            ], 500);
        }
    }
}
