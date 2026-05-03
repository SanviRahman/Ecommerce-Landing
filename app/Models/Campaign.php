<?php

namespace App\Models;

use App\Traits\HasMediaTrait;
use App\Traits\HasMetadataTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Campaign extends Model implements HasMedia
{
    use InteractsWithMedia, HasMediaTrait, HasMetadataTrait, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'campaign_type',
        'short_description',
        'full_description',
        'offer_text',
        'benefits_text',
        'comparison_text',
        'old_price',
        'new_price',
        'button_text',
        'order_form_title',
        'order_form_subtitle',
        'enable_bulk_order',
        'status',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'benefits_text' => 'array',
        'comparison_text' => 'array',
        'old_price' => 'integer',
        'new_price' => 'integer',
        'enable_bulk_order' => 'boolean',
        'status' => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('campaign_banner')->singleFile();
        $this->addMediaCollection('campaign_gallery');
        $this->addMediaCollection('campaign_review_images');
        $this->addMediaCollection('campaign_video_thumbnail')->singleFile();
        $this->addMediaCollection('campaign_middle_banner')->singleFile();
        $this->addMediaCollection('campaign_help_banner')->singleFile();
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'campaign_product')
            ->withPivot(['campaign_price', 'sort_order', 'is_default'])
            ->withTimestamps();
    }

    public function defaultProduct()
    {
        return $this->belongsToMany(Product::class, 'campaign_product')
            ->withPivot(['campaign_price', 'sort_order', 'is_default'])
            ->wherePivot('is_default', true)
            ->withTimestamps();
    }

    public function faqs()
    {
        return $this->hasMany(Faq::class)->orderBy('sort_order');
    }

    public function activeFaqs()
    {
        return $this->hasMany(Faq::class)->where('status', true)->orderBy('sort_order');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function activeReviews()
    {
        return $this->hasMany(Review::class)->where('status', true);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function bulkOrders()
    {
        return $this->hasMany(BulkOrder::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function getBannerAttribute(): string
    {
        return $this->getFirstMediaUrlOrPlaceholder('campaign_banner');
    }

    public function getMiddleBannerAttribute(): string
    {
        return $this->getFirstMediaUrlOrPlaceholder('campaign_middle_banner');
    }

    public function getHelpBannerAttribute(): string
    {
        return $this->getFirstMediaUrlOrPlaceholder('campaign_help_banner');
    }
}