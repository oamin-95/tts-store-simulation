<?php

use App\Http\Controllers\TenantController;
use App\Http\Controllers\Api\KeycloakIntegrationController;
use App\Http\Controllers\Api\SiteManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Tenant Management Routes
Route::prefix('tenants')->group(function () {
    Route::get('/', [TenantController::class, 'index']);
    Route::post('/', [TenantController::class, 'store']);
    Route::get('/{id}', [TenantController::class, 'show']);
    Route::put('/{id}', [TenantController::class, 'update']);
    Route::delete('/{id}', [TenantController::class, 'destroy']);

    // Product Management
    Route::post('/{id}/products', [TenantController::class, 'addProduct']);
    Route::post('/{id}/products/sync-roles', [TenantController::class, 'syncProductRoles']);
});

// Keycloak Integration Routes
Route::prefix('keycloak')->group(function () {
    Route::post('/sync-roles', [KeycloakIntegrationController::class, 'syncRoles']);
    Route::get('/realm-info', [KeycloakIntegrationController::class, 'getRealmInfo']);
    Route::post('/get-realm', [KeycloakIntegrationController::class, 'getRealmByTenantId']);
});

// Site Management Routes (Kayan ERP)
Route::prefix('sites')->group(function () {
    // List all sites
    Route::get('/', [SiteManagementController::class, 'listSites']);

    // Site operations
    Route::get('/{userId}', [SiteManagementController::class, 'getSite']);
    Route::post('/{userId}/start', [SiteManagementController::class, 'startSite']);
    Route::post('/{userId}/stop', [SiteManagementController::class, 'stopSite']);
    Route::post('/{userId}/restart', [SiteManagementController::class, 'restartSite']);

    // Bulk operations
    Route::post('/start-all', [SiteManagementController::class, 'startAllSites']);
    Route::post('/stop-all', [SiteManagementController::class, 'stopAllSites']);
});

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'SaaS Marketplace API',
        'timestamp' => now()->toIso8601String(),
    ]);
});
