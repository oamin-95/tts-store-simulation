<?php

namespace App\Models;

use App\Events\SubscriptionCreated;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product',
        'tenant_id',
        'domain',
        'url',
        'is_active',
        'meta',
        'status',
        'keycloak_realm_id',
        'keycloak_client_id',
        'keycloak_client_secret',
        'keycloak_client_uuid',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($subscription) {
            // Dispatch event AFTER database transaction commits
            // This ensures all subscription data (including domain) is saved
            \Illuminate\Support\Facades\DB::afterCommit(function () use ($subscription) {
                // Refresh to get latest data from database
                $subscription->refresh();

                // Load user relationship if not already loaded
                if (!$subscription->relationLoaded('user')) {
                    $subscription->load('user');
                }

                // Dispatch event with subscription and user
                event(new SubscriptionCreated($subscription, $subscription->user));
            });
        });
    }

    /**
     * Get the user that owns the subscription.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
