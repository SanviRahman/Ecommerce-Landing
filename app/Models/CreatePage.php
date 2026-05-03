<?php

namespace App\Models;

use App\Traits\HasMediaTrait;
use App\Traits\HasMetadataTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CreatePage extends Model implements HasMedia
{
    use InteractsWithMedia, HasMediaTrait, HasMetadataTrait, SoftDeletes;

    protected $fillable = [
        'page_name',
        'slug',
        'description',
        'meta_title',
        'meta_description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('page_banner')->singleFile();
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function getBannerAttribute(): string
    {
        return $this->getFirstMediaUrlOrPlaceholder('page_banner');
    }
}