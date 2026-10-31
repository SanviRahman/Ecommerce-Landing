<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\ShippingCharge;
use App\Models\User;
use App\Support\P02MigrationSafety;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Run only on the explicitly isolated MariaDB test schema. No production fixtures. */
final class P49HomepageShippingMySqlTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        P02MigrationSafety::assertIsolatedTestDatabase();
        self::assertGreaterThan(0, DB::transactionLevel());
        $this->admin = $this->actor('admin');
    }

    private function actor(string $role): User
    {
        return User::create([
            'name' => 'P49 synthetic ' . $role,
            'email' => 'p49-' . Str::lower(Str::random(15)) . '@example.test',
            'password' => bcrypt(Str::random(30)),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function campaignCharge(): ShippingCharge
    {
        $nonce = Str::lower(Str::random(12));
        $campaign = Campaign::create([
            'title' => 'P49 synthetic campaign',
            'slug' => 'p49-' . $nonce,
        ]);

        return ShippingCharge::create([
            'campaign_id' => $campaign->id,
            'area_name' => 'P49 isolated campaign area ' . $nonce,
            'delivery_charge' => 90,
            'status' => true,
        ]);
    }

    public function test_homepage_area_creation_uses_only_global_scope_and_does_not_touch_campaign(): void
    {
        $campaignCharge = $this->campaignCharge();
        $area = 'P49 Homepage ' . Str::random(12);
        $this->actingAs($this->admin)->post(route('admin.homepage-shipping-charges.store'), [
            'area_name' => $area,
            'delivery_charge' => 75,
            'status' => '1',
        ])->assertRedirect(route('admin.homepage-shipping-charges.index'));

        $saved = ShippingCharge::whereNull('campaign_id')->where('area_name', $area)->sole();
        self::assertTrue($saved->status);
        self::assertSame(75, $saved->delivery_charge);
        self::assertTrue(ShippingCharge::resolvedForCampaign(null)->contains('id', $saved->id));
        self::assertFalse(ShippingCharge::resolvedForCampaign((int) $campaignCharge->campaign_id)->contains('id', $saved->id));
        self::assertSame(90, $campaignCharge->fresh()->delivery_charge);
    }

    public function test_campaign_scoped_row_cannot_be_edited_or_deleted_from_homepage_module(): void
    {
        $campaignCharge = $this->campaignCharge();
        $url = route('admin.homepage-shipping-charges.update', $campaignCharge);
        $this->actingAs($this->admin)->patch($url, [
            'area_name' => 'Overwritten?', 'delivery_charge' => 0, 'status' => '0',
        ])->assertNotFound();
        $this->delete(route('admin.homepage-shipping-charges.destroy', $campaignCharge))->assertNotFound();
        self::assertSame(90, $campaignCharge->fresh()->delivery_charge);
        self::assertNull($campaignCharge->fresh()->deleted_at);
    }

    public function test_validation_rejects_negative_fees_and_duplicate_homepage_area(): void
    {
        $area = 'P49 ' . Str::random(12);
        ShippingCharge::create(['campaign_id' => null, 'area_name' => $area, 'delivery_charge' => 75, 'status' => true]);
        $this->actingAs($this->admin)->post(route('admin.homepage-shipping-charges.store'), [
            'area_name' => $area, 'delivery_charge' => -1, 'status' => '1',
        ])->assertSessionHasErrors(['area_name', 'delivery_charge']);
    }

    public function test_admin_only_and_soft_delete_keeps_campaign_rows(): void
    {
        $campaignCharge = $this->campaignCharge();
        $global = ShippingCharge::create([
            'campaign_id' => null, 'area_name' => 'P49 delete ' . Str::random(10),
            'delivery_charge' => 55, 'status' => false,
        ]);
        $this->actingAs($this->actor('employee'))
            ->get(route('admin.homepage-shipping-charges.index'))->assertForbidden();
        $this->actingAs($this->admin)
            ->delete(route('admin.homepage-shipping-charges.destroy', $global))
            ->assertRedirect(route('admin.homepage-shipping-charges.index'));
        self::assertNotNull(ShippingCharge::withTrashed()->findOrFail($global->id)->deleted_at);
        self::assertNotNull($campaignCharge->fresh());
    }
}
