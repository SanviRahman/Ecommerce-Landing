<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\LocalHomepageAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * LOCAL/DEBUG-only admin diagnostics and per-session consent for the P25
 * read-only storefront. No commerce flags, database mutations, or GET writes.
 */
final class StorefrontReadinessController extends Controller
{
    private const SESSION_KEY = 'p26_local_storefront_preview_approved';

    public function index(Request $request): Response
    {
        $this->assertLocalAdmin();
        $configured = LocalHomepageAccess::configuredForBuilder();
        $verified = false;
        if ($configured) {
            try {
                LocalHomepageAccess::assertActualBuilderDatabase();
                $verified = true;
            } catch (Throwable $exception) {
                // Deliberately don't disclose MySQL username, host, or raw exception.
            }
        }
        $consented = config('storefront.p25_local_browse_enabled') === true
            || $request->session()->get(self::SESSION_KEY) === true;

        return response()->view('admin.storefront.readiness', [
            'configured' => $configured,
            'verified' => $verified,
            'consented' => $consented,
            'sessionConsented' => $request->session()->get(self::SESSION_KEY) === true,
            'ready' => $verified && $consented,
        ])->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function enable(Request $request): RedirectResponse
    {
        $this->assertLocalAdmin();
        try {
            LocalHomepageAccess::assertActualBuilderDatabase();
        } catch (Throwable $exception) {
            // The source of this failure is shown as a safe readiness check.
            return redirect()->route('admin.storefront.readiness')
                ->with('storefront_preview_issue', 'Local builder database permission is not ready.');
        }
        // Independent browser-session consent. Never enables cart/order mutations.
        $request->session()->put(self::SESSION_KEY, true);
        return redirect()->route('storefront.home');
    }

    public function disable(Request $request): RedirectResponse
    {
        $this->assertLocalAdmin();
        $request->session()->forget(self::SESSION_KEY);
        return redirect()->route('admin.storefront.readiness');
    }

    private function assertLocalAdmin(): void
    {
        abort_unless(app()->environment('local') && config('app.debug') === true, 404);
        $admin = Auth::guard('web')->user();
        abort_unless($admin !== null && $admin->isAdmin() && (bool) $admin->is_active, 403);
    }
}
