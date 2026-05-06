<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;

class CampaignPageController extends Controller
{
    public function show(Campaign $campaign)
    {
        abort_if(! $campaign->status, 404);

        $campaign->load([
            'products' => function ($query) {
                $query->active()
                    ->orderByPivot('sort_order');
            },
        ]);

        return view('frontend.campaigns.show', [
            'campaign'        => $campaign,
            'products'        => $campaign->products,
            'courierServices' => config('couriers.list', []),
        ]);
    }
}