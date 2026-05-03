<?php
namespace App\Models;

use App\Traits\HasMediaTrait;
use App\Traits\HasMetadataTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Product extends Model implements HasMedia
{
    use InteractsWithMedia, HasMediaTrait, HasMetadataTrait, SoftDeletes;

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'product_code',
        'purchase_price',
        'old_price',
        'new_price',
        'stock',
        'sold_quantity',
        'weight_size',
        'short_description',
        'full_description',
        'is_top_sale',
        'is_feature',
        'is_flash_sale',
        'status',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'purchase_price' => 'integer',
        'old_price'      => 'integer',
        'new_price'      => 'integer',
        'stock'          => 'integer',
        'sold_quantity'  => 'integer',
        'is_top_sale'    => 'boolean',
        'is_feature'     => 'boolean',
        'is_flash_sale'  => 'boolean',
        'status'         => 'boolean',
    ];

    protected $appends = [
        'thumbnail',
        'final_price',
        'discount_amount',
        'discount_percent',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('product_thumbnail')->singleFile();
        $this->addMediaCollection('product_gallery');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'campaign_product')
            ->withPivot(['campaign_price', 'sort_order', 'is_default'])
            ->withTimestamps();
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function bulkOrders()
    {
        return $this->hasMany(BulkOrder::class, 'product_name', 'name');
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeTopSale($query)
    {
        return $query->where('is_top_sale', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_feature', true);
    }

    public function scopeFlashSale($query)
    {
        return $query->where('is_flash_sale', true);
    }

    public function getThumbnailAttribute(): string
    {
        $media = $this->getFirstMedia('product_thumbnail');

        if ($media) {
            return url('storage/' . $media->id . '/' . $media->file_name);
        }

        return asset('vendor/adminlte/dist/img/no-image.png');
    }

    public function getFinalPriceAttribute(): int
    {
        return (int) $this->new_price;
    }

    public function getDiscountAmountAttribute(): int
    {
        if (! $this->old_price || $this->old_price <= $this->new_price) {
            return 0;
        }

        return (int) ($this->old_price - $this->new_price);
    }

    public function getDiscountPercentAttribute(): int
    {
        if (! $this->old_price || $this->old_price <= $this->new_price) {
            return 0;
        }

        return (int) round((($this->old_price - $this->new_price) / $this->old_price) * 100);
    }

    public function isInStock(int $quantity = 1): bool
    {
        return $this->stock >= $quantity;
    }
}
