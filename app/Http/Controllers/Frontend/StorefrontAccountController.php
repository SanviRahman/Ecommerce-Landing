<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\StorefrontAccount;
use App\Models\Order;
use App\Services\Storefront\PublicCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

/** Customer-only guard; never authenticates with the admin/employee User model. */
final class StorefrontAccountController extends Controller
{
    public function loginForm(PublicCatalog $catalog): Response|RedirectResponse
    {
        $this->assertAccountReady($catalog);
        $current = Auth::guard('storefront')->user();
        if ($current && $current->is_active) return redirect()->route('storefront.account.index');
        if ($current) Auth::guard('storefront')->logout(); // inactive accounts must never loop between index/login
        return $this->view('storefront.account.login', $catalog);
    }
    public function registerForm(PublicCatalog $catalog): Response|RedirectResponse
    {
        $this->assertAccountReady($catalog);
        $current = Auth::guard('storefront')->user();
        if ($current && $current->is_active) return redirect()->route('storefront.account.index');
        if ($current) Auth::guard('storefront')->logout(); // inactive accounts must never loop between index/login
        return $this->view('storefront.account.register', $catalog);
    }
    public function register(Request $request, PublicCatalog $catalog): RedirectResponse
    {
        $this->assertAccountReady($catalog);
        // Normalize before checking the unique index to avoid case/space collisions.
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'name' => ['required','string','min:2','max:160'],
            'email' => ['required','email:rfc','max:190','unique:storefront_accounts,email'],
            'password' => ['required','confirmed', Password::min(12)->letters()->numbers()],
        ]);
        $customer = StorefrontAccount::create([
            'name' => trim($data['name']),
            'email' => Str::lower(trim($data['email'])),
            'password' => $data['password'],
        ]);
        Auth::guard('storefront')->login($customer);
        $request->session()->regenerate();
        return redirect()->route('storefront.account.index');
    }
    public function login(Request $request, PublicCatalog $catalog): RedirectResponse
    {
        $this->assertAccountReady($catalog);
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'email' => ['required','email','max:190'],
            'password' => ['required','string'],
        ]);
        if (! Auth::guard('storefront')->attempt([
            'email' => Str::lower(trim($data['email'])),
            'password' => $data['password'], 'is_active' => true,
        ])) {
            throw ValidationException::withMessages(['email'=>'These customer credentials could not be verified.']);
        }
        $request->session()->regenerate();
        return redirect()->route('storefront.account.index');
    }
    public function index(Request $request, PublicCatalog $catalog): Response|RedirectResponse
    {
        $this->assertAccountReady($catalog);
        $customer = Auth::guard('storefront')->user();
        if (! $customer || ! $customer->is_active) return redirect()->route('storefront.account.login');
        // Full totals are computed on this authenticated relationship; only the visible list is paginated/bounded.
        $ownedOrders = $customer->orders();
        $orderStats = [
            'total' => (clone $ownedOrders)->count(),
            'active' => (clone $ownedOrders)->whereNotIn('order_status', [
                Order::STATUS_DELIVERED, Order::STATUS_CANCELLED, Order::STATUS_CANCELED,
            ])->count(),
            'completed' => (clone $ownedOrders)->where('order_status', Order::STATUS_DELIVERED)->count(),
        ];
        $orders = $ownedOrders->orderByDesc('id')->limit(30)->get();
        // Section navigation is an allowlisted GET view, not a scroll anchor. In particular,
        // an empty orders list must not land the user in the Profile Settings form.
        $requestedTab = $request->query('tab', 'dashboard');
        $activeTab = in_array($requestedTab, ['dashboard', 'orders', 'profile'], true)
            ? $requestedTab
            : 'dashboard';

        return $this->view('storefront.account.index', $catalog, compact('customer', 'orders', 'orderStats', 'activeTab'));
    }
    public function updateProfile(Request $request, PublicCatalog $catalog): RedirectResponse
    {
        $this->assertAccountReady($catalog);
        $customer = Auth::guard('storefront')->user();
        abort_unless($customer && $customer->is_active, 403);
        abort_unless(Schema::hasColumns('storefront_accounts', [
            'gender', 'date_of_birth', 'division', 'district', 'upazila',
        ]), 503, 'Customer profile migration has not been installed.');

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'phone' => ['nullable', 'regex:/^01[0-9]{9}$/'],
            'gender' => ['nullable', 'in:male,female,other,prefer_not_to_say'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'division' => ['nullable', 'in:Barishal,Chattogram,Dhaka,Khulna,Mymensingh,Rajshahi,Rangpur,Sylhet'],
            'district' => ['nullable', 'string', 'max:100'],
            'upazila' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:2000'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['sometimes', 'boolean'],
        ]);
        $attributes = Arr::only($data, [
            'name', 'phone', 'gender', 'date_of_birth', 'division', 'district', 'upazila', 'address',
        ]);
        // Convert cleared optional inputs to NULL instead of keeping stale information.
        foreach (['phone', 'gender', 'date_of_birth', 'division', 'district', 'upazila', 'address'] as $field) {
            $attributes[$field] = ($attributes[$field] ?? null) ?: null;
        }

        // Validate MIME-derived extension before touching the profile or Media Library.
        $photoExtension = null;
        if ($request->hasFile('profile_photo')) {
            $photoExtension = (string) $request->file('profile_photo')->guessExtension();
            if (! in_array($photoExtension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                throw ValidationException::withMessages([
                    'profile_photo' => 'Please upload a JPG, PNG or WebP image.',
                ]);
            }
        }

        // An existing Spatie media table is required for photo operations. We never
        // create another avatar column or write to a hand-managed public directory.
        if ($photoExtension !== null || $request->boolean('remove_photo')) {
            abort_unless(Schema::hasTable('media'), 503, 'Spatie Media Library table is not installed.');
        }

        // Only the logged-in storefront customer can be modified; never accept a
        // submitted account ID or upload path. Other profile fields remain unchanged.
        $customer->update($attributes);

        if ($photoExtension !== null) {
            // The collection is singleFile(): replacing a photo removes the former
            // photo through Spatie's storage and media-record lifecycle.
            $customer->addMediaFromRequest('profile_photo')
                ->usingName('Customer profile photo')
                ->usingFileName(Str::uuid()->toString() . '.' . $photoExtension)
                ->toMediaCollection('profile_photo', 'public');
        } elseif ($request->boolean('remove_photo')) {
            $customer->clearMediaCollection('profile_photo');
        }

        return redirect()->route('storefront.account.index', ['tab' => 'profile'])
            ->with('status', 'Your profile was saved.');
    }
    public function logout(Request $request, PublicCatalog $catalog): RedirectResponse
    {
        $this->assertAccountReady($catalog);
        Auth::guard('storefront')->logout();
        // Preserve any independent admin login in this browser while rotating the customer session ID.
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        return redirect()->route('storefront.account.login');
    }
    private function assertAccountReady(PublicCatalog $catalog): void
    {
        // Customer writes require an explicit, independently verified request context.
        $request = request();
        $production = $request->attributes->get('p58_production_commerce') === true;
        $local = $request->attributes->get('p32_local_commerce', false) === true;
        abort_unless($production || $local
            || $request->attributes->get('p26_isolated_commerce_qa', false) === true, 404);
        if ($production) {
            app(\App\Support\P58ProductionStorefrontAccess::class)->assertApprovedDatabase($request);
        } elseif ($local) {
            app(\App\Support\P32LocalStorefrontAccess::class)->assertActualMainDatabase($request);
        }
        $catalog->assertAvailable();
        abort_unless(Schema::hasTable('storefront_accounts'), 503, 'Storefront account migration is not installed.');
    }

    private function view(string $name, PublicCatalog $catalog, array $data = []): Response
    {
        return response()->view($name, array_merge(['navigation'=>$catalog->navigationCategories()], $data))
            ->header('Cache-Control','private, no-store, max-age=0')
            ->header('X-Robots-Tag','noindex, nofollow');
    }
}
