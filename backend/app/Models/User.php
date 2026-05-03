<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'identity_uuid', 'identity_synced_at', 'last_synced_at', 'display_name', 'avatar_url', 'subscription_tier', 'mother_customer_id', 'mother_total_orders', 'total_xp', 'level', 'outfit_state', 'pet_species', 'pet_nickname', 'pet_onboarded_at', 'partner_share_token', 'partner_share_enabled_at', 'push_opted_in'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'identity_synced_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'pet_onboarded_at' => 'datetime',
            'partner_share_enabled_at' => 'datetime',
            'push_opted_in' => 'boolean',
            'mother_first_order_at' => 'datetime',
            'mother_last_order_at' => 'datetime',
            'mother_total_orders' => 'integer',
            'total_xp' => 'integer',
            'level' => 'integer',
            'outfit_state' => 'array',
            'password' => 'hashed',
        ];
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(Cycle::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }
}
