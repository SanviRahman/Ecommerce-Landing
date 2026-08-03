<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourierAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'base_url',
        'api_key',
        'secret_key',
        'auth_username',
        'auth_password',
        'token',
        'refresh_token',
        'token_type',
        'token_expires_at',
        'settings',
        'is_default',
        'status',
    ];

    protected $hidden = [
        'secret_key',
        'auth_password',
        'token',
        'refresh_token',
    ];

    protected $casts = [
        'auth_password' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'settings' => 'array',
        'is_default' => 'boolean',
        'status' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public static function defaultActive(): ?self
    {
        return static::query()
            ->active()
            ->default()
            ->latest()
            ->first();
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $key, $default);
    }

    public function hasStoredSecretKey(): bool
    {
        return filled($this->getRawOriginal('secret_key'));
    }

    public function hasStoredAuthPassword(): bool
    {
        return filled($this->getRawOriginal('auth_password'));
    }
}