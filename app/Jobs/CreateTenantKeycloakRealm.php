<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Models\User;
use App\Services\KeycloakService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CreateTenantKeycloakRealm implements ShouldQueue
{
    use Queueable;

    public $timeout = 300; // 5 minutes timeout
    public $tries = 3; // Retry up to 3 times

    protected $subscription;
    protected $user;

    /**
     * Create a new job instance.
     */
    public function __construct(Subscription $subscription, User $user)
    {
        $this->subscription = $subscription;
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(KeycloakService $keycloakService): void
    {
        Log::info("Starting Keycloak realm creation for subscription {$this->subscription->id}");

        try {
            $keycloakBaseUrl = config('services.keycloak.url');

            // Create realm for tenant
            $realmId = $keycloakService->createTenantRealm(
                $this->subscription->id,
                $this->user->company_name ?? "Tenant {$this->subscription->id}"
            );

            // Use a simple, consistent password for all tenants
            $adminPassword = 'admin123';

            // Create admin user in the realm (ignore if already exists)
            try {
                $keycloakService->createUser($realmId, [
                    'username' => $this->user->email,
                    'email' => $this->user->email,
                    'first_name' => $this->user->name ?? '',
                    'last_name' => '',
                    'enabled' => true,
                    'email_verified' => true,
                    'password' => $adminPassword,
                    'temporary_password' => false, // Make password permanent
                ]);
                Log::info("Created admin user {$this->user->email} in realm {$realmId}");
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                if ($e->getResponse()->getStatusCode() === 409) {
                    Log::info("User {$this->user->email} already exists in realm {$realmId}, skipping creation");
                } else {
                    throw $e;
                }
            }

            // Fix admin console client settings (enable proper login)
            Log::info("Fixing admin console client for realm {$realmId}");
            $keycloakService->fixAdminConsoleClient($realmId);

            // Assign realm-admin role to user (may already have it)
            try {
                Log::info("Assigning realm-admin role to {$this->user->email}");
                $keycloakService->assignRealmAdminRole($realmId, $this->user->email);
            } catch (\Exception $e) {
                Log::warning("Could not assign realm-admin role (may already be assigned): {$e->getMessage()}");
            }

            // Build Keycloak URLs
            $realmLoginUrl = "{$keycloakBaseUrl}/realms/{$realmId}/account";
            $realmAdminUrl = "{$keycloakBaseUrl}/admin/{$realmId}/console";
            $authEndpoint = "{$keycloakBaseUrl}/realms/{$realmId}/protocol/openid-connect/auth";
            $tokenEndpoint = "{$keycloakBaseUrl}/realms/{$realmId}/protocol/openid-connect/token";

            // Refresh subscription to get latest data from database
            $this->subscription->refresh();

            // Get current meta data or initialize as array
            $currentMeta = is_string($this->subscription->meta)
                ? (json_decode($this->subscription->meta, true) ?? [])
                : ($this->subscription->meta ?? []);

            // Update subscription with realm ID and access URLs
            $this->subscription->update([
                'keycloak_realm_id' => $realmId,
                'meta' => array_merge($currentMeta, [
                    'keycloak_realm_id' => $realmId,  // Also save in meta for easy access by CreateKayanERPSite
                    'keycloak' => [
                        'realm_id' => $realmId,
                        'realm_login_url' => $realmLoginUrl,
                        'realm_admin_url' => $realmAdminUrl,
                        'auth_endpoint' => $authEndpoint,
                        'token_endpoint' => $tokenEndpoint,
                        'admin_email' => $this->user->email,
                        'admin_temp_password' => $adminPassword,
                        'created_at' => now()->toISOString(),
                        'is_isolated' => true,
                        'description' => 'Keycloak realm معزول تمامًا لهذا المستأجر مع صفحة دخول ولوحة إدارة منفصلة',
                    ],
                ]),
            ]);

            Log::info("Keycloak realm {$realmId} created successfully for subscription {$this->subscription->id}");
            Log::info("Login URL: {$realmLoginUrl}");
            Log::info("Admin URL: {$realmAdminUrl}");

        } catch (\Exception $e) {
            Log::error("Failed to create Keycloak realm for subscription {$this->subscription->id}: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Job failed for subscription {$this->subscription->id}: {$exception->getMessage()}");
    }
}
