<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StorefrontFaq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Independent storefront FAQ CRUD. All mutating requests retain Laravel CSRF and admin auth. */
final class StorefrontFaqController extends Controller
{
    private function adminOnly(): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403);
    }

    public function index(): View
    {
        $this->adminOnly();
        return view('admin.storefront-faqs.index', [
            'faqs' => StorefrontFaq::query()->orderBy('sort_order')->orderBy('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->adminOnly();
        return view('admin.storefront-faqs.form', ['faq' => new StorefrontFaq()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->adminOnly();
        $data = $request->validate($this->rules());
        StorefrontFaq::query()->create($data + ['is_active' => false]);
        return redirect()->route('admin.storefront-faqs.index')->with('success', 'Storefront FAQ created.');
    }

    public function edit(StorefrontFaq $faq): View
    {
        $this->adminOnly();
        return view('admin.storefront-faqs.form', compact('faq'));
    }

    public function update(Request $request, StorefrontFaq $faq): RedirectResponse
    {
        $this->adminOnly();
        $faq->update($request->validate($this->rules()));
        return redirect()->route('admin.storefront-faqs.index')->with('success', 'Storefront FAQ updated.');
    }

    public function destroy(StorefrontFaq $faq): RedirectResponse
    {
        $this->adminOnly();
        $faq->delete(); // Soft delete; campaign FAQs never touched.
        return redirect()->route('admin.storefront-faqs.index')->with('success', 'Storefront FAQ moved to trash.');
    }

    private function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:300'],
            'answer' => ['required', 'string', 'max:6000'],
            'sort_order' => ['required', 'integer', 'between:0,100000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
