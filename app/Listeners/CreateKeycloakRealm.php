<?php

namespace App\Listeners;

use App\Events\SubscriptionCreated;
use App\Jobs\CreateTenantKeycloakRealm;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CreateKeycloakRealm implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(SubscriptionCreated $event): void
    {
        Log::info("SubscriptionCreated event triggered for subscription {$event->subscription->id}");

        // Dispatch job to create Keycloak realm in background
        CreateTenantKeycloakRealm::dispatch($event->subscription, $event->user);
    }
}
