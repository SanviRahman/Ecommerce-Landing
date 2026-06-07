<?php

namespace App\Models;use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class OrderItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'unit_price',
        'quantity',
        'discount_amount',
        'total_price',
    ];

    protected $casts = [
        'order_id'        => 'integer',
        'product_id'      => 'integer',
        'unit_price'      => 'decimal:2',
        'quantity'        => 'integer',
        'discount_amount' => 'decimal:2',
        'total_price'     => 'decimal:2',
    ];

    protected $appends = [
        'product_image_url',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getProductImageUrlAttribute(): ?string
    {
        $product = $this->relationLoaded('product')
            ? $this->product
            : $this->product()->first();

        if (! $product) {
            return null;
        }

        foreach ([
            'image_url',
            'thumbnail_url',
            'photo_url',
            'product_image_url',
            'image',
            'photo',
            'thumbnail',
            'thumb',
            'product_image',
            'product_photo',
            'main_image',
            'featured_image',
            'featured_photo',
            'image_path',
            'photo_path',
            'thumbnail_path',
            'picture',
            'avatar',
        ] as $attribute) {
            try {
                $value = $product->{$attribute} ?? null;
            } catch (\Throwable $e) {
                $value = null;
            }

            if (is_array($value)) {
                $value = collect($value)->filter()->first();
            }

            if (is_string($value) && str_starts_with(trim($value), '[')) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $value = collect($decoded)->filter()->first();
                }
            }

            if ($url = $this->normalizeImagePath(is_string($value) ? $value : null)) {
                return $url;
            }
        }

        if (method_exists($product, 'getFirstMediaUrl')) {
            foreach ([
                'product_image',
                'product_images',
                'product_thumbnail',
                'product_photo',
                'product_gallery',
                'product',
                'products',
                'image',
                'images',
                'photo',
                'photos',
                'thumbnail',
                'thumb',
                'gallery',
                'main_image',
                'featured_image',
                'default',
            ] as $collection) {
                try {
                    $url = $product->getFirstMediaUrl($collection);
                    if ($url) {
                        return $url;
                    }
                } catch (\Throwable $e) {
                    // Continue checking other media collections.
                }
            }

            try {
                $url = $product->getFirstMediaUrl();
                if ($url) {
                    return $url;
                }
            } catch (\Throwable $e) {
                // No default media collection available.
            }
        }

        return null;
    }

    private function normalizeImagePath(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) || str_starts_with($value, 'data:') || str_starts_with($value, '//')) {
            return $value;
        }

        $value = ltrim($value, '/');

        if (str_starts_with($value, 'public/')) {
            return Storage::url(substr($value, 7));
        }

        if (str_starts_with($value, 'storage/app/public/')) {
            return Storage::url(substr($value, 19));
        }

        if (str_starts_with($value, 'storage/')) {
            return asset($value);
        }

        return Storage::url($value);
    }
}
