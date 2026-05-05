<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Campaign;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ডাটাবেজে যদি কোনো Campaign থাকে, তবে তার ID নিবে, না থাকলে null বসাবে
        $campaignId = Campaign::first()->id ?? null;

        $faqs = [
            // General FAQs (Applies globally)
            [
                'campaign_id' => null, 
                'question' => 'How long does shipping usually take?',
                'answer' => 'Our standard delivery time is 2-3 business days inside Dhaka, and 3-5 business days outside Dhaka.',
                'sort_order' => 1,
                'status' => true,
            ],
            [
                'campaign_id' => null,
                'question' => 'What is your return and refund policy?',
                'answer' => 'We offer a hassle-free 7-day return policy. If you receive a damaged or incorrect product, we will replace it or refund your money.',
                'sort_order' => 2,
                'status' => true,
            ],
            [
                'campaign_id' => null,
                'question' => 'Do you offer cash on delivery (COD)?',
                'answer' => 'Yes, we offer Cash on Delivery all over Bangladesh. However, for certain heavy items, a partial advance payment might be required.',
                'sort_order' => 3,
                'status' => true,
            ],
            
            // Campaign Specific FAQs (Only shows on specific landing pages)
            [
                'campaign_id' => $campaignId, 
                'question' => 'Is this discount applicable for old customers?',
                'answer' => 'Yes! This special campaign offer is valid for both our new and loyal existing customers.',
                'sort_order' => 1,
                'status' => true,
            ],
            [
                'campaign_id' => $campaignId,
                'question' => 'Can I return a product bought under this sale?',
                'answer' => 'Products purchased under mega clearance sales are generally non-refundable unless they are delivered damaged or defective.',
                'sort_order' => 2,
                'status' => true,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::create($faq);
        }
    }
}