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
        'footer_youtube_url',
        'footer_facebook_url',
        'footer_instagram_url',
        'footer_linkedin_url',
        'footer_social_managed',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'footer_social_managed' => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('site_logo')->useDisk('public')->singleFile();
        $this->addMediaCollection('site_white_logo')->useDisk('public')->singleFile();
        $this->addMediaCollection('site_favicon')->useDisk('public')->singleFile();
        // Independent storefront images. Old campaign branding is not modified.
        $this->addMediaCollection('storefront_logo')->useDisk('public')->singleFile();
        $this->addMediaCollection('storefront_white_logo')->useDisk('public')->singleFile();
        $this->addMediaCollection('storefront_favicon')->useDisk('public')->singleFile();
        // Multiple approved homepage banners; independently managed from site logo.
        $this->addMediaCollection('storefront_hero_image')->useDisk('public');
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
