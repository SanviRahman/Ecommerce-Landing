<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Courier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Courier $courier) {
            if (empty($courier->code)) {
                $courier->code = Str::slug($courier->name, '_');
            }

            $courier->code = strtolower(Str::slug($courier->code, '_'));
        });

        static::updating(function (Courier $courier) {
            if (empty($courier->code)) {
                $courier->code = Str::slug($courier->name, '_');
            }

            $courier->code = strtolower(Str::slug($courier->code, '_'));
        });
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}