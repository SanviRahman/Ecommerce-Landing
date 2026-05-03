<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrackingPixel extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'platform',
        'name',
        'pixel_id',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeMeta($query)
    {
        return $query->where('platform', 'meta');
    }

    public function scopeTiktok($query)
    {
        return $query->where('platform', 'tiktok');
    }

    public function scopeGtm($query)
    {
        return $query->where('platform', 'gtm');
    }

    public function scopeGoogleAnalytics($query)
    {
        return $query->where('platform', 'google_analytics');
    }
}