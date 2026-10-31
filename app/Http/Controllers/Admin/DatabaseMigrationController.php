<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Support\P58ProductionStorefrontAccess;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Administrative, explicitly confirmed migration runner for limited cPanel hosting.
 *
 * GET shows migration status only. POST never accepts a command string or a path.
 * Existing migration-level environment guards remain fully enforced.
 */
final class DatabaseMigrationController extends Controller
{
    private const AUDIT_MIGRATION = 'database/migrations/2026_10_29_100001_create_order_purge_audits.php';
    private const STOREFRONT_STOCK_MIGRATION = 'database/migrations/2026_10_31_060001_add_storefront_stock_state_to_orders.php';
    private const STOREFRONT_SESSIONS_MIGRATION = 'database/migrations/2026_10_31_070001_create_sessions_table.php';
    private const PREVIEW_SESSION = 'command.database_migrations.preview';
    private const PREVIEW_TTL_SECONDS = 600;
    private const LOCK_SECONDS = 900;
    private const OUTPUT_LIMIT = 16000;

    public function index(Request $request): Response
    {
        $this->assertActiveAdmin($request);

        try {
            $statusCode = Artisan::call('migrate:status');
            $statusOutput = trim(Artisan::output());
        } catch (Throwable $exception) {
            Log::warning('Admin migration status unavailable', [
                'admin_id' => $request->user()->getAuthIdentifier(),
                'exception_class' => $exception::class,
            ]);
            $statusCode = 1;
            $statusOutput = 'Migration status could not be loaded. Check server database configuration and logs.';
        }

        $statusReady = $statusCode === 0;
        if ($statusReady) {
            $request->session()->put(self::PREVIEW_SESSION, [
                'fingerprint' => hash('sha256', $statusOutput),
                'shown_at' => time(),
            ]);
        } else {
            $request->session()->forget(self::PREVIEW_SESSION);
        }

        // Do not default to the previously completed P55 audit while storefront
        // prerequisites remain pending. No migration is executed by a GET request.
        $storefrontPending = $statusReady && self::storefrontMigrationsExist() && (
            self::statusHasPendingMigration($statusOutput, self::STOREFRONT_STOCK_MIGRATION)
            || self::statusHasPendingMigration($statusOutput, self::STOREFRONT_SESSIONS_MIGRATION)
        );

        return response()->view('admin.commands.database-migrations', [
            'statusReady' => $statusReady,
            'statusOutput' => self::shortOutput($statusOutput),
            'auditMigrationExists' => is_file(base_path(self::AUDIT_MIGRATION)),
            'storefrontMigrationsExist' => self::storefrontMigrationsExist(),
            'storefrontPending' => $storefrontPending,
            'suggestedScope' => $storefrontPending ? 'storefront' : 'audit',
            'productionChecks' => $this->productionChecks($request),
        ])->header('Cache-Control', 'private, no-store, max-age=0')
          ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assertActiveAdmin($request);

        $data = $request->validate([
            'scope' => ['required', Rule::in(['audit', 'storefront', 'all'])],
            'confirmation' => ['required', 'string', 'max:30'],
            'current_password' => ['required', 'string', 'max:255'],
            'backup_confirmed' => ['required', 'accepted'],
        ]);

        $requiredPhrase = match ($data['scope']) {
            'audit' => 'MIGRATE AUDIT',
            'storefront' => 'MIGRATE STOREFRONT',
            default => 'MIGRATE ALL',
        };
        if (! hash_equals($requiredPhrase, $data['confirmation'])) {
            throw ValidationException::withMessages([
                'confirmation' => 'Type exactly ' . $requiredPhrase . ' to confirm.',
            ]);
        }

        if (! Hash::check($data['current_password'], $request->user()->getAuthPassword())) {
            throw ValidationException::withMessages([
                'current_password' => 'The current administrator password is incorrect.',
            ]);
        }

        $preview = $request->session()->get(self::PREVIEW_SESSION);
        if (! is_array($preview)
            || ! is_string($preview['fingerprint'] ?? null)
            || ! is_numeric($preview['shown_at'] ?? null)
            || time() - (int) $preview['shown_at'] > self::PREVIEW_TTL_SECONDS
            || (int) $preview['shown_at'] > time()) {
            throw ValidationException::withMessages([
                'preview' => 'Preview expired. Reopen Database Migrations and review the status first.',
            ]);
        }

        if ($data['scope'] === 'audit' && ! is_file(base_path(self::AUDIT_MIGRATION))) {
            throw ValidationException::withMessages([
                'scope' => 'The P55 audit migration file is missing on the server.',
            ]);
        }

        if ($data['scope'] === 'storefront') {
            if (! self::storefrontMigrationsExist()) {
                throw ValidationException::withMessages([
                    'scope' => 'One or more approved storefront prerequisite migration files are missing.',
                ]);
            }

            // This narrowly scoped release operation must never run against a
            // local, staging, or unexpected database by mistake. The P58 helper
            // verifies HTTPS host, APP_URL, production mode and actual MySQL DB.
            try {
                app(\App\Support\P58ProductionStorefrontAccess::class)
                    ->assertApprovedDatabase($request, false);
            } catch (Throwable $exception) {
                Log::warning('Storefront prerequisite migration target rejected', [
                    'admin_id' => $request->user()->getAuthIdentifier(),
                    'exception_class' => $exception::class,
                ]);
                throw ValidationException::withMessages([
                    'scope' => 'Storefront prerequisite migrations are allowed only on the approved production MySQL database.',
                ]);
            }
        }

        // Double-submit and concurrent execution protection. Fails closed if
        // the configured cache store cannot provide the lock.
        try {
            $lock = Cache::lock('admin:database-migrations', self::LOCK_SECONDS);
            if (! $lock->get()) {
                throw ValidationException::withMessages([
                    'preview' => 'A database migration is already running. Try again after it finishes.',
                ]);
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Unable to acquire admin migration lock', [
                'admin_id' => $request->user()->getAuthIdentifier(),
                'exception_class' => $exception::class,
            ]);
            throw ValidationException::withMessages([
                'preview' => 'Migration lock is unavailable. Check server cache configuration.',
            ]);
        }

