<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'normalized_name',
        'phone',
    ];

    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            $customer->name = trim(preg_replace('/\s+/u', ' ', (string) $customer->name));
            $customer->normalized_name = static::normalizeName($customer->name);
            $customer->phone = preg_replace('/\D+/', '', (string) $customer->phone) ?: '';
        });
    }

    public static function normalizeName(?string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $name));

        return mb_strtolower($name, 'UTF-8');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
