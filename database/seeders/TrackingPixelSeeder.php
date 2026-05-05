<?php

namespace Database\Seeders;

use App\Models\TrackingPixel;
use Illuminate\Database\Seeder;

class TrackingPixelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $metaPixelId = '123456789012345';

        $metaPixelScript = <<<'META_PIXEL'
<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '123456789012345');
fbq('track', 'PageView');
</script>
<noscript>
<img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id=123456789012345&ev=PageView&noscript=1"/>
</noscript>
<!-- End Meta Pixel Code -->
META_PIXEL;

        TrackingPixel::updateOrCreate(
            [
                'platform' => 'meta',
                'pixel_id' => $metaPixelId,
            ],
            [
                'name' => 'Test Meta Pixel',
                'script_code' => $metaPixelScript,
                'status' => true,
            ]
        );
    }
}