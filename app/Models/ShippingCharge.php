<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingCharge extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'campaign_id',
        'area_name',
        'delivery_charge',
        'status',
    ];

    protected $casts = [
        'campaign_id' => 'integer',
        'delivery_charge' => 'integer',
        'status' => 'boolean',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Return shipping rows for exactly one campaign.
     *
     * No runtime fallback is used. Campaign pages and order forms therefore
     * always read the delivery areas and charges configured for that campaign.
     * A null campaign id is reserved for legacy/global rows used outside a
     * campaign context.
     */
    public static function resolvedForCampaign(?int $campaignId, bool $activeOnly = true): Collection
    {
        $query = static::query()
            ->when(
                $campaignId !== null,
                fn ($query) => $query->where('campaign_id', $campaignId),
                fn ($query) => $query->whereNull('campaign_id')
            );

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('id')->get();
    }
}