        try {
            // A migration could have run or new files could have been uploaded
            // between the status preview and this request.
            $statusExit = Artisan::call('migrate:status');
            $statusOutput = trim(Artisan::output());
            if ($statusExit !== 0
                || ! hash_equals($preview['fingerprint'], hash('sha256', $statusOutput))) {
                throw ValidationException::withMessages([
                    'preview' => 'Migration status changed since preview. Refresh and review it again.',
                ]);
            }

            // Consume the preview before running; a browser refresh cannot
            // accidentally submit the same migration a second time.
            $request->session()->forget(self::PREVIEW_SESSION);

            $options = ['--force' => true, '--no-interaction' => true];
            if ($data['scope'] === 'audit') {
                $options['--path'] = self::AUDIT_MIGRATION;
            } elseif ($data['scope'] === 'storefront') {
                // Laravel's --path option accepts an explicit list. No
                // request-supplied migration path or shell argument is used.
                $options['--path'] = [
                    self::STOREFRONT_STOCK_MIGRATION,
                    self::STOREFRONT_SESSIONS_MIGRATION,
                ];
            }

            Log::notice('Admin database migration requested', [
                'admin_id' => $request->user()->getAuthIdentifier(),
                'scope' => $data['scope'],
            ]);

            $exitCode = Artisan::call('migrate', $options);
            $resultOutput = trim(Artisan::output());
            $prerequisitesVerified = true;
            if ($exitCode === 0 && $data['scope'] === 'storefront') {
                // A successful console exit alone is not evidence that the two
                // migrations were recorded and the required schema exists.
                $newStatusExit = Artisan::call('migrate:status');
                $newStatus = trim(Artisan::output());
                $prerequisitesVerified = $newStatusExit === 0
                    && ! self::statusHasPendingMigration($newStatus, self::STOREFRONT_STOCK_MIGRATION)
                    && ! self::statusHasPendingMigration($newStatus, self::STOREFRONT_SESSIONS_MIGRATION)
                    && Schema::hasColumn('orders', 'storefront_stock_state')
                    && Schema::hasTable('sessions');
            }
            $success = $exitCode === 0 && $prerequisitesVerified;

            Log::notice('Admin database migration finished', [
                'admin_id' => $request->user()->getAuthIdentifier(),
                'scope' => $data['scope'],
                'exit_code' => $exitCode,
                'prerequisites_verified' => $prerequisitesVerified,
            ]);

            return redirect()->route('command.database-migrations.index')
                ->with('migration_result', [
                    'success' => $success,
                    'scope' => $data['scope'],
                    'message' => $success
                        ? ($data['scope'] === 'storefront'
                            ? 'Both storefront prerequisites are recorded and their database schema is present.'
                            : 'Selected migration scope completed. Review the updated status below.')
                        : 'Migration or prerequisite verification failed. Review the output and server log.',
                    'output' => self::shortOutput($resultOutput),
                ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            // Do not expose raw database exception messages to the browser.
            Log::error('Admin database migration failed', [
                'admin_id' => $request->user()->getAuthIdentifier(),
                'scope' => $data['scope'],
                'exception_class' => $exception::class,
            ]);

            return redirect()->route('command.database-migrations.index')
                ->with('migration_result', [
                    'success' => false,
                    'scope' => $data['scope'],
                    'message' => 'Migration failed or was interrupted. Review the server logs and current migration status.',
                    'output' => '',
                ]);
        } finally {
            $lock->release();
        }
    }

    /** Safe status parsing; the GET status preview is the authoritative list. */
    private static function statusHasPendingMigration(string $output, string $migrationPath): bool
    {
        $name = pathinfo($migrationPath, PATHINFO_FILENAME);
        return preg_match('/(?:^|\n)[^\r\n]*' . preg_quote($name, '/')
            . '[^\r\n]*\bPending\b/i', $output) === 1;
    }

    /** Admin-only, read-only production diagnostics (no DB name or secrets exposed). */
    private function productionChecks(Request $request): array
    {
        $checks = [
            'Production mode with debug disabled' => config('app.env') === 'production'
                && config('app.debug') === false,
            'Explicit P58 storefront approval configured' => config('storefront.p58_production_commerce_enabled') === true,
            'Approved HTTPS host and real MySQL database match' => false,
            'P61 order stock-state column present' => false,
            'Database sessions table present' => false,
        ];
        try {
            app(P58ProductionStorefrontAccess::class)->assertApprovedDatabase($request, false);
            $checks['Approved HTTPS host and real MySQL database match'] = true;
        } catch (Throwable) {
            // A failed check is reported as not ready without divulging credentials.
        }
        try {
            $checks['P61 order stock-state column present'] = Schema::hasColumn('orders', 'storefront_stock_state');
            $checks['Database sessions table present'] = Schema::hasTable('sessions');
        } catch (Throwable) {
            // Keep diagnostics safe when the production DB is unavailable.
        }
        return $checks;
    }

    private static function storefrontMigrationsExist(): bool
    {
        return is_file(base_path(self::STOREFRONT_STOCK_MIGRATION))
            && is_file(base_path(self::STOREFRONT_SESSIONS_MIGRATION));
    }

    private function assertActiveAdmin(Request $request): void
    {
        $admin = $request->user();
        abort_unless($admin !== null && $admin->isAdmin() && (bool) $admin->is_active, 403);
    }

    private static function shortOutput(string $output): string
    {
        if (strlen($output) <= self::OUTPUT_LIMIT) {
            return $output;
        }

        return substr($output, 0, self::OUTPUT_LIMIT) . "\n[Output truncated]";
    }
}
