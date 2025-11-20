<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\GroupManagementController;
use Illuminate\Support\Facades\Route;

// Redirect root to login
Route::get('/', function () {
    return redirect('/login');
});

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard.tenant');
    })->name('dashboard');

    // Subscription routes
    Route::post('/subscribe', [SubscriptionController::class, 'subscribe'])->name('subscribe');
    Route::post('/subscriptions/{id}/cancel', [SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');

    // User Management Routes
    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::get('/create', [UserManagementController::class, 'create'])->name('create');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::get('/{userId}/edit', [UserManagementController::class, 'edit'])->name('edit');
        Route::put('/{userId}', [UserManagementController::class, 'update'])->name('update');
        Route::delete('/{userId}', [UserManagementController::class, 'destroy'])->name('destroy');
        Route::post('/{userId}/reset-password', [UserManagementController::class, 'resetPassword'])->name('reset-password');
        Route::post('/{userId}/assign-group', [UserManagementController::class, 'assignGroup'])->name('assign-group');
        Route::delete('/{userId}/groups/{groupId}', [UserManagementController::class, 'removeGroup'])->name('remove-group');
    });

    // Group Management Routes
    Route::prefix('groups')->name('groups.')->group(function () {
        Route::get('/', [GroupManagementController::class, 'index'])->name('index');
        Route::get('/create', [GroupManagementController::class, 'create'])->name('create');
        Route::post('/', [GroupManagementController::class, 'store'])->name('store');
        Route::get('/{groupId}/edit', [GroupManagementController::class, 'edit'])->name('edit');
        Route::put('/{groupId}', [GroupManagementController::class, 'update'])->name('update');
        Route::delete('/{groupId}', [GroupManagementController::class, 'destroy'])->name('destroy');
        Route::get('/{groupId}/members', [GroupManagementController::class, 'members'])->name('members');
    });

    // Product Details Routes
    Route::get('/products/{product}', [App\Http\Controllers\ProductController::class, 'show'])->name('products.show');
});
