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
        'parent_id',
        'sort_order',
        'is_front_view',
        'status',
    ];

    protected $casts = [
        'is_front_view' => 'boolean',
        'status'        => 'boolean',
        'parent_id'     => 'integer',
        'sort_order'    => 'integer',
    ];

    protected $appends = [
        'image',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('category_image')
            ->useDisk('public')
            ->singleFile();
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
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
        $media = $this->getFirstMedia('category_image');

        if ($media) {
            return $media->getUrl();
        }

        return asset('vendor/adminlte/dist/img/no-image.png');
    }
}
