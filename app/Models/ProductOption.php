<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOption extends Model
{
    protected $fillable = ['code', 'name', 'input_type', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function values()
    {
        return $this->hasMany(ProductOptionValue::class)->orderBy('sort_order')->orderBy('id');
    }
}
