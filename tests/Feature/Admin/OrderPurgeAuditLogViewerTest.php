<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Requires the repository's isolated P01 MySQL test credentials and approval.
 * The base TestCase blocks execution against unapproved databases.
 */
final class OrderPurgeAuditLogViewerTest extends TestCase
{
    use DatabaseTransactions;

    private function requireAuditTable(): void
    {
        if (! Schema::hasTable('order_purge_audits')) {
            $this->markTestSkipped('Run the reviewed P55 migration on the isolated test database first.');
        }
    }

    private function admin(string $role = User::ROLE_ADMIN): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    private function archive(int $adminId, string $invoice = 'TEST-P56-INV'): int
    {
        return (int) DB::table('order_purge_audits')->insertGetId([
            'original_order_id' => random_int(900000000, 999999999),
            'invoice_id' => $invoice,
            'admin_id' => $adminId,
            'inventory_version' => 1,
            'audit_snapshot' => json_encode([
                'created_via' => 'storefront',
                'order_status' => 'cancelled',
                'payment_status' => 'pending',
                'sub_total' => '630.00',
                'shipping_charge' => '70.00',
                'total_amount' => '700.00',
                'items' => [[
                    'id' => 99, 'product_id' => 123, 'sku' => 'TEST-SKU',
                    'quantity' => 1, 'unit_price' => '630.00',
                    'total_price' => '630.00', 'allocated_quantity' => 1,
                ]],
                'movement_ids' => [55],
                'checkout_request_ids' => [88],
                'status_history' => [['status' => 'pending', 'at' => '2026-10-29 00:00:00']],
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    public function test_admin_can_filter_and_view_retained_history_without_writing_to_it(): void
    {
        $this->requireAuditTable();
        $admin = $this->admin();
        $id = $this->archive($admin->id);
        $before = DB::table('order_purge_audits')->where('id', $id)->value('audit_snapshot');

        $this->actingAs($admin)
            ->get(route('admin.reports.audit-logs.index', ['search' => 'TEST-P56-INV']))
            ->assertOk()->assertSee('TEST-P56-INV')->assertSee('View Details');

        $this->get(route('admin.reports.audit-logs.show', $id))
            ->assertOk()->assertSee('Financial Snapshot')
            ->assertSee('Inventory & Checkout Evidence', false)
            ->assertSee('700.00')->assertSee('TEST-SKU');

        self::assertSame($before, DB::table('order_purge_audits')->where('id', $id)->value('audit_snapshot'));
    }

    public function test_employee_cannot_list_show_or_export_audit_history(): void
    {
        $employee = $this->admin(User::ROLE_EMPLOYEE);
        $this->actingAs($employee)
            ->get(route('admin.reports.audit-logs.index'))->assertForbidden();
        $this->get(route('admin.reports.audit-logs.show', 1))->assertForbidden();
        $this->get(route('admin.reports.audit-logs.export'))->assertForbidden();
    }

    public function test_csv_export_sanitizes_spreadsheet_formulas(): void
    {
        $this->requireAuditTable();
        $admin = $this->admin();
        $id = $this->archive($admin->id, '=HYPERLINK("https://invalid.example")');

        $response = $this->actingAs($admin)->get(route('admin.reports.audit-logs.export'));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        self::assertStringContainsString("'=HYPERLINK", $csv);
        self::assertStringContainsString((string) $id, $csv);
    }

    public function test_audit_routes_do_not_accept_mutations(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)
            ->delete('/admin/reports/audit-logs/1')->assertStatus(405);
        $this->post('/admin/reports/audit-logs')->assertStatus(405);
    }
}
