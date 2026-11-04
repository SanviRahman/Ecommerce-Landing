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
        'script_code',
        'status',
        'capi_enabled',
        'capi_access_token',
        'capi_test_event_code',
    ];

    protected $hidden = ['capi_access_token'];

    protected $casts = [
        'status' => 'boolean',
        'capi_enabled' => 'boolean',
        'capi_access_token' => 'encrypted',
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