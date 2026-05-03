<?php

namespace App\Models;

use App\Traits\HasMediaTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class SiteSetting extends Model implements HasMedia
{
    use InteractsWithMedia, HasMediaTrait, SoftDeletes;

    protected $fillable = [
        'website_name',
        'phone',
        'hotline',
        'whatsapp_number',
        'messenger_link',
        'email',
        'address',
        'top_headline',
        'footer_text',
        'business_short_description',
        'working_hours',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('site_logo')->singleFile();
        $this->addMediaCollection('site_white_logo')->singleFile();
        $this->addMediaCollection('site_favicon')->singleFile();
    }

    public function getLogoAttribute(): string
    {
        return $this->getFirstMediaUrlOrPlaceholder('site_logo');
    }

    public function getWhiteLogoAttribute(): string
    {
        return $this->getFirstMediaUrlOrPlaceholder('site_white_logo');
    }

    public function getFaviconAttribute(): string
    {
        return $this->getFirstMediaUrlOrPlaceholder('site_favicon');
    }
}