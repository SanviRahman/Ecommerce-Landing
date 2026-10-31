<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Services\Inventory\ManagedOrderWriter;
use App\Support\P02MigrationSafety;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

require_once dirname(__DIR__, 3) . '/tools/qa/P03SyntheticFixtures.php';

/**
 * HTTP-level tests against a restricted, empty, local MariaDB test schema.
 * Synthetic users, orders and stock exist only within a rolled-back transaction.
 * CSRF is exempted by Laravel's testing environment: this suite verifies the
 * protected route and authorization, NOT a browser-level CSRF handshake.
 */
final class ManagedOrderHttpMySqlTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        P02MigrationSafety::assertIsolatedTestDatabase();
        $this->assertGreaterThan(0, DB::transactionLevel());
        config()->set('storefront.p03_core_test_enabled', true);
        config()->set('storefront.p03_order_writer_test_enabled', true);
    }

    protected function tearDown(): void
    {
        config()->set('storefront.p03_order_writer_test_enabled', false);
        config()->set('storefront.p03_core_test_enabled', false);
        parent::tearDown();
    }

    private function actor(string $role = 'admin', bool $active = true): User
    {
        return User::query()->create([
            'name' => 'Synthetic P03 ' . $role,
            'email' => 'p03-http-' . Str::lower(Str::random(18)) . '@example.test',
            'password' => bcrypt(Str::random(32)),
            'role' => $role,
            'is_active' => $active,
        ]);
    }

    /** @return array{product:int,variant:int,order:int,item:int} */
    private function allocated(string $source = 'frontend'): array
    {
        $fixture = \P03SyntheticFixtures::create(source: $source);
        $field = in_array($source, ['admin_bulk', 'employee_bulk'], true)
            ? 'bulk_orders'
            : (in_array($source, ['admin_manual', 'employee_manual'], true) ? 'items' : 'products');
        app(ManagedOrderWriter::class)->allocateCreatedOrder($fixture['order'], $field);
        return $fixture;
    }

    private function quantityUrl(array $fixture, ?int $itemId = null): string
    {
        return route('admin.orders.managed_items.quantity', [
            $fixture['order'], $itemId ?? $fixture['item'],
        ]);
    }

    private function quantity(int $orderItemId): int
    {
        return (int) DB::table('order_items')->where('id', $orderItemId)->value('quantity');
    }

    private function stock(int $variantId): int
    {
        return (int) DB::table('product_variants')->where('id', $variantId)->value('stock');
    }

    public function test_admin_quantity_patch_updates_stock_and_server_totals(): void
    {
        $f = $this->allocated();
        $this->actingAs($this->actor())
            ->patch($this->quantityUrl($f), ['quantity' => 4, 'expected_quantity' => 2])
            ->assertRedirect(route('admin.orders.edit', $f['order']))
            ->assertSessionHas('success');
        $this->assertSame(4, $this->quantity($f['item']));
        $this->assertSame(6, $this->stock($f['variant']));
        $this->assertSame('500.00', (string) DB::table('orders')->where('id', $f['order'])->value('total_amount'));
    }

    public function test_patch_ignores_client_supplied_prices(): void
    {
        $f = $this->allocated();
        $this->actingAs($this->actor())
            ->patch($this->quantityUrl($f), [
                'quantity' => 3, 'expected_quantity' => 2,
                'unit_price' => '0', 'total_price' => '0', 'total_amount' => '0',
            ])->assertRedirect();
        $this->assertSame('125.00', (string) DB::table('order_items')->where('id', $f['item'])->value('unit_price'));
        $this->assertSame('375.00', (string) DB::table('orders')->where('id', $f['order'])->value('total_amount'));
    }

    public function test_invalid_quantity_is_rejected_before_stock_change(): void
    {
        $f = $this->allocated();
        $this->actingAs($this->actor())
            ->patch($this->quantityUrl($f), ['quantity' => 0, 'expected_quantity' => 2])
            ->assertSessionHasErrors(['quantity']);
        $this->assertSame(2, $this->quantity($f['item']));
        $this->assertSame(8, $this->stock($f['variant']));
    }

    public function test_stale_expected_quantity_is_rejected(): void
    {
        $f = $this->allocated();
        $this->actingAs($this->actor())
            ->patch($this->quantityUrl($f), ['quantity' => 3, 'expected_quantity' => 1])
            ->assertSessionHasErrors(['quantity']);
        $this->assertSame(2, $this->quantity($f['item']));
        $this->assertSame(8, $this->stock($f['variant']));
    }

    public function test_guest_cannot_patch_managed_order(): void
    {
        $f = $this->allocated();
        $this->patch($this->quantityUrl($f), ['quantity' => 3, 'expected_quantity' => 2])
            ->assertRedirect(route('admin.login'));
        $this->assertSame(8, $this->stock($f['variant']));
    }

    public function test_inactive_admin_cannot_patch_managed_order(): void
    {
        $f = $this->allocated();
        $this->actingAs($this->actor('admin', false))
            ->patch($this->quantityUrl($f), ['quantity' => 3, 'expected_quantity' => 2])
            ->assertRedirect(route('admin.login'));
        $this->assertSame(8, $this->stock($f['variant']));
    }

    public function test_assigned_employee_can_patch_order(): void
    {
        $f = $this->allocated('employee_manual');
        $employee = $this->actor('employee');
        DB::table('orders')->where('id', $f['order'])->update(['assigned_employee_id' => $employee->id]);
        $this->actingAs($employee)
            ->patch($this->quantityUrl($f), ['quantity' => 3, 'expected_quantity' => 2])
            ->assertRedirect(route('admin.orders.edit', $f['order']));
        $this->assertSame(7, $this->stock($f['variant']));
    }

    public function test_unassigned_employee_cannot_patch_order(): void
    {
        $f = $this->allocated('admin_manual');
        $this->actingAs($this->actor('employee'))
            ->patch($this->quantityUrl($f), ['quantity' => 3, 'expected_quantity' => 2])
            ->assertForbidden();
        $this->assertSame(8, $this->stock($f['variant']));
    }

    public function test_route_is_not_available_when_writer_gate_is_off(): void
    {
        $f = $this->allocated();
        config()->set('storefront.p03_order_writer_test_enabled', false);
        $this->actingAs($this->actor())
            ->patch($this->quantityUrl($f), ['quantity' => 3, 'expected_quantity' => 2])
            ->assertNotFound();
        $this->assertSame(8, $this->stock($f['variant']));
    }

    public function test_cross_order_item_is_not_accessible(): void
    {
        $a = $this->allocated();
        $b = $this->allocated();
        $this->actingAs($this->actor())
            ->patch($this->quantityUrl($a, $b['item']), ['quantity' => 3, 'expected_quantity' => 2])
            ->assertNotFound();
        $this->assertSame(8, $this->stock($a['variant']));
        $this->assertSame(8, $this->stock($b['variant']));
    }

    public function test_negotiated_bulk_order_quantity_is_rejected(): void
    {
        $f = $this->allocated('admin_bulk');
        $this->actingAs($this->actor())
            ->patch($this->quantityUrl($f), ['quantity' => 3, 'expected_quantity' => 2])
            ->assertSessionHasErrors(['quantity']);
        $this->assertSame(8, $this->stock($f['variant']));
    }

    public function test_authorized_cancel_http_request_releases_stock_once(): void
    {
        $f = $this->allocated();
        $url = route('admin.orders.update_status', $f['order']);
        $this->actingAs($this->actor())
            ->patchJson($url, ['order_status' => 'cancelled'])->assertOk()
            ->assertJsonPath('status', true);
        $this->assertSame(10, $this->stock($f['variant']));
        $this->assertSame('cancelled', (string) DB::table('orders')->where('id', $f['order'])->value('order_status'));
        $this->patchJson($url, ['order_status' => 'cancelled'])->assertOk();
        $this->assertSame(1, DB::table('inventory_movements')
            ->where('order_item_id', $f['item'])->where('kind', 'release')->count());
    }

    public function test_shipped_managed_order_cannot_cancel_via_http(): void
    {
        $f = $this->allocated();
        DB::table('orders')->where('id', $f['order'])->update(['order_status' => 'shipped']);
        $this->actingAs($this->actor())
            ->patchJson(route('admin.orders.update_status', $f['order']), ['order_status' => 'cancelled'])
            ->assertUnprocessable();
        $this->assertSame(8, $this->stock($f['variant']));
        $this->assertSame('shipped', (string) DB::table('orders')->where('id', $f['order'])->value('order_status'));
    }
}
