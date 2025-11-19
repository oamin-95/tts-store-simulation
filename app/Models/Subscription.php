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
            // Load user relationship if not already loaded
            if (!$subscription->relationLoaded('user')) {
                $subscription->load('user');
            }

            // Dispatch event with subscription and user
            event(new SubscriptionCreated($subscription, $subscription->user));
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
