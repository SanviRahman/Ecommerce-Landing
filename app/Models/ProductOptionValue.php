<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOptionValue extends Model
{
    protected $fillable = ['product_option_id', 'code', 'label', 'swatch_hex', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function option()
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }
}
