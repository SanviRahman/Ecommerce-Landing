<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Demo Campaign
        |--------------------------------------------------------------------------
        */
        $campaign = Campaign::updateOrCreate(
            [
                'slug' => 'shanto-gift-shop-bd',
            ],
            [
                'title' => 'Shanto Gift Shop BD',
                'campaign_type' => 'multiple',

                'short_description' => 'Shanto Gift Shop BD একটি বিশেষ অফার ক্যাম্পেইন। সীমিত সময়ের জন্য এই অফারটি প্রযোজ্য।',
                'full_description' => 'Premium quality products with cash on delivery service.',

                'offer_text' => 'আজকের স্পেশাল অফার',

                /*
                |--------------------------------------------------------------------------
                | Embed Video URL
                |--------------------------------------------------------------------------
                | চাইলে YouTube/Facebook embed URL দিতে পারো।
                | Upload video না থাকলে এই video frontend hero section-এ show হবে।
                */
                'embed_video_url' => null,

                'benefits_text' => [
                    'গাছের মাছ',
                    'ইলিশের মাথা',
                    'মাছ',
                    'দেশী',
                    'খাসির মাথা',
                    'মুরগী মট',
                    'সরিষা',
                    'কালিজিরা',
                    'চটপটি',
                ],

                'comparison_text' => [
                    'left_title' => 'গাছ চুইঝাল',
                    'right_title' => 'এটা চুইঝাল',
                    'left' => [
                        'চুইঝাল গাছের কাণ্ডকে গাছ চুইঝাল বলা হয়।',
                        'গাছ চুইঝাল সাধারণত রান্নায় সহজে গলে যায়।',
                        'রান্নায় ঝাঁজ ও ঘ্রাণ বাড়াতে ব্যবহার করা হয়।',
                        'সাধারণত বড় পরিমাণে ব্যবহার করা হয়।',
                        'এটি রান্নার স্বাদকে আলাদা করে তোলে।',
                    ],
                    'right' => [
                        'চুইঝাল গাছের গোড়া এবং গোড়া সংলগ্ন অংশকে এটা চুইঝাল বলা হয়।',
                        'এটা চুইঝাল ফাইবার কম থাকায় রান্নায় ভালো ফ্লেভার দেয়।',
                        'মাছ, ডাল ও তরকারিতে ব্যবহার করা যায়।',
                        'মসলা হিসেবে স্বাদ ও ঘ্রাণ বাড়াতে ব্যবহার করা হয়।',
                        'এটি সাধারণ খাবারকেও সুস্বাদু করে তোলে।',
                    ],
                ],

                'section_titles' => [
                    'category_title' => 'ক্যাটাগরি সমূহ',
                    'brand_title' => 'ব্র্যান্ড সমূহ',
                    'product_title' => 'আমাদের প্রোডাক্ট',
                    'category_filter_title' => 'ক্যাটাগরি দিয়ে ফিল্টার',
                    'brand_filter_title' => 'ব্র্যান্ড দিয়ে ফিল্টার',
                    'comparison_title' => 'চুইঝালের পার্থক্যসমূহ',
                    'service_title' => 'কেন আমরাই সেরা',
                    'review_title' => 'কাস্টমার রিভিউ',
                    'faq_title' => 'সচরাচর জিজ্ঞাস্য প্রশ্নাবলি',
                    'gallery_title' => 'প্রোডাক্ট গ্যালারি',
                    'order_title' => 'অর্ডার করুন এখনই',
                ],

                'service_items' => [
                    [
                        'icon' => 'fas fa-award',
                        'title' => 'অর্গানিক প্রোডাক্ট',
                        'description' => 'আমাদের কাছে পাবেন সেরা মানের প্রিমিয়াম পণ্য।',
                    ],
                    [
                        'icon' => 'fas fa-crown',
                        'title' => 'প্রিমিয়াম কোয়ালিটি',
                        'description' => 'সেরা কোয়ালিটির পণ্য সংগ্রহ করে সরবরাহ করা হয়।',
                    ],
                    [
                        'icon' => 'fas fa-undo-alt',
                        'title' => 'রিটার্ন পলিসি',
                        'description' => 'সমস্যা হলে সহজ রিটার্ন ও রিপ্লেসমেন্ট সুবিধা।',
                    ],
                    [
                        'icon' => 'fas fa-truck',
                        'title' => 'ক্যাশ অন ডেলিভারি',
                        'description' => 'পণ্য হাতে পেয়ে টাকা পরিশোধ করার সুবিধা।',
                    ],
                ],

                'help_content' => [
                    'title' => 'সাহায্য প্রয়োজন?',
                    'description' => 'যেকোনো জিজ্ঞাসা ও অর্ডারজনিত সমস্যায় কল করুন আমাদের হেল্পলাইনে অথবা নক করুন আমাদের হোয়াটসঅ্যাপ বা ফেসবুক পেজে। আমরা আছি সকাল ১০ টা থেকে রাত ৮ টা পর্যন্ত আপনার সেবায়।',
                    'button_text' => 'হেল্পলাইন',
                ],

                'button_text' => 'অর্ডার করুন',

                /*
                |--------------------------------------------------------------------------
                | Campaign Wise Hero Contact
                |--------------------------------------------------------------------------
                | এই ২টা field campaign-wise WhatsApp এবং Phone button dynamic করবে।
                | Admin panel থেকেও create/edit করা যাবে।
                */
                'hero_whatsapp' => '01812345678',
                'hero_phone' => '01712345678',

                'order_form_title' => 'ডেলিভারি এড্রেস',
                'order_form_subtitle' => 'সঠিক তথ্য দিয়ে অর্ডার করুন।',

                'enable_bulk_order' => false,

                'hero_section_status' => true,
                'benefits_section_status' => true,
                'category_section_status' => true,
                'product_section_status' => true,
                'comparison_section_status' => true,
                'service_section_status' => true,
                'review_section_status' => true,
                'gallery_section_status' => true,
                'faq_section_status' => true,
                'order_section_status' => true,

                'status' => true,
                'meta_title' => 'Shanto Gift Shop BD',
                'meta_description' => 'Premium ecommerce landing campaign.',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Selected Categories
        |--------------------------------------------------------------------------
        | Admin je order-e select korbe, frontend-e sei order-e show hobe.
        */
        $categoryIds = Category::query()
            ->where('status', true)
            ->whereIn('name', [
                'Clothing',
                'Natural Oil',
                'Organic Food',
                'Home & Kitchen',
            ])
            ->pluck('id')
            ->values()
            ->toArray();

        if (empty($categoryIds)) {
            $categoryIds = Category::query()
                ->where('status', true)
                ->take(4)
                ->pluck('id')
                ->values()
                ->toArray();
        }

        $categorySyncData = [];

        foreach ($categoryIds as $index => $categoryId) {
            $categorySyncData[$categoryId] = [
                'sort_order' => $index + 1,
            ];
        }

        $campaign->categories()->sync($categorySyncData);

        /*
        |--------------------------------------------------------------------------
        | Selected Brands
        |--------------------------------------------------------------------------
        */
        $brandIds = Brand::query()
            ->where('status', true)
            ->whereIn('name', [
                'Apple',
                'Samsung',
                'Nike',
                'Sony',
            ])
            ->pluck('id')
            ->values()
            ->toArray();

        if (empty($brandIds)) {
            $brandIds = Brand::query()
                ->where('status', true)
                ->take(4)
                ->pluck('id')
                ->values()
                ->toArray();
        }

        $brandSyncData = [];

        foreach ($brandIds as $index => $brandId) {
            $brandSyncData[$brandId] = [
                'sort_order' => $index + 1,
            ];
        }

        $campaign->brands()->sync($brandSyncData);

        /*
        |--------------------------------------------------------------------------
        | Selected Products
        |--------------------------------------------------------------------------
        */
        $productIds = Product::query()
            ->where('status', true)
            ->take(8)
            ->pluck('id')
            ->values()
            ->toArray();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $productSyncData = [];

        foreach ($productIds as $index => $productId) {
            if (! isset($products[$productId])) {
                continue;
            }

            $product = $products[$productId];

            $productSyncData[$productId] = [
                'campaign_price' => $product->new_price,
                'sort_order' => $index + 1,
                'is_default' => $index === 0,
            ];
        }

        $campaign->products()->sync($productSyncData);

        $this->seedDefaultShippingCharges();

        $this->command?->info('Campaign seeded successfully with categories, brands, products, hero contact numbers and shipping charges.');
    }

    /**
     * Seed default shipping charges.
     *
     * This method is column-safe:
     * - New code/model may use: area_name, delivery_charge
     * - Old/demo code may use: region, charge
     */
    private function seedDefaultShippingCharges(): void
    {
        if (! Schema::hasTable('shipping_charges')) {
            $this->command?->warn('shipping_charges table not found. Run migration first.');
            return;
        }

        $nameColumn = Schema::hasColumn('shipping_charges', 'area_name')
            ? 'area_name'
            : (Schema::hasColumn('shipping_charges', 'region') ? 'region' : null);

        $chargeColumn = Schema::hasColumn('shipping_charges', 'delivery_charge')
            ? 'delivery_charge'
            : (Schema::hasColumn('shipping_charges', 'charge') ? 'charge' : null);

        if (! $nameColumn || ! $chargeColumn) {
            $this->command?->warn('shipping_charges table column mismatch. Expected area_name/delivery_charge or region/charge.');
            return;
        }

        $now = now();

        $rows = [
            [
                'name' => 'ঢাকার ভিতরে',
                'charge' => 70,
                'sort_order' => 1,
            ],
            [
                'name' => 'ঢাকার বাইরে',
                'charge' => 130,
                'sort_order' => 2,
            ],
        ];

        foreach ($rows as $row) {
            $values = [
                $chargeColumn => $row['charge'],
                'status' => true,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('shipping_charges', 'sort_order')) {
                $values['sort_order'] = $row['sort_order'];
            }

            DB::table('shipping_charges')->updateOrInsert(
                [$nameColumn => $row['name']],
                array_merge($values, [
                    $nameColumn => $row['name'],
                    'created_at' => $now,
                ])
            );
        }
    }

}
