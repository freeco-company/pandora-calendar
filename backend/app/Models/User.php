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

#[Fillable(['name', 'email', 'password', 'identity_uuid', 'identity_synced_at', 'mother_customer_id', 'mother_total_orders'])]
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
            'mother_first_order_at' => 'datetime',
            'mother_last_order_at' => 'datetime',
            'mother_total_orders' => 'integer',
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
}
