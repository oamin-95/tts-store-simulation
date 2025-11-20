<?php

namespace App\Listeners;

use App\Events\SubscriptionCreated;
use App\Services\KeycloakService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CreateKeycloakRealm implements ShouldQueue
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
     *
     * Creates Product Client in user's Keycloak realm and notifies the product
     */
    public function handle(SubscriptionCreated $event): void
    {
        $subscription = $event->subscription;
        $user = $event->user;

        Log::info("Processing subscription {$subscription->id} for product {$subscription->product}");

        // For Kayan ERP, skip if URL is not set yet (will be triggered again after site creation)
        if ($subscription->product === 'kayan_erp' && empty($subscription->url)) {
            Log::info("Skipping Keycloak client creation for Kayan ERP subscription {$subscription->id} - site not created yet");
            return;
        }

        // Ensure user has a Keycloak realm
        if (!$user->keycloak_realm_id) {
            Log::error("User {$user->id} does not have a Keycloak realm yet!");
            throw new \Exception("User must have a Keycloak realm before subscribing to products");
        }

        // Map product name to slug
        $productSlugMap = [
            'training' => 'training-platform',
            'services' => 'services-platform',
            'kayan_erp' => 'kayan-erp',
        ];

        $productSlug = $productSlugMap[$subscription->product] ?? $subscription->product;

        // Get product configuration
        $productConfig = config("products.{$subscription->product}");

        if (!$productConfig) {
            Log::warning("No configuration found for product: {$subscription->product}");
            return;
        }

        try {
            // LOG: Before creating client
            Log::info("CreateKeycloakRealm - About to create client", [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'realm_id' => $user->keycloak_realm_id,
                'product' => $subscription->product,
                'product_slug' => $productSlug,
                'product_url' => $productConfig['url'],
                'subscription_domain' => $subscription->domain,
                'subscription_tenant_id' => $subscription->tenant_id,
                'subscription_url' => $subscription->url,
            ]);

            // For Kayan ERP, use the dynamic URL from subscription (includes port)
            // For other products, use the static URL from config
            $productUrl = $subscription->product === 'kayan_erp' && $subscription->url
                ? $subscription->url
                : $productConfig['url'];

            // Create Product Client in user's realm
            $clientData = $this->keycloak->createProductClient(
                $user->keycloak_realm_id,
                $productSlug,
                $productUrl,
                $subscription->domain  // Pass tenant-specific domain
            );

            // Update subscription with client details
            $subscription->update([
                'keycloak_client_id' => $clientData['client_id'],
                'keycloak_client_secret' => $clientData['client_secret'],
                'keycloak_client_uuid' => $clientData['client_uuid'],
            ]);

            Log::info("Created Keycloak client {$clientData['client_id']} for subscription {$subscription->id}");

            // Send webhook to product platform
            $this->notifyProduct($subscription, $user, $clientData, $productConfig);

        } catch (\Exception $e) {
            Log::error("Failed to create Keycloak client for subscription {$subscription->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Notify product platform about new subscription
     */
    protected function notifyProduct($subscription, $user, $clientData, $productConfig)
    {
        // For Kayan ERP, construct webhook URL dynamically from subscription URL
        // For other products, use static webhook_url from config
        if ($subscription->product === 'kayan_erp' && $subscription->url) {
            // subscription->url is like "http://localhost:40877"
            $webhookUrl = $subscription->url . '/api/method/oidc_extended.setup.keycloak_webhook_setup';
        } else {
            $webhookUrl = $productConfig['webhook_url'] ?? null;
        }

        if (!$webhookUrl) {
            Log::warning("No webhook URL configured for product: {$subscription->product}");
            return;
        }

        try {
            // Use domain as tenant_id for products (they expect the full domain as tenant identifier)
            // For training product: training-user-X-TIMESTAMP.localhost
            // For services product: services-user-X-TIMESTAMP.localhost
            // For Kayan ERP: kayan-user-X.local
            $tenantId = $subscription->domain ?
                str_replace('.localhost', '', $subscription->domain) :
                $subscription->tenant_id;

            Log::info("Sending webhook to {$subscription->product}", [
                'webhook_url' => $webhookUrl,
                'tenant_id' => $tenantId,
                'realm_id' => $user->keycloak_realm_id,
            ]);

            $response = Http::timeout(30)->post($webhookUrl, [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'realm_id' => $user->keycloak_realm_id,
                'realm_url' => config('services.keycloak.url') . "/realms/{$user->keycloak_realm_id}",
                'client_id' => $clientData['client_id'],
                'client_secret' => $clientData['client_secret'],
                'tenant_id' => $tenantId,
                'domain' => $subscription->domain,
            ]);

            if ($response->successful()) {
                Log::info("Successfully notified {$subscription->product} about subscription {$subscription->id}");
            } else {
                Log::warning("Failed to notify {$subscription->product}: HTTP {$response->status()} - {$response->body()}");
            }
        } catch (\Exception $e) {
            Log::error("Exception notifying {$subscription->product}: " . $e->getMessage());
        }
    }
}
