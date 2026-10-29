<?php

declare(strict_types=1);

namespace App\Services;

use App\MediaLibrary\HomepageMediaPathGenerator;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Non-destructive, opt-in migration of *dedicated* Homepage media only. */
final class HomepageMediaSectionOrganizerService
{
    /** @return array{planned:int,organized:int,missing:int,skipped:int} */
    public function organize(bool $apply = false): array
    {
        $planned = $organized = $missing = $skipped = 0;
        $root = (string) config('filesystems.disks.public.root');
        if ($root === '' || ! File::isDirectory($root)) {
            throw new RuntimeException('Configured public media disk root is missing. No files were changed.');
        }

        $mediaItems = Media::query()->where('model_type', (new SiteSetting)->getMorphClass())
            ->where('disk', 'public')
            ->whereIn('collection_name', array_keys(HomepageMediaPathGenerator::FOLDERS))
            ->orderBy('id')->get();

        foreach ($mediaItems as $media) {
            $currentPath = $media->getPathRelativeToRoot();
            $sourceBase = trim(str_replace('\\', '/', dirname($currentPath)), './');
            $targetBase = trim((string) config('media-library.prefix', ''), '/');
            $targetBase .= ($targetBase !== '' ? '/' : '') . 'homepage/'
                . HomepageMediaPathGenerator::FOLDERS[$media->collection_name]
                . '/media-' . $media->getKey();

            if ($sourceBase === '' || $sourceBase === '.' || str_contains($sourceBase, '..')) {
                $missing++;
                continue;
            }

            if ($media->getCustomProperty('homepage_named_path') === true) {
                $skipped++;
                continue;
            }

            // Existing numeric directory can include conversions/responsive images;
            // verify every file before switching Spatie's path generator.
            $source = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sourceBase);
            $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetBase);
            if (! File::isDirectory($source) || ! File::isFile($source . DIRECTORY_SEPARATOR . $media->file_name)) {
                $missing++;
                continue;
            }

            $planned++;
            if (! $apply) {
                continue;
            }

            $this->copyDirectoryVerified($source, $target);
            if (! File::isFile($target . DIRECTORY_SEPARATOR . $media->file_name)) {
                throw new RuntimeException('Homepage media destination missing after copy verification.');
            }
            $media->setCustomProperty('homepage_named_path', true)
                ->setCustomProperty('homepage_section_folder', HomepageMediaPathGenerator::FOLDERS[$media->collection_name]);
            $media->save();
            $organized++;
            // Intentional: old numeric files stay on disk for safe rollback and
            // hardcoded legacy URLs. Never delete files during this operation.
        }

        return compact('planned', 'organized', 'missing', 'skipped');
    }

    private function copyDirectoryVerified(string $source, string $target): void
    {
        File::ensureDirectoryExists($target, 0755, true);
        foreach (File::allFiles($source) as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('Symlink inside public media folder is not supported.');
            }
            $relative = substr($file->getPathname(), strlen(rtrim($source, '/\\')) + 1);
            $destination = rtrim($target, '/\\') . DIRECTORY_SEPARATOR . $relative;
            File::ensureDirectoryExists(dirname($destination), 0755, true);
            if (! File::isFile($destination) && ! File::copy($file->getPathname(), $destination)) {
                throw new RuntimeException('Unable to copy Homepage media without data loss.');
            }
            if (! File::isFile($destination)
                || hash_file('sha256', $file->getPathname()) !== hash_file('sha256', $destination)) {
                throw new RuntimeException('Homepage media checksum conflict; original files were not changed.');
            }
        }
    }
}
