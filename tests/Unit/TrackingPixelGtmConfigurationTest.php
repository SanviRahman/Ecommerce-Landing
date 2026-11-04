<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\TrackingPixelController;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class TrackingPixelGtmConfigurationTest extends TestCase
{
    public function test_gtm_container_id_can_be_used_without_a_full_tracking_script(): void
    {
        $controller = new TrackingPixelController();
        $pixelId = $this->invokePrivate($controller, 'extractIdentifier', [
            '',
            'gtm',
            'gtm-ts7q7wgg',
        ]);

        $this->assertSame('GTM-TS7Q7WGG', $pixelId);

        $this->invokePrivate($controller, 'assertValidPixel', [
            'gtm',
            $pixelId,
            '',
            'pixel_id',
        ]);

        $this->assertTrue(true);
    }

    public function test_container_id_can_be_extracted_from_a_legacy_gtm_snippet(): void
    {
        $controller = new TrackingPixelController();
        $script = '<script src="https://www.googletagmanager.com/gtm.js?id=gtm-ts7q7wgg"></script>';
        $pixelId = $this->invokePrivate($controller, 'extractIdentifier', [
            $script,
            'gtm',
            null,
        ]);

        $this->assertSame('GTM-TS7Q7WGG', $pixelId);
        $this->invokePrivate($controller, 'assertValidPixel', [
            'gtm',
            $pixelId,
            $script,
            'pixel_id',
        ]);
    }

    public function test_gtm_container_id_is_validated_even_when_a_script_is_present(): void
    {
        $this->expectException(ValidationException::class);

        $this->invokePrivate(new TrackingPixelController(), 'assertValidPixel', [
            'gtm',
            '2100861233911035',
            '<script>/* GTM-TS7Q7WGG */</script>',
            'pixel_id',
        ]);
    }

    public function test_manual_gtm_id_must_match_the_id_inside_a_legacy_script(): void
    {
        $this->expectException(ValidationException::class);

        $this->invokePrivate(new TrackingPixelController(), 'assertValidPixel', [
            'gtm',
            'GTM-AAAAAAAA',
            '<script src="https://www.googletagmanager.com/gtm.js?id=GTM-BBBBBBBB"></script>',
            'pixel_id',
        ]);
    }

    public function test_matching_legacy_gtm_script_remains_supported(): void
    {
        $this->invokePrivate(new TrackingPixelController(), 'assertValidPixel', [
            'gtm',
            'GTM-TS7Q7WGG',
            '<script src="https://www.googletagmanager.com/gtm.js?id=GTM-TS7Q7WGG"></script>',
            'pixel_id',
        ]);

        $this->assertTrue(true);
    }

    /** @param array<int, mixed> $arguments */
    private function invokePrivate(object $target, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod($target, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($target, $arguments);
    }
}
