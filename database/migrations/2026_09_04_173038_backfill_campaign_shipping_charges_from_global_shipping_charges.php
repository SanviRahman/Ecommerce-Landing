<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('campaigns')
            || ! Schema::hasTable('shipping_charges')
            || ! Schema::hasColumn('shipping_charges', 'campaign_id')
        ) {
            return;
        }

        $globalQuery = DB::table('shipping_charges')
            ->whereNull('campaign_id');

        if (Schema::hasColumn('shipping_charges', 'deleted_at')) {
            $globalQuery->whereNull('deleted_at');
        }

        $globalRows = $globalQuery->orderBy('id')->get();

        if ($globalRows->isEmpty()) {
            return;
        }

        $campaignIds = DB::table('campaigns')->pluck('id');
        $now = now();

        foreach ($campaignIds as $campaignId) {
            $hasCampaignRows = DB::table('shipping_charges')
                ->where('campaign_id', $campaignId)
                ->exists();

            if ($hasCampaignRows) {
                continue;
            }

            foreach ($globalRows as $globalRow) {
                $row = [
                    'campaign_id' => $campaignId,
                    'area_name' => $globalRow->area_name,
                    'delivery_charge' => $globalRow->delivery_charge,
                    'status' => (bool) $globalRow->status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (Schema::hasColumn('shipping_charges', 'deleted_at')) {
                    $row['deleted_at'] = null;
                }

                DB::table('shipping_charges')->insert($row);
            }
        }
    }

    public function down(): void
    {
        // Data backfill is intentionally not reversed to avoid deleting
        // campaign delivery areas that may have been edited after deployment.
    }
};
