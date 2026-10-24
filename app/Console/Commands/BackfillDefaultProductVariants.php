<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * P02 preparation ONLY. The existing order writers remain legacy until P03.
 * Dry-run performs no writes; --apply is restricted to a disposable LOCAL test schema.
 */
class BackfillDefaultProductVariants extends Command
{
    protected $signature = 'storefront:backfill-default-variants
        {--apply : Write to the isolated test schema only}
        {--acknowledge= : Must equal p01_campaign_test when applying}
        {--chunk=100 : Products per chunk (1-500)}';

    protected $description = 'Inspect legacy product stock or initialize default variants in an isolated disposable MySQL test database.';

    public static function legacySku(int $productId): string
    {
        if ($productId < 1) {
            throw new RuntimeException('A positive product ID is required for a legacy SKU.');
        }

        // Deterministic, stable, unique per product, never based on mutable text.
        return 'LEGACY-' . $productId;
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $chunk = filter_var($this->option('chunk'), FILTER_VALIDATE_INT);

        if ($chunk === false || $chunk < 1 || $chunk > 500) {
            $this->error('--chunk must be between 1 and 500.');
            return self::FAILURE;
        }

        if (! $this->hasRequiredSchema()) {
            $this->error('P02 schema is incomplete. Do NOT run migrations on the existing database without an approved isolated test environment.');
            return self::FAILURE;
        }

        if ($apply && ! $this->isApprovedTestConnection()) {
            $this->error('APPLY BLOCKED: only the isolated local p01_campaign_test schema and restricted p01_tester account are permitted.');
            return self::FAILURE;
        }

        $stats = ['already_managed' => 0, 'ready' => 0, 'conflicts' => 0, 'applied' => 0];
        $stats['already_managed'] = (int) DB::table('products')->where('inventory_version', 1)->count();
        $dryRun = ! $apply;

        DB::table('products')
            ->where('inventory_version', 0)
            ->select(['id', 'product_code', 'stock', 'status', 'deleted_at', 'has_variants', 'inventory_version'])
            ->orderBy('id')
            ->chunkById($chunk, function ($products) use ($dryRun, &$stats): void {
                foreach ($products as $product) {
                    if ($dryRun) {
                        $reason = $this->conflictReason($product);
                        $stats[$reason === null ? 'ready' : 'conflicts']++;
                        if ($reason !== null) {
                            $this->warn('Product ID ' . $product->id . ': ' . $reason);
                        }
                        continue;
                    }

                    try {
                        $result = DB::transaction(function () use ($product): string {
                            $locked = DB::table('products')->where('id', $product->id)->lockForUpdate()->first();
                            if ($locked === null || (int) $locked->inventory_version === 1) {
                                return 'already_managed';
                            }

                            $reason = $this->conflictReason($locked);
                            if ($reason !== null) {
                                throw new RuntimeException($reason);
                            }

                            $now = now();
                            $sku = $this->skuForProduct($locked);
                            if ($sku === null) {
                                throw new RuntimeException('No collision-free SKU available');
                            }
                            $stock = (int) $locked->stock;
                            $variantId = DB::table('product_variants')->insertGetId([
                                'product_id' => $locked->id,
                                'sku' => $sku,
                                'option_signature' => 'default',
                                'price_override' => null,
                                'old_price_override' => null,
                                'stock' => $stock,
                                'is_default' => true,
                                'is_active' => (bool) $locked->status && $locked->deleted_at === null,
                                'sort_order' => 0,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);

                            DB::table('inventory_movements')->insert([
                                'product_variant_id' => $variantId,
                                'order_item_id' => null,
                                'event_uuid' => (string) Str::uuid(),
                                'operation_key' => $this->openingKey((int) $locked->id),
                                'kind' => 'opening',
                                'quantity_delta' => $stock,
                                'stock_before' => 0,
                                'stock_after' => $stock,
                                'actor_id' => null,
                                'reason' => 'Legacy stock snapshot; physical balance not independently verified',
                                'metadata' => json_encode(['source' => 'legacy', 'reconciled' => false], JSON_THROW_ON_ERROR),
                                'created_at' => $now,
                            ]);

                            // Set only after variant + ledger exist in the same transaction.
                            DB::table('products')->where('id', $locked->id)->where('inventory_version', 0)
                                ->update(['inventory_version' => 1]);

                            return 'applied';
                        }, 3);
                        $stats[$result]++;
                    } catch (Throwable $e) {
                        $stats['conflicts']++;
                        $this->warn('Product ID ' . $product->id . ': transaction rolled back (' . $e->getMessage() . ')');
                    }
                }
            }, 'id');

        $this->table(['Outcome', 'Count'], array_map(
            static fn ($label, $count) => [$label, $count],
            array_keys($stats),
            array_values($stats)
        ));

        if ($stats['conflicts'] > 0) {
            $this->error('Conflicts detected; review before proceeding.');
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('DRY RUN: no database writes were requested. Historical stock is NOT certified sellable stock.');
        } else {
            $this->warn('TEST BACKFILL ONLY. Do not enable storefront sales; P03 shared inventory cutover is not implemented.');
        }

        return self::SUCCESS;
    }

    private function hasRequiredSchema(): bool
    {
        return Schema::hasTable('products')
            && Schema::hasTable('product_variants')
            && Schema::hasTable('inventory_movements')
            && Schema::hasColumn('products', 'inventory_version')
            && Schema::hasColumn('products', 'has_variants');
    }

    private function isApprovedTestConnection(): bool
    {
        if (! app()->environment(['local', 'testing'])
            || config('database.default') !== 'mysql'
            || (string) $this->option('acknowledge') !== 'p01_campaign_test') {
            return false;
        }

        $config = DB::connection()->getConfig();
        if (($config['database'] ?? '') !== 'p01_campaign_test'
            || ($config['username'] ?? '') !== 'p01_tester'
            || ($config['host'] ?? '') !== '127.0.0.1'
            || (int) ($config['port'] ?? 0) !== 3306
            || ! empty($config['url'])
            || ! empty($config['unix_socket'] ?? null)
            || ! empty($config['read'] ?? null)
            || ! empty($config['write'] ?? null)) {
            return false;
        }

        $identity = DB::selectOne('SELECT DATABASE() AS db_name, CURRENT_USER() AS db_user');
        return $identity !== null
            && $identity->db_name === 'p01_campaign_test'
            && str_starts_with((string) $identity->db_user, 'p01_tester@');
    }

    private function openingKey(int $productId): string
    {
        return 'opening:v1:product:' . $productId;
    }

    private function skuForProduct(object $product): ?string
    {
        // Prefer the existing business code if compatible with the 100-char SKU contract.
        $code = trim((string) ($product->product_code ?? ''));
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,99}\z/D', $code) === 1
            && ! DB::table('product_variants')->where('sku', $code)->exists()) {
            return $code;
        }

        // Stable, collision-safe fallback; if even this is occupied, stop for review.
        $fallback = self::legacySku((int) $product->id);
        return DB::table('product_variants')->where('sku', $fallback)->exists() ? null : $fallback;
    }

    private function conflictReason(object $product): ?string
    {
        if ((int) $product->inventory_version !== 0) {
            return 'unexpected inventory version';
        }
        if ((bool) $product->has_variants) {
            return 'explicit variant state must be reconciled manually';
        }
        if ((int) $product->stock < 0) {
            return 'negative stock';
        }
        if ((int) $product->stock > 2147483647) {
            return 'legacy stock exceeds the signed inventory movement delta range';
        }
        if (DB::table('product_variants')->where('product_id', $product->id)->exists()) {
            return 'product already has a variant';
        }
        if ($this->skuForProduct($product) === null) {
            return 'no collision-free legacy SKU available';
        }
        if (DB::table('inventory_movements')->where('operation_key', $this->openingKey((int) $product->id))->exists()) {
            return 'opening ledger already exists';
        }

        return null;
    }
}
