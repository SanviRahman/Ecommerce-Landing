<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * P56: read-only viewer for P55's retained order_purge_audits evidence.
 * No create, edit, delete, restore, or audit state change routes exist.
 */
final class OrderPurgeAuditLogController extends Controller
{
    private const PAGE_SIZE = 20;
    private const EXPORT_LIMIT = 5000;

    private function authorizeViewer(): void
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->isAdmin() && (bool) $user->is_active, 403);
    }

    /** @return array{search: ?string, admin_id: ?int, from: ?string, to: ?string, inventory_version: ?string} */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
            'admin_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'inventory_version' => ['nullable', Rule::in(['0', '1'])],
        ]);

        if (isset($validated['from'], $validated['to'])
            && strcmp($validated['to'], $validated['from']) < 0) {
            throw ValidationException::withMessages([
                'to' => 'The end date must be on or after the start date.',
            ]);
        }

        return [
            'search' => ($search = trim((string) ($validated['search'] ?? ''))) === '' ? null : $search,
            'admin_id' => isset($validated['admin_id']) ? (int) $validated['admin_id'] : null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'inventory_version' => isset($validated['inventory_version'])
                ? (string) $validated['inventory_version'] : null,
        ];
    }

    /** @param array<string, mixed> $filters */
    private function auditQuery(array $filters): Builder
    {
        $query = DB::table('order_purge_audits as audits')
            ->leftJoin('users as users', 'users.id', '=', 'audits.admin_id')
            ->select([
                'audits.id', 'audits.original_order_id', 'audits.invoice_id',
                'audits.admin_id', 'audits.inventory_version', 'audits.created_at',
                'users.name as admin_name',
            ]);

        if ($filters['search'] !== null) {
            $search = $filters['search'];
            $query->where(static function (Builder $q) use ($search): void {
                $q->where('audits.invoice_id', 'like', '%' . $search . '%');
                if (ctype_digit($search) && strlen($search) <= 18) {
                    $q->orWhere('audits.original_order_id', $search);
                }
            });
        }
        if ($filters['admin_id'] !== null) {
            $query->where('audits.admin_id', $filters['admin_id']);
        }
        if ($filters['inventory_version'] !== null) {
            $query->where('audits.inventory_version', (int) $filters['inventory_version']);
        }
        if ($filters['from'] !== null) {
            $query->where('audits.created_at', '>=', CarbonImmutable::parse($filters['from'])->startOfDay());
        }
        if ($filters['to'] !== null) {
            $query->where('audits.created_at', '<=', CarbonImmutable::parse($filters['to'])->endOfDay());
        }

        return $query;
    }

    public function index(Request $request): Response
    {
        $this->authorizeViewer();
        $filters = $this->filters($request);
        $available = Schema::hasTable('order_purge_audits');
        $audits = null;
        $adminOptions = collect();
        $totals = ['all' => 0, 'today' => 0, 'admins' => 0];

        if ($available) {
            $audits = $this->auditQuery($filters)->orderByDesc('audits.id')
                ->paginate(self::PAGE_SIZE)->appends(array_filter($filters, static fn ($v) => $v !== null));
            $adminOptions = DB::table('order_purge_audits as audits')
                ->leftJoin('users as users', 'users.id', '=', 'audits.admin_id')
                ->select('audits.admin_id', 'users.name as admin_name')
                ->distinct()->orderBy('audits.admin_id')->limit(200)->get();
            $table = DB::table('order_purge_audits');
            $totals = [
                'all' => (clone $table)->count(),
                'today' => (clone $table)->whereDate('created_at', now()->toDateString())->count(),
                'admins' => (clone $table)->distinct()->count('admin_id'),
            ];
        }

        return response()->view('admin.audit-logs.index', compact('available', 'audits', 'totals', 'filters', 'adminOptions'))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function show(int $audit): Response
    {
        $this->authorizeViewer();
        abort_unless(Schema::hasTable('order_purge_audits'), 503, 'Audit history is not initialized.');

        $entry = DB::table('order_purge_audits as audits')
            ->leftJoin('users as users', 'users.id', '=', 'audits.admin_id')
            ->where('audits.id', $audit)
            ->select('audits.*', 'users.name as admin_name')
            ->first();
        abort_if($entry === null, 404);

        $snapshot = self::decodeSnapshot($entry->audit_snapshot);
        return response()->view('admin.audit-logs.show', compact('entry', 'snapshot'))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeViewer();
        $filters = $this->filters($request);
        abort_unless(Schema::hasTable('order_purge_audits'), 503, 'Audit history is not initialized.');

        // Explicitly bounded CSV: do not dump unreviewed nested JSON or PII.
        $query = $this->auditQuery($filters)
            ->addSelect('audits.audit_snapshot')
            ->orderByDesc('audits.id')
            ->limit(self::EXPORT_LIMIT);

        return response()->streamDownload(static function () use ($query): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Audit ID', 'Original Order ID', 'Invoice', 'Admin ID', 'Admin Name',
                'Source', 'Order Status', 'Payment Status', 'Inventory Version',
                'Subtotal (BDT)', 'Shipping (BDT)', 'Total (BDT)',
                'SKU Lines', 'Inventory Movements', 'Purged At',
            ], ',', '"', '');
            foreach ($query->cursor() as $row) {
                $snapshot = self::decodeSnapshot($row->audit_snapshot) ?? [];
                $cells = [
                    $row->id, $row->original_order_id, $row->invoice_id,
                    $row->admin_id, $row->admin_name ?? ('Former admin #' . $row->admin_id),
                    $snapshot['created_via'] ?? '', $snapshot['order_status'] ?? '',
                    $snapshot['payment_status'] ?? '', $row->inventory_version,
                    $snapshot['sub_total'] ?? '', $snapshot['shipping_charge'] ?? '',
                    $snapshot['total_amount'] ?? '',
                    count(is_array($snapshot['items'] ?? null) ? $snapshot['items'] : []),
                    count(is_array($snapshot['movement_ids'] ?? null) ? $snapshot['movement_ids'] : []),
                    $row->created_at,
                ];
                fputcsv($output, array_map(self::safeCsvCell(...), $cells), ',', '"', '');
            }
            fclose($output);
        }, 'order-purge-audit-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string, mixed>|null */
    private static function decodeSnapshot(mixed $json): ?array
    {
        try {
            $decoded = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException) {
            return null;
        }
    }

    /** Prevent spreadsheet formula execution when CSV data is opened in Excel/Sheets. */
    private static function safeCsvCell(mixed $value): string
    {
        $text = (string) ($value ?? '');
        return preg_match('/^[\x00-\x20]*[=+\-@]/', $text) ? "'" . $text : $text;
    }
}
