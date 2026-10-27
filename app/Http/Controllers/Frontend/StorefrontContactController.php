<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\StorefrontContactMessage;
use App\Services\Storefront\PublicCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class StorefrontContactController extends Controller
{
    public function store(Request $request, PublicCatalog $catalog): RedirectResponse
    {
        $catalog->assertAvailable();
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:24', 'regex:/\A\+?[0-9() .-]{6,24}\z/D'],
            'subject' => ['nullable', Rule::in(['general', 'product', 'order', 'delivery', 'returns', 'other'])],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            // Honeypot: should remain empty for a real customer.
            'company_website' => ['nullable', 'string', 'max:0'],
        ]);
        StorefrontContactMessage::query()->create([
            'name' => trim($data['name']),
            'email' => trim($data['email']),
            'phone' => trim($data['phone'] ?? '') ?: null,
            'subject' => $data['subject'] ?? 'general',
            'message' => trim($data['message']),
            'status' => 'new',
        ]);
        return redirect()->route('storefront.contact')->with('contact_success',
            'Your message was received. Our support team can review it in the admin inbox.');
    }
}
