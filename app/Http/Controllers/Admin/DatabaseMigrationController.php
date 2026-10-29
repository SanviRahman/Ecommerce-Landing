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

        return response()->view('admin.commands.database-migrations', [
            'statusReady' => $statusReady,
            'statusOutput' => self::shortOutput($statusOutput),
            'auditMigrationExists' => is_file(base_path(self::AUDIT_MIGRATION)),
        ])->header('Cache-Control', 'private, no-store, max-age=0')
          ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assertActiveAdmin($request);

        $data = $request->validate([
            'scope' => ['required', Rule::in(['audit', 'all'])],
            'confirmation' => ['required', 'string', 'max:30'],
            'current_password' => ['required', 'string', 'max:255'],
            'backup_confirmed' => ['required', 'accepted'],
        ]);

        $requiredPhrase = $data['scope'] === 'all' ? 'MIGRATE ALL' : 'MIGRATE AUDIT';
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
            }

            Log::notice('Admin database migration requested', [
                'admin_id' => $request->user()->getAuthIdentifier(),
                'scope' => $data['scope'],
            ]);

            $exitCode = Artisan::call('migrate', $options);
            $resultOutput = trim(Artisan::output());

            Log::notice('Admin database migration finished', [
                'admin_id' => $request->user()->getAuthIdentifier(),
                'scope' => $data['scope'],
                'exit_code' => $exitCode,
            ]);

            return redirect()->route('command.database-migrations.index')
                ->with('migration_result', [
                    'success' => $exitCode === 0,
                    'scope' => $data['scope'],
                    'message' => $exitCode === 0
                        ? 'Artisan migrate finished successfully. Review the updated status below.'
                        : 'Artisan migrate reported a failure. Review the output and server log.',
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
