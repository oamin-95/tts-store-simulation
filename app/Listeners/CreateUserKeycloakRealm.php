<?php

namespace App\Listeners;

use App\Events\UserCreated;
use App\Services\KeycloakService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CreateUserKeycloakRealm implements ShouldQueue
{
    use InteractsWithQueue;

    protected $keycloak;

    /**
     * Create the event listener.
     */
    public function __construct(KeycloakService $keycloak)
    {
        $this->keycloak = $keycloak;
    }

    /**
     * Handle the event.
     */
    public function handle(UserCreated $event): void
    {
        $user = $event->user;

        // Skip if user already has a realm
        if ($user->keycloak_realm_id) {
            Log::info("User {$user->id} already has Keycloak realm: {$user->keycloak_realm_id}");
            return;
        }

        try {
            Log::info("Creating Keycloak realm for user {$user->id}");

            // Create realm
            $realmId = $this->keycloak->createTenantRealm(
                $user->id,
                $user->name ?? $user->email
            );

            // Create first user in realm
            $this->keycloak->createUser($realmId, [
                'username' => $user->email,
                'email' => $user->email,
                'firstName' => $user->name ?? '',
                'lastName' => '',
                'enabled' => true,
                'emailVerified' => true,
                'password' => 'ChangeMe123!', // Temporary password
                'temporary_password' => true,
            ]);

            // Fix admin console client
            $this->keycloak->fixAdminConsoleClient($realmId);

            // Update user with realm_id
            $user->update(['keycloak_realm_id' => $realmId]);

            Log::info("Successfully created Keycloak realm {$realmId} for user {$user->id}");

        } catch (\Exception $e) {
            Log::error("Failed to create Keycloak realm for user {$user->id}: " . $e->getMessage());

            // Re-throw to trigger job retry
            throw $e;
        }
    }
}
