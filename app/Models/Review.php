<?php

namespace App\Models;

use App\Traits\HasMediaTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Review extends Model implements HasMedia
{
    use InteractsWithMedia, HasMediaTrait, SoftDeletes;

    protected $fillable = [
        'campaign_id',
        'customer_name',
        'location',
        'rating',
        'review_text',
        'social_link',
        'status',
    ];

    protected $casts = [
        'rating' => 'integer',
        'status' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Appended Image Accessors
    |--------------------------------------------------------------------------
    | Frontend home.blade.php image helper image_url / review_image_url / photo_url
    | check kore. Tai sob accessor same review customer image return korbe.
    */
    protected $appends = [
        'customer_image',
        'image_url',
        'review_image_url',
        'photo_url',
    ];

    /*
    |--------------------------------------------------------------------------
    | Media Collections
    |--------------------------------------------------------------------------
    */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('review_customer_image')->singleFile();
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */
    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */
    public function getCustomerImageAttribute(): string
    {
        return $this->getFirstMediaUrl('review_customer_image')
            ?: asset('vendor/adminlte/dist/img/user2-160x160.jpg');
    }

    public function getImageUrlAttribute(): string
    {
        return $this->customer_image;
    }

    public function getReviewImageUrlAttribute(): string
    {
        return $this->customer_image;
    }

    public function getPhotoUrlAttribute(): string
    {
        return $this->customer_image;
    }
}