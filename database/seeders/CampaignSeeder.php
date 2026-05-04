<?php

namespace Database\Seeders;

use App\Models\Campaign;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CampaignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $campaigns = [
            [
                'title' => 'Shanto Gift Shop BD',
                'offer_text' => 'আজকের স্পেশাল অফার',
                'old_price' => 1500,
                'new_price' => 999,
            ],
            [
                'title' => 'Premium Honey Offer',
                'offer_text' => 'খাঁটি মধু কিনুন বিশেষ ছাড়ে',
                'old_price' => 1200,
                'new_price' => 850,
            ],
            [
                'title' => 'Organic Black Seed Oil',
                'offer_text' => 'স্বাস্থ্যকর জীবনের জন্য প্রিমিয়াম কালোজিরা তেল',
                'old_price' => 1000,
                'new_price' => 699,
            ],
            [
                'title' => 'Herbal Hair Oil Campaign',
                'offer_text' => 'চুলের যত্নে হারবাল হেয়ার অয়েল',
                'old_price' => 900,
                'new_price' => 599,
            ],
            [
                'title' => 'Beauty Care Combo',
                'offer_text' => 'স্কিন কেয়ারে কম্বো অফার',
                'old_price' => 2200,
                'new_price' => 1590,
            ],
        ];

        foreach ($campaigns as $campaign) {
            Campaign::create([
                'title' => $campaign['title'],
                'slug' => $this->generateUniqueSlug($campaign['title']),

                'campaign_type' => 'single',

                'short_description' => $campaign['title'] . ' একটি বিশেষ অফার ক্যাম্পেইন। সীমিত সময়ের জন্য এই অফারটি প্রযোজ্য।',
                'full_description' => $campaign['title'] . ' ক্যাম্পেইনের মাধ্যমে আপনি সেরা মানের পণ্য সবচেয়ে ভালো দামে অর্ডার করতে পারবেন। আমাদের সকল পণ্য কোয়ালিটি চেক করা এবং দ্রুত ডেলিভারি সুবিধাসহ পাওয়া যায়।',

                'offer_text' => $campaign['offer_text'],

                'benefits_text' => [
                    'প্রিমিয়াম কোয়ালিটি',
                    'দ্রুত হোম ডেলিভারি',
                    'ক্যাশ অন ডেলিভারি সুবিধা',
                    'সীমিত সময়ের বিশেষ ছাড়',
                ],

                'comparison_text' => [
                    'বাজারের সাধারণ পণ্যের তুলনায় উন্নত মান',
                    'বিশ্বস্ত সোর্স থেকে সংগ্রহ করা',
                    'কাস্টমার সাপোর্ট সুবিধা',
                ],

                'old_price' => $campaign['old_price'],
                'new_price' => $campaign['new_price'],

                'button_text' => 'অর্ডার করুন',

                'order_form_title' => 'অফারটি সীমিত সময়ের জন্য, তাই অফার শেষ হওয়ার আগেই অর্ডার করুন',
                'order_form_subtitle' => 'অর্ডার করতে নিচের ফর্মটি পূরণ করুন।',

                'enable_bulk_order' => false,

                'status' => true,

                'meta_title' => $campaign['title'],
                'meta_description' => $campaign['title'] . ' - বিশেষ ছাড়ে এখনই অর্ডার করুন।',
            ]);
        }
    }

    private function generateUniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title);

        if (! $baseSlug) {
            $baseSlug = 'campaign-' . time();
        }

        $slug = $baseSlug;
        $count = 1;

        while (Campaign::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }
}