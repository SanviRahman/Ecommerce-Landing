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
    public function index(PublicCatalog $catalog): Response|RedirectResponse
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
        return $this->view('storefront.account.index', $catalog, compact('customer', 'orders', 'orderStats'));
    }
    public function updateProfile(Request $request, PublicCatalog $catalog): RedirectResponse
    {
         $this->assertAccountReady($catalog);
        $customer = Auth::guard('storefront')->user();
        abort_unless($customer && $customer->is_active, 403);
        $data = $request->validate([
            'name' => ['required','string','min:2','max:160'],
            'phone' => ['nullable','regex:/^01[0-9]{9}$/'],
            'address' => ['nullable','string','max:2000'],
        ]);
        $customer->update($data);
        return back()->with('status','Your profile was saved.');
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
