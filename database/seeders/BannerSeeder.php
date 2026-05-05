<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $banners = [
            [
                'title' => 'Spring Mega Sale - Up to 50% Off!',
                'position' => 'hero',
                'link' => '/campaigns/spring-sale',
                'sort_order' => 1,
                'status' => true,
            ],
            [
                'title' => 'Free Shipping on Orders Over ৳2000',
                'position' => 'hero',
                'link' => '/products',
                'sort_order' => 2,
                'status' => true,
            ],
            [
                'title' => 'New Arrivals Collection',
                'position' => 'middle',
                'link' => '/products?sort=new',
                'sort_order' => 1,
                'status' => true,
            ],
            [
                'title' => '10% Welcome Discount',
                'position' => 'popup',
                'link' => '/register',
                'sort_order' => 1,
                'status' => true,
            ],
            [
                'title' => 'Download Our Mobile App',
                'position' => 'footer',
                'link' => '#',
                'sort_order' => 1,
                'status' => true,
            ],
            [
                'title' => 'Need Help? Call Us 24/7',
                'position' => 'help',
                'link' => '/pages/contact-us',
                'sort_order' => 1,
                'status' => true,
            ],
        ];

        foreach ($banners as $banner) {
            // firstOrCreate
            Banner::firstOrCreate(
                [
                    'title' => $banner['title'], 
                    'position' => $banner['position']
                ],
                [
                    'link' => $banner['link'],
                    'sort_order' => $banner['sort_order'],
                    'status' => $banner['status'],
                ]
            );
        }
    }
}