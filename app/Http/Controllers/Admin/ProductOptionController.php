<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Services\Catalog\VariantCatalogManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductOptionController extends Controller
{
    public function index(VariantCatalogManager $catalog): View
    {
        $this->adminOnly();
        $catalog->assertTestSchema();
        return view('admin.products.options', [
            'options' => ProductOption::query()->with('values')->orderBy('sort_order')->orderBy('id')->get(),
            'supported' => VariantCatalogManager::OPTIONS,
        ]);
    }

    public function bootstrap(VariantCatalogManager $catalog): RedirectResponse
    {
        $this->adminOnly();
        $catalog->bootstrapOptions();
        return back()->with('success', 'Approved product options initialized.');
    }

    public function value(Request $request, ProductOption $option, VariantCatalogManager $catalog): RedirectResponse
    {
        $this->adminOnly();
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:80', 'regex:/\A[a-z0-9][a-z0-9_-]*\z/D'],
            'label' => ['required', 'string', 'max:100'],
            'swatch_hex' => ['nullable', 'string', 'regex:/\A#[0-9a-fA-F]{6}\z/D'],
        ]);
        $catalog->addOptionValue($option, $validated);
        return back()->with('success', 'Option value added successfully.');
    }

    public function updateValue(Request $request, ProductOption $option, ProductOptionValue $value,
        VariantCatalogManager $catalog): RedirectResponse
    {
        $this->adminOnly();
        abort_unless((int) $value->product_option_id === (int) $option->id, 404);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:80', 'regex:/\A[a-z0-9][a-z0-9_-]*\z/D'],
            'label' => ['required', 'string', 'max:100'],
            'swatch_hex' => ['nullable', 'string', 'regex:/\A#[0-9a-fA-F]{6}\z/D'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
        ]);
        $catalog->updateOptionValue($option, $value, $validated);
        return back()->with('success', 'Option value updated successfully.');
    }

    public function destroyValue(ProductOption $option, ProductOptionValue $value,
        VariantCatalogManager $catalog): RedirectResponse
    {
        $this->adminOnly();
        abort_unless((int) $value->product_option_id === (int) $option->id, 404);
        $catalog->deleteOptionValue($option, $value);
        return back()->with('success', 'Unused option value deleted successfully.');
    }

    private function adminOnly(): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403);
        try {
            app(VariantCatalogManager::class)->assertTestSchema();
        } catch (\RuntimeException $exception) {
            // Never expose staged admin endpoints on a different DB/unfinished schema.
            abort(404, 'Staged variant administration is unavailable on this database.');
        }
    }
}
