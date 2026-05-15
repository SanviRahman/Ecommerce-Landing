<?php
namespace App\Models;

use App\Traits\HasMediaTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Banner extends Model implements HasMedia
{
    use InteractsWithMedia, HasMediaTrait, SoftDeletes;

    protected $fillable = [
        'title',
        'position',
        'link',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'status'     => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('banner_image')
            ->useDisk('public')
            ->singleFile();
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function scopePosition($query, string $position)
    {
        return $query->where('position', $position);
    }

    public function scopeSorted($query)
    {
        return $query->orderBy('sort_order');
    }

    public function getImageAttribute(): string
    {
        return $this->getFirstMediaUrlOrPlaceholder('banner_image');
    }
}
