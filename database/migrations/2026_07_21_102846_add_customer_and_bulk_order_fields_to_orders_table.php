<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'customer_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('customer_id')
                    ->nullable()
                    ->after('campaign_id')
                    ->constrained('customers')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('orders', 'bulk_order_batch_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('bulk_order_batch_id')
                    ->nullable()
                    ->after('customer_id')
                    ->constrained('bulk_order_batches')
                    ->nullOnDelete();
            });
        }

        $this->backfillExistingCustomers();
    }

    private function backfillExistingCustomers(): void
    {
        DB::table('orders')
            ->whereNull('customer_id')
            ->orderBy('id')
            ->select(['id', 'customer_name', 'phone'])
            ->chunkById(500, function ($orders) {
                foreach ($orders as $order) {
                    $phone = preg_replace('/\D+/', '', (string) $order->phone) ?: '';

                    if (! preg_match('/^01\d{9}$/', $phone)) {
                        continue;
                    }

                    $name = trim(preg_replace('/\s+/u', ' ', (string) $order->customer_name));

                    if ($name === '') {
                        $name = 'Customer';
                    }

                    $normalizedName = mb_strtolower($name, 'UTF-8');
                    $customer = DB::table('customers')->where('phone', $phone)->first();

                    if (! $customer) {
                        $customerId = DB::table('customers')->insertGetId([
                            'name'            => $name,
                            'normalized_name' => $normalizedName,
                            'phone'           => $phone,
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ]);
                    } else {
                        $customerId = $customer->id;
                    }

                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update(['customer_id' => $customerId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'bulk_order_batch_id')) {
                $table->dropConstrainedForeignId('bulk_order_batch_id');
            }

            if (Schema::hasColumn('orders', 'customer_id')) {
                $table->dropConstrainedForeignId('customer_id');
            }
        });
    }
};
