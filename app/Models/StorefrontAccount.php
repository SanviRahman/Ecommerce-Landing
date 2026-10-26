<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Customer logins never authenticate into the separate admin/employee guard. */
final class StorefrontAccount extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'phone', 'address'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean'];
    }
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'storefront_account_id');
    }
}
