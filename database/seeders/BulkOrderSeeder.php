<?php

namespace Database\Seeders;

use App\Models\BulkOrder;
use App\Models\Campaign;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BulkOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ডাটাবেজে যদি কোনো Campaign থাকে, তবে তার ID নিবে, না থাকলে null বসাবে
        $campaignId = Campaign::first()->id ?? null;

        $bulkOrders = [
            [
                'campaign_id' => $campaignId,
                'customer_name' => 'Tanvir Ahmed',
                'phone' => '01711223344',
                'company_name' => 'Tech Solutions BD',
                'product_name' => 'Corporate Gift Box',
                'expected_quantity' => 150,
                'address' => 'Banani, Dhaka',
                'requirement_message' => 'We need 150 gift boxes for our upcoming corporate event. Please include our company logo on the box.',
                'status' => 'new',
                'admin_note' => null,
                'source_ip' => '103.111.222.33',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'source_url' => 'https://yoursite.com/landing-page-1',
            ],
            [
                'campaign_id' => null,
                'customer_name' => 'Farhana Islam',
                'phone' => '01855667788',
                'company_name' => 'Fashion House Ltd',
                'product_name' => 'Premium Panjabi',
                'expected_quantity' => 500,
                'address' => 'Agrabad, Chittagong',
                'requirement_message' => 'Looking for wholesale pricing for upcoming Eid collection. Need 500 pieces in mixed sizes.',
                'status' => 'contacted',
                'admin_note' => 'Called the customer. They will send the size ratio via email.',
                'source_ip' => '114.31.200.15',
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X)',
                'source_url' => 'https://yoursite.com/wholesale',
            ],
            [
                'campaign_id' => $campaignId,
                'customer_name' => 'Jamal Hossain',
                'phone' => '01999888777',
                'company_name' => null, // Individual bulk buyer
                'product_name' => 'Custom Polo Shirts',
                'expected_quantity' => 50,
                'address' => 'Sylhet Sadar, Sylhet',
                'requirement_message' => 'University batch tour t-shirts needed. Please share fabric options.',
                'status' => 'processing',
                'admin_note' => 'Sample sent via Pathao courier. Waiting for confirmation.',
                'source_ip' => '103.45.67.89',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
                'source_url' => 'https://yoursite.com/campaign/polo-shirts',
            ],
            [
                'campaign_id' => null,
                'customer_name' => 'Rafiqul Islam',
                'phone' => '01511222333',
                'company_name' => 'ABC Traders',
                'product_name' => 'Electronics Gadgets',
                'expected_quantity' => 1000,
                'address' => 'Motijheel, Dhaka',
                'requirement_message' => 'Want to be a distributor for your smartwatches. Send catalog.',
                'status' => 'completed',
                'admin_note' => 'Deal closed. Order shipped.',
                'source_ip' => '27.147.205.10',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'source_url' => 'https://yoursite.com/contact-us',
            ],
        ];

        foreach ($bulkOrders as $order) {
            BulkOrder::firstOrCreate(
                ['phone' => $order['phone']], 
                $order
            );
        }
    }
}