<?php

namespace Database\Seeders;

use App\Models\CreatePage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pages = [
            [
                'page_name' => 'About Us',
                'description' => '<h2>Welcome to Our Store!</h2><p>We are dedicated to providing the best quality products at affordable prices. Customer satisfaction is our first priority.</p>',
                'meta_title' => 'About Us | Best E-commerce Store',
                'meta_description' => 'Learn more about our mission, vision, and the amazing team behind our success.',
                'status' => true,
            ],
            [
                'page_name' => 'Privacy Policy',
                'description' => '<h2>Privacy Policy</h2><p>Your privacy is important to us. This policy outlines how we collect, use, and protect your personal information.</p>',
                'meta_title' => 'Privacy Policy | Safe & Secure',
                'meta_description' => 'Read our privacy policy to understand how we keep your data safe and secure.',
                'status' => true,
            ],
            [
                'page_name' => 'Terms and Conditions',
                'description' => '<h2>Terms & Conditions</h2><p>By accessing and using this website, you agree to comply with our terms and conditions.</p>',
                'meta_title' => 'Terms and Conditions',
                'meta_description' => 'Detailed terms and conditions for using our platform and making purchases.',
                'status' => true,
            ],
            [
                'page_name' => 'Refund Policy',
                'description' => '<h2>Refund & Return Policy</h2><p>We offer a hassle-free 7-day return policy for all intact and unused products.</p>',
                'meta_title' => 'Refund Policy | Easy Returns',
                'meta_description' => 'Check out our easy and transparent refund and return policy.',
                'status' => true,
            ],
        ];

        foreach ($pages as $page) {
            // firstOrCreate
            CreatePage::firstOrCreate(
                ['slug' => Str::slug($page['page_name'])],
                [
                    'page_name' => $page['page_name'],
                    'description' => $page['description'],
                    'meta_title' => $page['meta_title'],
                    'meta_description' => $page['meta_description'],
                    'status' => $page['status'],
                ]
            );
        }
    }
}