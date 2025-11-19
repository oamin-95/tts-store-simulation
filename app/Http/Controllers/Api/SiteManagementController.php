<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SiteManagementController extends Controller
{
    /**
     * Get list of all sites from registry
     */
    public function listSites()
    {
        $registryFile = '/home/vboxuser/frappe-bench/config/site_registry.json';

        if (!file_exists($registryFile)) {
            return response()->json([
                'success' => false,
                'message' => 'Registry file not found',
                'sites' => []
            ], 404);
        }

        $registry = json_decode(file_get_contents($registryFile), true);

        // Add status check for each site
        $sites = [];
        foreach ($registry as $userId => $siteInfo) {
            $pid = $siteInfo['pid'] ?? null;
            $isRunning = false;

            if ($pid) {
                // Check if process is running
                exec("ps -p {$pid} > /dev/null 2>&1", $output, $returnCode);
                $isRunning = ($returnCode === 0);
            }

            $sites[] = [
                'user_id' => $userId,
                'site_name' => $siteInfo['site_name'] ?? null,
                'port' => $siteInfo['port'] ?? null,
                'url' => "http://{$siteInfo['site_name']}:{$siteInfo['port']}",
                'admin_email' => $siteInfo['admin_email'] ?? 'Administrator',
                'admin_password' => $siteInfo['admin_password'] ?? null,
                'status' => $isRunning ? 'running' : 'stopped',
                'pid' => $pid,
                'created_at' => $siteInfo['created_at'] ?? null
            ];
        }

        return response()->json([
            'success' => true,
            'sites' => $sites,
            'total' => count($sites)
        ]);
    }

    /**
     * Get site info for specific user
     */
    public function getSite($userId)
    {
        $registryFile = '/home/vboxuser/frappe-bench/config/site_registry.json';

        if (!file_exists($registryFile)) {
            return response()->json([
                'success' => false,
                'message' => 'Registry file not found'
            ], 404);
        }

        $registry = json_decode(file_get_contents($registryFile), true);

        if (!isset($registry[$userId])) {
            return response()->json([
                'success' => false,
                'message' => 'Site not found for this user'
            ], 404);
        }

        $siteInfo = $registry[$userId];
        $pid = $siteInfo['pid'] ?? null;
        $isRunning = false;

        if ($pid) {
            exec("ps -p {$pid} > /dev/null 2>&1", $output, $returnCode);
            $isRunning = ($returnCode === 0);
        }

        return response()->json([
            'success' => true,
            'site' => [
                'user_id' => $userId,
                'site_name' => $siteInfo['site_name'] ?? null,
                'port' => $siteInfo['port'] ?? null,
                'url' => "http://{$siteInfo['site_name']}:{$siteInfo['port']}",
                'admin_email' => $siteInfo['admin_email'] ?? 'Administrator',
                'admin_password' => $siteInfo['admin_password'] ?? null,
                'status' => $isRunning ? 'running' : 'stopped',
                'pid' => $pid,
                'created_at' => $siteInfo['created_at'] ?? null
            ]
        ]);
    }

    /**
     * Start a specific site
     */
    public function startSite(Request $request, $userId)
    {
        $scriptPath = '/home/vboxuser/frappe-bench/scripts/manage_sites.py';

        $command = sprintf(
            'python3 %s start %s 2>&1',
            escapeshellarg($scriptPath),
            escapeshellarg($userId)
        );

        Log::info("Starting site for user {$userId}: {$command}");

        exec($command, $output, $returnCode);

        return response()->json([
            'success' => $returnCode === 0,
            'message' => $returnCode === 0 ? 'Site started successfully' : 'Failed to start site',
            'output' => implode("\n", $output)
        ]);
    }

    /**
     * Stop a specific site
     */
    public function stopSite(Request $request, $userId)
    {
        $scriptPath = '/home/vboxuser/frappe-bench/scripts/manage_sites.py';

        $command = sprintf(
            'python3 %s stop %s 2>&1',
            escapeshellarg($scriptPath),
            escapeshellarg($userId)
        );

        Log::info("Stopping site for user {$userId}: {$command}");

        exec($command, $output, $returnCode);

        return response()->json([
            'success' => $returnCode === 0,
            'message' => $returnCode === 0 ? 'Site stopped successfully' : 'Failed to stop site',
            'output' => implode("\n", $output)
        ]);
    }

    /**
     * Restart a specific site
     */
    public function restartSite(Request $request, $userId)
    {
        $scriptPath = '/home/vboxuser/frappe-bench/scripts/manage_sites.py';

        $command = sprintf(
            'python3 %s restart %s 2>&1',
            escapeshellarg($scriptPath),
            escapeshellarg($userId)
        );

        Log::info("Restarting site for user {$userId}: {$command}");

        exec($command, $output, $returnCode);

        return response()->json([
            'success' => $returnCode === 0,
            'message' => $returnCode === 0 ? 'Site restarted successfully' : 'Failed to restart site',
            'output' => implode("\n", $output)
        ]);
    }

    /**
     * Start all sites
     */
    public function startAllSites()
    {
        $scriptPath = '/home/vboxuser/frappe-bench/scripts/start_sites_from_registry.py';

        $command = sprintf('python3 %s 2>&1', escapeshellarg($scriptPath));

        Log::info("Starting all sites: {$command}");

        exec($command, $output, $returnCode);

        return response()->json([
            'success' => $returnCode === 0,
            'message' => $returnCode === 0 ? 'All sites started successfully' : 'Failed to start all sites',
            'output' => implode("\n", $output)
        ]);
    }

    /**
     * Stop all sites
     */
    public function stopAllSites()
    {
        $scriptPath = '/home/vboxuser/frappe-bench/scripts/manage_sites.py';

        $command = sprintf('python3 %s stop 2>&1', escapeshellarg($scriptPath));

        Log::info("Stopping all sites: {$command}");

        exec($command, $output, $returnCode);

        return response()->json([
            'success' => $returnCode === 0,
            'message' => $returnCode === 0 ? 'All sites stopped successfully' : 'Failed to stop all sites',
            'output' => implode("\n", $output)
        ]);
    }
}
