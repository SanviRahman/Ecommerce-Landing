<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        $this->dropPhoneOnlyUniqueIndex();
        $this->ensurePhoneLookupIndex();
        $this->realignOrdersByCustomerIdentity();
        $this->mergeDuplicateCustomerIdentities();
        $this->ensureCustomerIdentityUniqueIndex();
    }

    public function down(): void
    {
        if (! Schema::hasTable('customers')) {
            return;
        }

        $hasSharedPhones = DB::table('customers')
            ->select('phone')
            ->whereNotNull('phone')
            ->where('phone', '<>', '')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasSharedPhones) {
            throw new \RuntimeException(
                'Cannot restore unique customer phone numbers while multiple customers share the same phone.'
            );
        }

        $identityIndex = $this->findIndex(
            ['normalized_name', 'phone'],
            true
        );

        if ($identityIndex) {
            Schema::table('customers', function (Blueprint $table) use ($identityIndex): void {
                $table->dropUnique((string) $identityIndex['name']);
            });
        }

        $phoneIndex = $this->findIndex(['phone'], false);

        if ($phoneIndex) {
            Schema::table('customers', function (Blueprint $table) use ($phoneIndex): void {
                $table->dropIndex((string) $phoneIndex['name']);
            });
        }

        if (! $this->findIndex(['phone'], true)) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->unique('phone', 'customers_phone_unique');
            });
        }
    }

    private function dropPhoneOnlyUniqueIndex(): void
    {
        $phoneUniqueIndex = $this->findIndex(['phone'], true);

        if (! $phoneUniqueIndex) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) use ($phoneUniqueIndex): void {
            $table->dropUnique((string) $phoneUniqueIndex['name']);
        });
    }

    private function ensurePhoneLookupIndex(): void
    {
        if ($this->findIndex(['phone'])) {
            return;
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->index('phone', 'customers_phone_index');
        });
    }

    private function ensureCustomerIdentityUniqueIndex(): void
    {
        if ($this->findIndex(['normalized_name', 'phone'], true)) {
            return;
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->unique(
                ['normalized_name', 'phone'],
                'customers_normalized_name_phone_unique'
            );
        });
    }

    private function realignOrdersByCustomerIdentity(): void
    {
        if (
            ! Schema::hasTable('orders')
            || ! Schema::hasColumn('orders', 'customer_id')
            || ! Schema::hasColumn('orders', 'customer_name')
            || ! Schema::hasColumn('orders', 'phone')
        ) {
            return;
        }

        DB::table('orders')
            ->orderBy('id')
            ->select(['id', 'customer_id', 'customer_name', 'phone'])
            ->chunkById(500, function ($orders): void {
                foreach ($orders as $order) {
                    $phone = preg_replace('/\D+/', '', (string) $order->phone) ?: '';

                    if (! preg_match('/^01\d{9}$/', $phone)) {
                        continue;
                    }

                    $name = trim(preg_replace('/\s+/u', ' ', (string) $order->customer_name));
                    $name = $name !== '' ? $name : 'Customer';
                    $normalizedName = mb_strtolower($name, 'UTF-8');

                    $customerId = DB::table('customers')
                        ->where('normalized_name', $normalizedName)
                        ->where('phone', $phone)
                        ->value('id');

                    if (! $customerId) {
                        $customerId = DB::table('customers')->insertGetId([
                            'name' => $name,
                            'normalized_name' => $normalizedName,
                            'phone' => $phone,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    if ((int) $order->customer_id !== (int) $customerId) {
                        DB::table('orders')
                            ->where('id', $order->id)
                            ->update(['customer_id' => $customerId]);
                    }
                }
            });
    }

    private function mergeDuplicateCustomerIdentities(): void
    {
        $duplicates = DB::table('customers')
            ->select([
                'normalized_name',
                'phone',
                DB::raw('MIN(id) as keeper_id'),
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('normalized_name', 'phone')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $duplicateIds = DB::table('customers')
                ->where('normalized_name', $duplicate->normalized_name)
                ->where('phone', $duplicate->phone)
                ->where('id', '<>', $duplicate->keeper_id)
                ->pluck('id');

            if ($duplicateIds->isEmpty()) {
                continue;
            }

            if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'customer_id')) {
                DB::table('orders')
                    ->whereIn('customer_id', $duplicateIds)
                    ->update(['customer_id' => $duplicate->keeper_id]);
            }

            DB::table('customers')->whereIn('id', $duplicateIds)->delete();
        }
    }

    private function findIndex(array $columns, ?bool $unique = null): ?array
    {
        foreach (Schema::getIndexes('customers') as $index) {
            if ($unique !== null && (bool) ($index['unique'] ?? false) !== $unique) {
                continue;
            }

            if (array_values($index['columns'] ?? []) === array_values($columns)) {
                return $index;
            }
        }

        return null;
    }
};
