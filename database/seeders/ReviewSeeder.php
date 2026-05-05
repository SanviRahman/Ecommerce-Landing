<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\Campaign;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ডাটাবেজে যদি কোনো Campaign থাকে, তবে তার ID নিবে, না থাকলে null বসাবে
        $campaignId = Campaign::first()->id ?? null;

        $reviews = [
            [
                'campaign_id' => $campaignId,
                'customer_name' => 'Rahim Uddin',
                'location' => 'Dhaka',
                'rating' => 5,
                'review_text' => 'খুবই সুন্দর একটি প্রোডাক্ট! ডেলিভারি অনেক ফাস্ট ছিল। ধন্যবাদ।',
                'social_link' => 'https://facebook.com',
                'status' => true,
            ],
            [
                'campaign_id' => null, // General Store Review
                'customer_name' => 'Sadia Islam',
                'location' => 'Chittagong',
                'rating' => 4,
                'review_text' => 'Product quality is good, but the packaging could be slightly better.',
                'social_link' => null,
                'status' => true,
            ],
            [
                'campaign_id' => $campaignId,
                'customer_name' => 'Arif Hossain',
                'location' => 'Sylhet',
                'rating' => 5,
                'review_text' => '100% authentic product. Highly recommended! I will buy again.',
                'social_link' => 'https://instagram.com',
                'status' => true,
            ],
            [
                'campaign_id' => null,
                'customer_name' => 'Mehedi Hasan',
                'location' => 'Rajshahi',
                'rating' => 5,
                'review_text' => 'যেরকম ছবি ও ভিডিওতে দেখেছি ঠিক সেরকমই পেয়েছি। সেলারের ব্যবহার অনেক ভালো।',
                'social_link' => null,
                'status' => true,
            ],
            [
                'campaign_id' => $campaignId,
                'customer_name' => 'Nusrat Jahan',
                'location' => 'Khulna',
                'rating' => 3,
                'review_text' => 'মোটামুটি ভালো, তবে প্রাইস অনুযায়ী কোয়ালিটি আরেকটু ভালো হতে পারত।',
                'social_link' => null,
                'status' => true,
            ],
        ];

        foreach ($reviews as $review) {
            Review::create($review);
        }
    }
}