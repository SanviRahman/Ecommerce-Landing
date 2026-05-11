<?php

namespace Database\Seeders;

use App\Models\SocialMedia;
use Illuminate\Database\Seeder;

class SocialMediaSeeder extends Seeder
{
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
                'platform_name' => 'Messenger',
                'link' => 'https://m.me/yourpage',
                'icon_class' => 'fab fa-facebook-messenger',
                'status' => true,
            ],
            [
                'platform_name' => 'WhatsApp',
                'link' => 'https://wa.me/8801711111111',
                'icon_class' => 'fab fa-whatsapp',
                'status' => true,
            ],
            [
                'platform_name' => 'Phone',
                'link' => 'tel:01711111111',
                'icon_class' => 'fas fa-phone-alt',
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
                'platform_name' => 'LinkedIn',
                'link' => 'https://linkedin.com/company/yourcompany',
                'icon_class' => 'fab fa-linkedin-in',
                'status' => true,
            ],
        ];

        foreach ($socialMedias as $social) {
            SocialMedia::updateOrCreate(
                [
                    'platform_name' => $social['platform_name'],
                ],
                [
                    'link' => $social['link'],
                    'icon_class' => $social['icon_class'],
                    'status' => $social['status'],
                ]
            );
        }

        $this->command?->info('Social media seeded successfully.');
    }
}