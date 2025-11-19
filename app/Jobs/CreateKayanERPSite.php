<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class CreateKayanERPSite implements ShouldQueue
{
    use Queueable;

    public $timeout = 900; // 15 minutes timeout (ERPNext installation takes time)
    public $tries = 1; // Only try once

    protected $subscription;
    protected $user;
    protected $adminPassword;

    /**
     * Create a new job instance.
     */
    public function __construct(Subscription $subscription, $user, $adminPassword)
    {
        $this->subscription = $subscription;
        $this->user = $user;
        $this->adminPassword = $adminPassword;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting Kayan ERP site creation for user {$this->user->id}");

        // Update status to processing
        $this->subscription->update(['status' => 'processing']);

        try {
            // Path to Python script
            $scriptPath = '/home/vboxuser/kayan-erp/scripts/create_tenant_site.py';

            // Execute Python script to create isolated site
            // Note: stderr contains progress messages, stdout contains JSON result
            $command = sprintf(
                'python3 %s %d %s %s %s',
                escapeshellarg($scriptPath),
                $this->user->id,
                escapeshellarg($this->user->company_name),
                escapeshellarg($this->user->email),
                escapeshellarg($this->adminPassword)
            );

            Log::info("Executing command: {$command}");

            // Execute with timeout
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            // Parse JSON response
            $result = json_decode(implode("\n", $output), true);

            Log::info("Command result", ['result' => $result, 'return_code' => $returnCode]);

            if (!$result || !$result['success']) {
                $errorMsg = $result['message'] ?? 'Failed to create Kayan ERP site';
                throw new \Exception($errorMsg);
            }

            // Refresh subscription to get latest data (including keycloak_realm_id from CreateTenantKeycloakRealm)
            $this->subscription->refresh();

            // Get current meta or initialize as array
            $existingMeta = is_string($this->subscription->meta)
                ? (json_decode($this->subscription->meta, true) ?? [])
                : ($this->subscription->meta ?? []);

            // Update subscription with site info, merging with existing meta
            $this->subscription->update([
                'tenant_id' => $result['site_name'],
                'domain' => $result['domain'],
                'url' => $result['url'],
                'status' => 'active',
                'is_active' => true,
                'meta' => json_encode(array_merge($existingMeta, [
                    'site_name' => $result['site_name'],
                    'admin_email' => $result['admin_email'],
                    'admin_password' => $this->adminPassword,
                    'company_name' => $this->user->company_name,
                    'isolated_site' => true,
                    'created_at' => now()->toISOString(),
                ])),
            ]);

            Log::info("Kayan ERP site created successfully for user {$this->user->id}");

            // Get realm_id from the subscription to pass to Keycloak integration
            $this->subscription->refresh();
            $currentMeta = is_string($this->subscription->meta)
                ? (json_decode($this->subscription->meta, true) ?? [])
                : ($this->subscription->meta ?? []);

            $realmId = $currentMeta['keycloak_realm_id'] ?? null;

            if ($realmId) {
                Log::info("Starting Keycloak integration for site on port {$result['port']} with realm {$realmId}");

                // Call Keycloak integration script using Frappe Python environment
                $integrationScriptPath = '/home/vboxuser/kayan-erp/scripts/setup_keycloak_integration.py';
                $frappePython = '/home/vboxuser/kayan-erp/env/bin/python';
                $integrationCommand = sprintf(
                    '%s %s %d %s',
                    $frappePython,
                    escapeshellarg($integrationScriptPath),
                    $result['port'],
                    escapeshellarg($realmId)
                );

                Log::info("Executing Keycloak integration: {$integrationCommand}");

                $integrationOutput = [];
                $integrationReturnCode = 0;
                exec($integrationCommand, $integrationOutput, $integrationReturnCode);

                $integrationResult = json_decode(implode("\n", $integrationOutput), true);

                Log::info("Keycloak integration result", [
                    'result' => $integrationResult,
                    'return_code' => $integrationReturnCode
                ]);

                // Update meta with Keycloak integration status
                if ($integrationResult && $integrationResult['success']) {
                    $this->subscription->refresh();
                    $updatedMeta = is_string($this->subscription->meta)
                        ? (json_decode($this->subscription->meta, true) ?? [])
                        : ($this->subscription->meta ?? []);

                    $updatedMeta['keycloak_client_id'] = $integrationResult['client_id'];
                    $updatedMeta['keycloak_integrated'] = true;
                    $updatedMeta['keycloak_roles_synced'] = $integrationResult['roles_synced'] ?? 0;

                    $this->subscription->update(['meta' => json_encode($updatedMeta)]);

                    Log::info("Keycloak integration completed successfully for user {$this->user->id}");
                } else {
                    Log::warning("Keycloak integration failed but site creation succeeded", [
                        'error' => $integrationResult['message'] ?? 'Unknown error',
                        'user_id' => $this->user->id
                    ]);
                }
            } else {
                Log::warning("No realm_id found in subscription meta, skipping Keycloak integration");
            }

        } catch (\Exception $e) {
            Log::error("Failed to create Kayan ERP site for user {$this->user->id}: {$e->getMessage()}");

            // Update subscription status to failed
            $this->subscription->update([
                'status' => 'failed',
                'meta' => json_encode([
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toISOString(),
                ]),
            ]);

            // Re-throw to mark job as failed
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
