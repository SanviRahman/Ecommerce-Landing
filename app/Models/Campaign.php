<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Campaign extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;

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
        'benefits_text'     => 'array',
        'comparison_text'   => 'array',
        'old_price'         => 'integer',
        'new_price'         => 'integer',
        'enable_bulk_order' => 'boolean',
        'status'            => 'boolean',
    ];

    protected $appends = [
        'banner_image_url',
        'image_one_url',
        'image_two_url',
        'image_three_url',
        'review_image_url',
        'campaign_video_url',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('banner_image')->useDisk('public')->singleFile();
        $this->addMediaCollection('image_one')->useDisk('public')->singleFile();
        $this->addMediaCollection('image_two')->useDisk('public')->singleFile();
        $this->addMediaCollection('image_three')->useDisk('public')->singleFile();
        $this->addMediaCollection('review_image')->useDisk('public')->singleFile();
        $this->addMediaCollection('campaign_video')->useDisk('public')->singleFile();
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'campaign_product')
            ->withPivot(['campaign_price', 'sort_order', 'is_default'])
            ->withTimestamps();
    }

    public function getBannerImageUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('banner_image') ?: asset('vendor/adminlte/dist/img/no-image.png');
    }

    public function getImageOneUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('image_one') ?: asset('vendor/adminlte/dist/img/no-image.png');
    }

    public function getImageTwoUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('image_two') ?: asset('vendor/adminlte/dist/img/no-image.png');
    }

    public function getImageThreeUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('image_three') ?: asset('vendor/adminlte/dist/img/no-image.png');
    }

    public function getReviewImageUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('review_image') ?: asset('vendor/adminlte/dist/img/no-image.png');
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
