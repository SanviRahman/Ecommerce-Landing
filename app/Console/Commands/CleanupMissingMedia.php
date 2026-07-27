<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class CleanupMissingMedia extends Command
{
    protected $signature = 'media:cleanup-missing
        {--model=all : all, product, campaign or review}
        {--apply : Delete missing physical-file records from the media table}';

    protected $description = 'Find and optionally delete Product/Landing media records whose physical files are missing.';

    public function handle(): int
    {
        $modelOption = strtolower(trim((string) $this->option('model')));

        if (! in_array($modelOption, ['all', 'product', 'campaign', 'review'], true)) {
            $this->error('--model must be all, product, campaign or review.');

            return self::FAILURE;
        }

        $query = Media::query();
        $this->applyModelFilter($query, $modelOption);

        $missing = $query
            ->orderBy('id')
            ->get()
            ->filter(function (Media $media): bool {
                try {
                    return ! Storage::disk($media->disk)->exists(
                        $media->getPathRelativeToRoot()
                    );
                } catch (Throwable) {
                    return true;
                }
            })
            ->values();

        if ($missing->isEmpty()) {
            $this->info('No missing Product/Landing media files were found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Media ID', 'Model', 'Model ID', 'Collection', 'Relative path'],
            $missing->map(fn (Media $media): array => [
                $media->id,
                class_basename((string) $media->model_type),
                $media->model_id,
                $media->collection_name,
                $media->getPathRelativeToRoot(),
            ])->all()
        );

        if (! $this->option('apply')) {
            $this->warn('Dry run only. Add --apply to delete these stale database rows.');

            return self::SUCCESS;
        }

        $deleted = 0;

        foreach ($missing as $media) {
            try {
                $media->delete();
                $deleted++;
            } catch (Throwable $exception) {
                $this->warn('Media ID ' . $media->id . ' could not be deleted: ' . $exception->getMessage());
            }
        }

        $this->info($deleted . ' missing media records deleted successfully.');

        return self::SUCCESS;
    }

    private function applyModelFilter(Builder $query, string $modelOption): void
    {
        if ($modelOption === 'product') {
            $query->where('model_type', Product::class);

            return;
        }

        if ($modelOption === 'campaign') {
            $query->whereIn('model_type', [Campaign::class, Review::class]);

            return;
        }

        if ($modelOption === 'review') {
            $query->where('model_type', Review::class);

            return;
        }

        $query->whereIn('model_type', [
            Product::class,
            Campaign::class,
            Review::class,
        ]);
    }
}
