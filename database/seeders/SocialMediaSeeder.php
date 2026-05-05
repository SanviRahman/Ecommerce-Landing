<?php

namespace Database\Seeders;

use App\Models\SocialMedia;
use Illuminate\Database\Seeder;

class SocialMediaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $socialMedias = [
            [
                'platform_name' => 'Facebook',
                'link' => 'https://facebook.com/yourpage',
                'icon_class' => 'fab fa-facebook-f',
                'status' => true,
            ],
            [
                'platform_name' => 'Instagram',
                'link' => 'https://instagram.com/yourprofile',
                'icon_class' => 'fab fa-instagram',
                'status' => true,
            ],
            [
                'platform_name' => 'YouTube',
                'link' => 'https://youtube.com/c/yourchannel',
                'icon_class' => 'fab fa-youtube',
                'status' => true,
            ],
            [
                'platform_name' => 'Twitter / X',
                'link' => 'https://twitter.com/yourhandle',
                'icon_class' => 'fab fa-x-twitter', // or 'fab fa-twitter' depending on your FontAwesome version
                'status' => true,
            ],
            [
                'platform_name' => 'LinkedIn',
                'link' => 'https://linkedin.com/company/yourcompany',
                'icon_class' => 'fab fa-linkedin-in',
                'status' => true,
            ],
            [
                'platform_name' => 'WhatsApp',
                'link' => 'https://wa.me/1234567890',
                'icon_class' => 'fab fa-whatsapp',
                'status' => true,
            ],
        ];

        foreach ($socialMedias as $social) {
            // firstOrCreate
            SocialMedia::firstOrCreate(
                ['platform_name' => $social['platform_name']],
                [
                    'link' => $social['link'],
                    'icon_class' => $social['icon_class'],
                    'status' => $social['status'],
                ]
            );
        }
    }
}