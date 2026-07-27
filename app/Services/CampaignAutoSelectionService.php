<?php

namespace App\Services;

use App\Models\Campaign;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CampaignAutoSelectionService
{
    /**
     * Per-request cache. Key format: sorted unique product IDs joined by comma.
     *
     * @var array<string, int|null>
     */
    private array $resolutionCache = [];

    /**
     * Resolve a Campaign for all selected products.
     *
     * Rules:
     * 1. Only active, non-deleted Campaigns are eligible.
     * 2. The Campaign must contain every distinct ordered product.
     * 3. If one or more matching Campaigns are active + default, choose default.
     * 4. If no default exists and exactly one active Campaign matches, choose it.
     * 5. If multiple active non-default Campaigns match, return null.
     * 6. If no active Campaign contains every product, return null.
     *
     * If bad legacy data contains multiple active defaults, the most recently
     * updated default is chosen deterministically, followed by the lowest ID.
     */
    public function resolveForProductIds(array|Collection $productIds): ?int
    {
        $productIds = collect($productIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->sort()
            ->values();

        if ($productIds->isEmpty()) {
            return null;
        }

        $cacheKey = $productIds->implode(',');

        if (array_key_exists($cacheKey, $this->resolutionCache)) {
            return $this->resolutionCache[$cacheKey];
        }

        /*
         * Resolve pivot candidates first. Keeping the aggregate query limited
         * to campaign_product avoids MySQL GROUP BY differences between local
         * and production servers and safely handles duplicate pivot rows.
         */
        $candidateCampaignIds = DB::table('campaign_product')
            ->select('campaign_id')
            ->whereIn('product_id', $productIds->all())
            ->groupBy('campaign_id')
            ->havingRaw(
                'COUNT(DISTINCT product_id) = ?',
                [$productIds->count()]
            )
            ->pluck('campaign_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($candidateCampaignIds->isEmpty()) {
            return $this->resolutionCache[$cacheKey] = null;
        }

        $candidates = Campaign::query()
            ->whereIn('id', $candidateCampaignIds->all())
            ->where('status', true)
            ->get([
                'id',
                'is_default',
                'updated_at',
            ]);

        if ($candidates->isEmpty()) {
            return $this->resolutionCache[$cacheKey] = null;
        }

        $defaultCampaigns = $candidates
            ->filter(fn (Campaign $campaign) => (bool) $campaign->is_default)
            ->sort(function (Campaign $left, Campaign $right): int {
                $leftTime = $left->updated_at?->getTimestamp() ?? 0;
                $rightTime = $right->updated_at?->getTimestamp() ?? 0;

                if ($leftTime !== $rightTime) {
                    return $rightTime <=> $leftTime;
                }

                return (int) $left->id <=> (int) $right->id;
            })
            ->values();

        if ($defaultCampaigns->isNotEmpty()) {
            return $this->resolutionCache[$cacheKey]
                = (int) $defaultCampaigns->first()->id;
        }

        if ($candidates->count() === 1) {
            return $this->resolutionCache[$cacheKey]
                = (int) $candidates->first()->id;
        }

        return $this->resolutionCache[$cacheKey] = null;
    }
}
