<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
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
        'help_section_status',
        'order_section_status',

        'status',
        'is_default',
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
        'help_section_status'       => 'boolean',
        'order_section_status'      => 'boolean',

        'status'                    => 'boolean',
        'is_default'                => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('banner_image')->singleFile();
        $this->addMediaCollection('image_one')->singleFile();
        $this->addMediaCollection('image_two')->singleFile();
        $this->addMediaCollection('image_three')->singleFile();
        $this->addMediaCollection('review_image')->singleFile();
        $this->addMediaCollection('campaign_video')->singleFile();

        // Hero section multiple slider images.
        $this->addMediaCollection('hero_slider_images');

        // Campaign product gallery images.
        $this->addMediaCollection('campaign_product_gallery');
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

    public function getHeroSliderImageUrlsAttribute(): array
    {
        return $this->getMedia('hero_slider_images')
            ->map(fn ($media) => $media->getUrl())
            ->values()
            ->toArray();
    }

    public function getCampaignProductGalleryUrlsAttribute(): array
    {
        return $this->getMedia('campaign_product_gallery')
            ->map(fn ($media) => $media->getUrl())
            ->values()
            ->toArray();
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


    public function faqs()
    {
        return $this->hasMany(Faq::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class)
            ->latest();
    }


    /**
     * Toggle campaign-level default status.
     *
     * Business rule:
     * - When turning ON, this campaign becomes default and all other campaigns
     *   including soft-deleted ones are cleared.
     * - When turning OFF, this campaign loses default status and the system may
     *   temporarily have no default campaign until an admin selects another one.
     *
     * The transaction + row lock protects the singleton default rule when two
     * admins click almost at the same time.
     */
    public static function toggleDefaultCampaign(int $campaignId, bool $makeDefault): self
    {
        return DB::transaction(function () use ($campaignId, $makeDefault) {
            $campaign = self::query()
                ->whereKey($campaignId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $makeDefault) {
                if ($campaign->is_default) {
                    $campaign->forceFill(['is_default' => false])->save();
                }

                return $campaign->refresh();
            }

            self::withTrashed()
                ->where('is_default', true)
                ->whereKeyNot($campaign->id)
                ->update(['is_default' => false]);

            if (! $campaign->is_default) {
                $campaign->forceFill(['is_default' => true])->save();
            }

            return $campaign->refresh();
        });
    }

    /**
     * Backward-compatible helper for older code that only sets default ON.
     */
    public static function setDefaultCampaign(int $campaignId): self
    {
        return self::toggleDefaultCampaign($campaignId, true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope only campaign-level default landing pages.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Resolve the campaign that should be displayed on the root homepage (/).
     *
     * Priority:
     * 1. Active + campaign-level default campaign
     * 2. Latest active campaign fallback, so the homepage never breaks if no default is selected
     *
     * Usage in public/root controller:
     * $campaign = Campaign::resolveHomepageCampaign(['products', 'categories', 'brands', 'faqs', 'reviews']);
     */
    public static function resolveHomepageCampaign(array $with = []): ?self
    {
        $defaultCampaign = self::query()
            ->active()
            ->default()
            ->with($with)
            ->latest('id')
            ->first();

        if ($defaultCampaign) {
            return $defaultCampaign;
        }

        return self::query()
            ->active()
            ->with($with)
            ->latest('id')
            ->first();
    }

    /**
     * Same as resolveHomepageCampaign(), but aborts when no active campaign exists.
     */
    public static function resolveHomepageCampaignOrFail(array $with = []): self
    {
        $campaign = self::resolveHomepageCampaign($with);

        abort_if(! $campaign, 404, 'No active default campaign found.');

        return $campaign;
    }
}
