<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OrderField extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'color',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'status' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrderField $field) {
            if (! $field->slug) {
                $field->slug = static::generateUniqueSlug($field->name);
            }
        });

        static::updating(function (OrderField $field) {
            if ($field->isDirty('name') && ! $field->isDirty('slug')) {
                $field->slug = static::generateUniqueSlug($field->name, $field->id);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'order_field_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'order-field-' . time();
        $slug = $base;
        $count = 1;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base . '-' . $count;
            $count++;
        }

        return $slug;
    }
}
