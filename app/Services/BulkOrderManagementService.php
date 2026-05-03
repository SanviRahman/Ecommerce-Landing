<?php

namespace App\Services;

use App\Models\BulkOrder;
use Illuminate\Http\Request;

class BulkOrderManagementService
{
    public function create(array $data, Request $request): BulkOrder
    {
        return BulkOrder::create([
            'campaign_id' => $data['campaign_id'] ?? null,
            'customer_name' => $data['customer_name'],
            'phone' => $data['phone'],
            'company_name' => $data['company_name'] ?? null,
            'product_name' => $data['product_name'] ?? null,
            'expected_quantity' => $data['expected_quantity'] ?? 1,
            'address' => $data['address'] ?? null,
            'requirement_message' => $data['requirement_message'] ?? null,
            'status' => BulkOrder::STATUS_NEW,
            'source_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'source_url' => $request->headers->get('referer'),
        ]);
    }
}