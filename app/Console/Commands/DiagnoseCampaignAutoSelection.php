<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Product;
use App\Services\CampaignAutoSelectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseCampaignAutoSelection extends Command
{
    protected $signature = 'campaigns:diagnose-auto-selection {product_codes* : Product codes used in one order}';

    protected $description = 'Show matching active campaigns and the campaign selected by the shared auto-selection resolver.';

    public function handle(CampaignAutoSelectionService $resolver): int
    {
        $codes = collect($this->argument('product_codes'))
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values();

        $products = Product::query()
            ->where(function ($query) use ($codes) {
                foreach ($codes as $code) {
                    $query->orWhereRaw('LOWER(product_code) = ?', [mb_strtolower($code, 'UTF-8')]);
                }
            })
            ->get(['id', 'name', 'product_code', 'status']);

        $foundCodes = $products
            ->pluck('product_code')
            ->map(fn ($code) => mb_strtolower((string) $code, 'UTF-8'));

        $missing = $codes->reject(
            fn ($code) => $foundCodes->contains(mb_strtolower($code, 'UTF-8'))
        );

        if ($missing->isNotEmpty()) {
            $this->error('Missing product codes: ' . $missing->implode(', '));
            return self::FAILURE;
        }

        $productIds = $products->pluck('id')->map(fn ($id) => (int) $id)->all();

        $candidateIds = DB::table('campaign_product')
            ->select('campaign_id')
            ->whereIn('product_id', $productIds)
            ->groupBy('campaign_id')
            ->havingRaw('COUNT(DISTINCT product_id) = ?', [count(array_unique($productIds))])
            ->pluck('campaign_id');

        $campaigns = Campaign::query()
            ->whereIn('id', $candidateIds)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get(['id', 'title', 'slug', 'status', 'is_default']);

        $this->table(
            ['ID', 'Campaign', 'Slug', 'Active', 'Default'],
            $campaigns->map(fn (Campaign $campaign) => [
                $campaign->id,
                $campaign->title,
                $campaign->slug,
                $campaign->status ? 'Yes' : 'No',
                $campaign->is_default ? 'Yes' : 'No',
            ])->all()
        );

        $selectedId = $resolver->resolveForProductIds($productIds);

        if (! $selectedId) {
            $this->warn('Resolver result: No Campaign');
            return self::SUCCESS;
        }

        $selected = Campaign::query()->find($selectedId);
        $this->info('Resolver result: ' . ($selected?->title ?: "Campaign #{$selectedId}"));

        return self::SUCCESS;
    }
}
