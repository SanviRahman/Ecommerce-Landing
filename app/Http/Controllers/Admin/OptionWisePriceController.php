<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductOption;
use App\Services\Catalog\VariantCatalogManager;
use App\Services\Catalog\OptionWiseSelections;
use App\Support\P33CatalogAdminAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Leatherket-inspired product choices and Piece Count-only prices: NO SKU matrix. */
final class OptionWisePriceController extends Controller
{
    public function save(Request $request, Product $product, VariantCatalogManager $catalog): RedirectResponse
    {
        P33CatalogAdminAccess::assertAllowed();
        $data = $request->validate([
            'options' => ['required', 'array:color,size,weight,piece_count'],
            // Color, Size and Weight are independent, optional choices.
            // Piece Count is required because it is the ONLY price driver.
            'options.color' => ['sometimes', 'array', 'max:40'],
            'options.size' => ['sometimes', 'array', 'max:40'],
            'options.weight' => ['sometimes', 'array', 'max:40'],
            'options.piece_count' => ['required', 'array', 'min:1', 'max:40'],
            'options.*.*' => ['required', 'integer', 'min:1'],
            'prices' => ['required', 'array'],
            'prices.*' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'activate_after_save' => ['sometimes', 'boolean'],
        ]);
        DB::transaction(function () use ($product, $data, $catalog): void {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ($locked->has_variants || ! in_array((int) $locked->inventory_version, [0, 1], true)) {
                throw ValidationException::withMessages(['options' => 'This product has an unsupported legacy variant setup.']);
            }
            $options = ProductOption::query()->whereIn('code', OptionWiseSelections::CODES)
                ->where('is_active', true)->get()->keyBy('code');
            if (! $options->has('piece_count')) {
                throw ValidationException::withMessages([
                    'options.piece_count' => 'Create an active Piece Count option in Variant Options before setting prices.',
                ]);
            }
            $ids = [];
            $piecePrices = [];
            foreach (OptionWiseSelections::CODES as $code) {
                $values = array_values(array_unique(array_map('intval', $data['options'][$code] ?? [])));
                if ($values === []) {
                    continue; // Unselected optional dimensions are not offered to customers.
                }
                if (! $options->has($code)) {
                    throw ValidationException::withMessages([
                        'options' => "Activate the {$code} option category before selecting its values.",
                    ]);
                }
                $validIds = DB::table('product_option_values')
                    ->where('product_option_id', $options[$code]->id)->where('is_active', true)
                    ->whereIn('id', $values)->pluck('id')->map(static fn ($id) => (int)$id)->all();
                if (count($validIds) !== count($values)) {
                    throw ValidationException::withMessages(['options' => "Invalid {$code} choices selected."]);
                }
                $ids = array_merge($ids, $validIds);
                if ($code === 'piece_count') {
                    foreach ($validIds as $id) {
                        if (!isset($data['prices'][$id]) || (int)$data['prices'][$id] < 1) {
                            throw ValidationException::withMessages(['prices' => 'Set a positive price for each selected Piece Count.']);
                        }
                        $piecePrices[$id] = (int)$data['prices'][$id];
                    }
                }
            }
            // Only after every selected value and price has been verified do we
            // promote an existing Simple Product. The nested transaction uses the
            // same product lock; failure rolls back BOTH inventory and option rows.
            if ((int) $locked->inventory_version === 0) {
                $catalog->prepareSimple($locked, true);
                $locked->refresh();
            }
            // Never replace physical stock or synthesize Color x Size combinations.
            DB::table('product_option_choices')->where('product_id', $locked->id)->delete();
            foreach ($ids as $id) {
                DB::table('product_option_choices')->insert([
                    'product_id' => $locked->id, 'product_option_value_id' => $id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('product_piece_prices')->where('product_id', $locked->id)
                ->whereNotIn('product_option_value_id', array_keys($piecePrices))->delete();
            foreach ($piecePrices as $id => $price) {
                DB::table('product_piece_prices')->updateOrInsert(
                    ['product_id' => $locked->id, 'product_option_value_id' => $id],
                    ['price' => $price, 'updated_at' => now(), 'created_at' => now()]
                );
            }
            // Make the internal shared inventory usable after the product is published.
            DB::table('product_variants')->where('product_id', $locked->id)
                ->where('is_default', true)->update(['is_active' => true, 'updated_at' => now()]);

            // A newly created Option-wise Product stays unpublished only during
            // its initial price setup. The form offers activation by default,
            // but the admin can leave an intentionally paused product inactive.
            // Publish ONLY after all selected choices and prices have been saved
            // in this transaction. Subsequent price edits never change the status
            // of an already Active product.
            $changes = ['new_price' => min($piecePrices)];
            if (! $locked->status && ! empty($data['activate_after_save'])) {
                $changes['status'] = true;
            }
            $locked->update($changes);
        }, 3);
        $wasSimple = (int) $product->inventory_version === 0;
        $product->refresh();
        $message = $wasSimple
            ? 'Simple Product upgraded to Option-wise Pricing. Existing stock was kept as the shared balance and recorded in the audit ledger.'
            : 'Option-wise prices saved. Color, Size and Weight are independently selectable. Stock is shared per product.';
        $message .= $product->status
            ? ' Product is Active.'
            : ' Product remains Inactive. Tick Activate when saving prices to publish it.';
        return redirect()->route('admin.products.variants.index', $product)
            ->with('success', $message);
    }
}
