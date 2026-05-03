<?php

namespace App\Models;

use App\Traits\HasMediaTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Category extends Model implements HasMedia
{
    use InteractsWithMedia, HasMediaTrait, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'is_front_view',
        'status',
    ];

    protected $casts = [
        'is_front_view' => 'boolean',
        'status' => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('category_image')->singleFile();
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function activeProducts()
    {
        return $this->hasMany(Product::class)->where('status', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopeFrontView($query)
    {
        return $query->where('is_front_view', true);
    }

    public function getImageAttribute(): string
    {
        return $this->getFirstMediaUrlOrPlaceholder('category_image');
    }
}