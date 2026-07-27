<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeBulkOrderAdminNotes extends Command
{
    protected $signature = 'orders:normalize-bulk-admin-notes
                            {--all : Replace every bulk-order Admin Note, including manually edited notes}';

    protected $description = 'Replace verbose bulk-order Admin Notes with a concise employee completion note.';

    public function handle(): int
    {
        $query = Order::withTrashed()
            ->whereIn('created_via', [
                Order::CREATED_VIA_ADMIN_BULK,
                Order::CREATED_VIA_EMPLOYEE_BULK,
            ])
            ->with('assignedEmployee')
            ->orderBy('id');

        if (! $this->option('all')) {
            $query->where(function ($builder) {
                $builder->where('admin_note', 'like', 'Created from bulk order batch%')
                    ->orWhereNull('admin_note')
                    ->orWhere('admin_note', '');
            });
        }

        $updated = 0;
        $timezone = method_exists(Order::class, 'displayTimezone')
            ? Order::displayTimezone()
            : config('app.order_display_timezone', 'Asia/Dhaka');

        $query->chunkById(100, function ($orders) use (&$updated, $timezone) {
            foreach ($orders as $order) {
                $employeeName = trim((string) ($order->assignedEmployee?->name ?? ''));

                if ($employeeName === '' && $order->created_by_admin_id) {
                    $employeeName = trim((string) User::query()
                        ->whereKey($order->created_by_admin_id)
                        ->value('name'));
                }

                if ($employeeName === '') {
                    $employeeName = 'System';
                }

                $completedAt = method_exists($order, 'localDateTime')
                    ? $order->localDateTime('created_at')
                    : ($order->created_at
                        ? $order->created_at->copy()->timezone($timezone)
                        : now()->timezone($timezone));

                $note = sprintf(
                    'Bulk Order completed by %s on %s at %s.',
                    $employeeName,
                    $completedAt?->format('d M Y') ?: now()->timezone($timezone)->format('d M Y'),
                    $completedAt?->format('h:i A') ?: now()->timezone($timezone)->format('h:i A')
                );

                DB::table('orders')
                    ->where('id', $order->id)
                    ->update([
                        'admin_note' => $note,
                        'updated_at' => now(),
                    ]);

                $updated++;
            }
        });

        $this->info("{$updated} bulk-order Admin Notes updated successfully.");

        return self::SUCCESS;
    }
}
