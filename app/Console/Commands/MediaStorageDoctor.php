<?php

namespace App\Console\Commands;

use App\MediaLibrary\LandingMediaPathGenerator;
use App\MediaLibrary\ProductMediaPathGenerator;
use App\Models\Campaign;
use App\Models\Product;
use App\Models\Review;
use App\Services\PublicMediaStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class MediaStorageDoctor extends Command
{
    protected $signature = 'media:storage-doctor
        {--limit=20 : Number of latest Product/Landing media rows to inspect}';

    protected $description = 'Diagnose the direct public media root and Product/Landing media paths.';

    public function handle(PublicMediaStorageService $storageService): int
    {
        $diskRoot = (string) config('filesystems.disks.public.root');
        $diskUrl = (string) config('filesystems.disks.public.url');
        $canonicalRoot = $storageService->canonicalRoot();
        $legacyRoot = $storageService->legacyRoot();

        $productGenerator = config(
            'media-library.custom_path_generators.' . Product::class
        );
        $campaignGenerator = config(
            'media-library.custom_path_generators.' . Campaign::class
        );
        $reviewGenerator = config(
            'media-library.custom_path_generators.' . Review::class
        );

        $this->components->twoColumnDetail('APP_URL', (string) config('app.url'));
        $this->components->twoColumnDetail('Public disk root', $diskRoot);
        $this->components->twoColumnDetail('Canonical web storage', $canonicalRoot);
        $this->components->twoColumnDetail('Public disk URL', $diskUrl);
        $this->components->twoColumnDetail('Legacy storage root', $legacyRoot);
        $this->components->twoColumnDetail(
            'Direct physical folder',
            $storageService->isDirectDirectory($diskRoot) ? 'Yes' : 'No'
        );
        $this->components->twoColumnDetail('Disk root writable', is_dir($diskRoot) && is_writable($diskRoot) ? 'Yes' : 'No');
        $this->components->twoColumnDetail('Product path generator', (string) ($productGenerator ?: 'Default'));
        $this->components->twoColumnDetail('Campaign path generator', (string) ($campaignGenerator ?: 'Default'));
        $this->components->twoColumnDetail('Review path generator', (string) ($reviewGenerator ?: 'Default'));

        $configIsCorrect = $storageService->samePath($diskRoot, $canonicalRoot);
        $generatorsAreCorrect = $productGenerator === ProductMediaPathGenerator::class
            && $campaignGenerator === LandingMediaPathGenerator::class
            && $reviewGenerator === LandingMediaPathGenerator::class;
        $rootIsReady = $storageService->isDirectDirectory($diskRoot)
            && is_writable($diskRoot);

        if ($configIsCorrect && $generatorsAreCorrect && $rootIsReady) {
            $this->info('Single public media root is ready.');
        } else {
            $this->error('Media storage configuration is incomplete.');
            $this->line('Run: php artisan storage:repair-public --force');
        }

        try {
            $limit = max(1, min(200, (int) $this->option('limit')));

            $mediaRows = Media::query()
                ->whereIn('model_type', [
                    Product::class,
                    Campaign::class,
                    Review::class,
                ])
                ->latest('id')
                ->limit($limit)
                ->get();

            if ($mediaRows->isEmpty()) {
                $this->warn('No Product or Landing media rows were found.');

                return self::SUCCESS;
            }

            $rows = $mediaRows->map(function (Media $media): array {
                $relativePath = $media->getPathRelativeToRoot();
                $exists = false;

                try {
                    $exists = Storage::disk($media->disk)->exists($relativePath);
                } catch (Throwable) {
                    $exists = false;
                }

                return [
                    $media->id,
                    class_basename((string) $media->model_type),
                    $media->model_id,
                    $media->collection_name,
                    $relativePath,
                    $exists ? 'YES' : 'NO',
                    $media->getUrl(),
                ];
            })->all();

            $this->table(
                ['Media ID', 'Model', 'Model ID', 'Collection', 'Relative path', 'Exists', 'URL'],
                $rows
            );
        } catch (Throwable $exception) {
            $this->warn('Database media inspection skipped: ' . $exception->getMessage());
        }

        $this->newLine();
        $this->line('New Product media path: products/{product-slug}/{folder}/{file_name}');
        $this->line('New Landing media path: landings/{landing-slug}/{section-name}/{file_name}');
        $this->line('Public URL base: /storage');

        return self::SUCCESS;
    }
}
