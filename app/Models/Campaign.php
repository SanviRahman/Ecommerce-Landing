<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Campaign extends Model implements HasMedia
{
    use SoftDeletes;
    use InteractsWithMedia;

    protected $fillable = [
        'title',
        'slug',
        'campaign_type',
        'short_description',
        'full_description',
        'offer_text',
        'embed_video_url',

        'benefits_text',
        'comparison_text',
        'section_titles',
        'service_items',
        'help_content',

        'button_text',
        'order_form_title',
        'order_form_subtitle',
        'enable_bulk_order',

        'hero_section_status',
        'benefits_section_status',
        'category_section_status',
        'product_section_status',
        'comparison_section_status',
        'service_section_status',
        'review_section_status',
        'gallery_section_status',
        'faq_section_status',
        'order_section_status',

        'status',
        'meta_title',
        'meta_description',
        'hero_whatsapp',
        'hero_phone',
    ];

    protected $casts = [
        'benefits_text'             => 'array',
        'comparison_text'           => 'array',
        'section_titles'            => 'array',
        'service_items'             => 'array',
        'help_content'              => 'array',

        'enable_bulk_order'         => 'boolean',

        'hero_section_status'       => 'boolean',
        'benefits_section_status'   => 'boolean',
        'category_section_status'   => 'boolean',
        'product_section_status'    => 'boolean',
        'comparison_section_status' => 'boolean',
        'service_section_status'    => 'boolean',
        'review_section_status'     => 'boolean',
        'gallery_section_status'    => 'boolean',
        'faq_section_status'        => 'boolean',
        'order_section_status'      => 'boolean',

        'status'                    => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('banner_image')->singleFile();
        $this->addMediaCollection('image_one')->singleFile();
        $this->addMediaCollection('image_two')->singleFile();
        $this->addMediaCollection('image_three')->singleFile();
        $this->addMediaCollection('review_image')->singleFile();
        $this->addMediaCollection('campaign_video')->singleFile();
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'campaign_product')
            ->withPivot(['campaign_price', 'sort_order', 'is_default'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'campaign_category')
            ->withPivot(['sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function brands()
    {
        return $this->belongsToMany(Brand::class, 'campaign_brand')
            ->withPivot(['sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function getBannerImageUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('banner_image') ?: null;
    }

    public function getImageOneUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('image_one') ?: null;
    }

    public function getImageTwoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('image_two') ?: null;
    }

    public function getImageThreeUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('image_three') ?: null;
    }

    public function getReviewImageUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('review_image') ?: null;
    }

    public function getCampaignVideoUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('campaign_video') ?: null;
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
