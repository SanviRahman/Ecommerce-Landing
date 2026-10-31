<?php

namespace App\Services\Checkout;

use App\Services\Inventory\StorefrontDeferredStock;
use App\Support\P02MigrationSafety;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * Shared P03 checkout idempotency core: isolated/local QA and explicit P58 storefront release.
 *
 * Claim and complete MUST run in the very same transaction as order creation.
 * If the transaction rolls back, the claim rolls back as well; a retry can
 * create an order. Concurrent claims for the same owner/key are serialized
 * through the unique owner/key index and a FOR UPDATE lock.
 *
 * Caller must derive ownerHash from a trusted server-side session/cookie,
 * never accept a public owner_hash or a client-computed price as authority.
 */
final class CheckoutIdempotencyService
{
    /**
     * @return array{state:'new'|'replayed',checkout_request_id:string,order_id:int|null}
     */
    public function claim(
        string $ownerHash,
        string $idempotencyKey,
        string $requestHash,
        string $channel,
        ?int $campaignId = null
    ): array {
        $this->assertTestTransaction();

        if (! preg_match('/\A[a-f0-9]{64}\z/D', $ownerHash)
            || ! preg_match('/\A[a-f0-9]{64}\z/D', $requestHash)
            || ! Str::isUuid($idempotencyKey)
            || ! in_array($channel, ['campaign', 'storefront'], true)
            || ($channel === 'campaign' && ($campaignId === null || $campaignId < 1))
            || ($channel === 'storefront' && $campaignId !== null)) {
            throw new InvalidArgumentException('Checkout identity and scope are invalid.');
        }

        $query = DB::table('checkout_requests')
            ->where('owner_hash', $ownerHash)
            ->where('idempotency_key', $idempotencyKey);
        $existing = $query->lockForUpdate()->first();

        if ($existing !== null) {
            if ($existing->request_hash !== $requestHash || $existing->channel !== $channel
                || ($existing->campaign_id === null ? null : (int) $existing->campaign_id) !== $campaignId) {
                throw new DomainException('Checkout key was reused with a different request.');
            }
            if ($existing->status === 'completed' && $existing->order_id !== null) {
                return [
                    'state' => 'replayed',
                    'checkout_request_id' => (string) $existing->id,
                    'order_id' => (int) $existing->order_id,
                ];
            }
            throw new DomainException('This checkout is already being processed; do not create a second order.');
        }

        $id = (string) Str::uuid();
        DB::table('checkout_requests')->insert([
            'id' => $id,
            'owner_hash' => $ownerHash,
            'idempotency_key' => $idempotencyKey,
            'channel' => $channel,
            'campaign_id' => $campaignId,
            'request_hash' => $requestHash,
            'status' => 'processing',
            'order_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['state' => 'new', 'checkout_request_id' => $id, 'order_id' => null];
    }

    /** Commit only after a valid reservation (deferred) or allocation is persisted. */
    public function complete(string $checkoutRequestId, int $orderId): void
    {
        $this->assertTestTransaction();
        if (! Str::isUuid($checkoutRequestId) || $orderId < 1) {
            throw new InvalidArgumentException('Invalid checkout request/order identity.');
        }
        $claim = DB::table('checkout_requests')
            ->where('id', $checkoutRequestId)->lockForUpdate()->first();
        if ($claim === null) {
            throw new DomainException('Checkout reservation does not exist.');
        }
        if ($claim->status === 'completed' && (int) $claim->order_id === $orderId) {
            return; // Stable successful response on a safe replay.
        }
        // A deferred order is complete as a *checkout* when its reservation
        // exists, even though physical inventory is not allocated until Confirmed.
        // The P26/campaign/legacy paths must still require inventory_version=1.
        $deferred = $claim->channel === 'storefront'
            && StorefrontDeferredStock::checkoutEnabled();
        $columns = ['campaign_id', 'created_via', 'inventory_version', 'deleted_at'];
        if ($deferred) {
            $columns[] = 'storefront_stock_state';
        }
        $order = DB::table('orders')->where('id', $orderId)->first($columns);
        $inventoryReady = $order !== null && ($deferred
            ? (int) $order->inventory_version === 0
                && (int) $order->storefront_stock_state === StorefrontDeferredStock::RESERVED
            : (int) $order->inventory_version === 1);
        if ($claim->status !== 'processing' || $claim->order_id !== null
            || $order === null || $order->deleted_at !== null || ! $inventoryReady
            || ($order->campaign_id === null ? null : (int) $order->campaign_id)
                !== ($claim->campaign_id === null ? null : (int) $claim->campaign_id)
            || $order->created_via !== 'frontend') {
            throw new DomainException('Checkout completion requires a valid allocated or reserved storefront order.');
        }
        $updated = DB::table('checkout_requests')
            ->where('id', $checkoutRequestId)->where('status', 'processing')->whereNull('order_id')
            ->update(['status' => 'completed', 'order_id' => $orderId, 'updated_at' => now()]);
        if ($updated !== 1) {
            throw new DomainException('Checkout completion raced with another writer.');
        }
    }

    private function assertTestTransaction(): void
    {
        if (config('storefront.p03_core_test_enabled', false) !== true) {
            throw new LogicException('P03 checkout idempotency is disabled pending MySQL integration approval.');
        }
        if (request()->attributes->get('p58_production_checkout') === true) {
            app(\App\Support\P58ProductionStorefrontAccess::class)->assertStorefrontCheckout();
        } elseif (request()->attributes->get('p32_local_checkout', false) === true) {
            app(\App\Support\P32LocalStorefrontAccess::class)->assertStorefrontCheckout();
        } else {
            P02MigrationSafety::assertIsolatedTestDatabase();
        }
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Checkout claim, order creation and completion must share one transaction.');
        }
    }
}
