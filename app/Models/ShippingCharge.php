<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingCharge extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'area_name',
        'delivery_charge',
        'status',
    ];

    protected $casts = [
        'delivery_charge' => 'integer',
        'status' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}