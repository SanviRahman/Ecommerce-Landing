<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** Customer logins never authenticate into the separate admin/employee guard. */
final class StorefrontAccount extends Authenticatable implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'address',
        'gender', 'date_of_birth', 'division', 'district', 'upazila',
    ];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'boolean', 'date_of_birth' => 'date'];
    }
    /** Customer photos are separate from the admin User::avatars collection. */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile_photo')
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'storefront_account_id');
    }
}
