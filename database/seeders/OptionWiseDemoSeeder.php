<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Disposable development seed only: one hidden inventory balance per product. */
final class OptionWiseDemoSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            'color' => ['Red' => '#c63a3a', 'Black' => '#101010'],
            'size' => ['M' => null, 'L' => null, 'XL' => null],
            'weight' => ['5g' => null, '10g' => null],
            'piece_count' => ['1 Piece' => null, '2 Pieces' => null, '3 Pieces' => null],
        ];
        $map = [];
        foreach ($definitions as $code => $labels) {
            $option = ProductOption::firstOrCreate(['code' => $code],
                ['name' => ucwords(str_replace('_',' ',$code)), 'input_type'=>'select', 'is_active'=>true]);
            $map[$code] = [];
            foreach ($labels as $label => $swatch) {
                $value = ProductOptionValue::firstOrCreate(
                    ['product_option_id' => $option->id, 'code' => Str::slug($label, '_')],
                    ['label' => $label, 'swatch_hex' => $swatch, 'is_active' => true]
                );
                $map[$code][$label] = $value->id;
            }
        }
        foreach (Product::query()->orderBy('id')->get() as $product) {
            // No customer-facing SKU matrix: this single internal row is needed only
            // for the existing movement ledger/confirmation infrastructure.
            ProductVariant::firstOrCreate(['product_id' => $product->id, 'option_signature' => 'default'], [
                'sku' => 'INTERNAL-PRODUCT-' . $product->id,
                'stock' => (int)$product->stock, 'is_default' => true, 'is_active' => true,
            ]);
            $product->forceFill(['inventory_version' => 1, 'has_variants' => false])->save();
            $variant = $product->variants()->where('is_default', true)->firstOrFail();
            DB::table('inventory_movements')->insertOrIgnore([
                'product_variant_id' => $variant->id, 'order_item_id' => null,
                'event_uuid' => (string) Str::uuid(),
                'operation_key' => 'opening:v1:product:' . $product->id,
                'kind' => 'opening', 'quantity_delta' => (int)$product->stock,
                'stock_before' => 0, 'stock_after' => (int)$product->stock,
                'actor_id' => null, 'reason' => 'Disposable seeded opening inventory',
                'metadata' => '{}', 'created_at' => now(),
            ]);
        }
        // One demo product showcases independent Color/Size/Weight and Piece prices.
        if ($sample = Product::query()->orderBy('id')->first()) {
            $choices = [
                $map['color']['Red'], $map['color']['Black'],
                $map['size']['M'], $map['size']['L'], $map['size']['XL'],
                $map['weight']['5g'], $map['weight']['10g'],
                $map['piece_count']['1 Piece'], $map['piece_count']['2 Pieces'],
            ];
            foreach ($choices as $id) DB::table('product_option_choices')->insertOrIgnore([
                'product_id' => $sample->id, 'product_option_value_id' => $id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ([1 => $map['piece_count']['1 Piece'], 2 => $map['piece_count']['2 Pieces']] as $packs => $id) {
                DB::table('product_piece_prices')->updateOrInsert(
                    ['product_id' => $sample->id, 'product_option_value_id' => $id],
                    ['price' => (int)$sample->new_price * $packs, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }
}
