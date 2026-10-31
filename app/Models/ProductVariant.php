<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id', 'sku', 'option_signature', 'price_override', 'old_price_override',
        'stock', 'is_default', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'price_override' => 'integer', 'old_price_override' => 'integer',
        'stock' => 'integer', 'is_default' => 'boolean',
        'is_active' => 'boolean', 'sort_order' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function optionValues()
    {
        return $this->belongsToMany(
            ProductOptionValue::class, 'product_variant_values',
            'product_variant_id', 'product_option_value_id'
        )->withPivot('product_option_id');
    }
}
