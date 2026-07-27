<?php

namespace App\Services;

use App\MediaLibrary\LandingMediaSectionMap;
use App\Models\Campaign;
use App\Models\Review;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class LandingMediaSectionOrganizerService
{
    public function __construct(
        private readonly PublicMediaStorageService $storageService
    ) {
    }

    public function organize(): array
    {
        $root = $this->storageService->canonicalRoot();

        File::ensureDirectoryExists($root, 0755, true);

        $mediaItems = Media::query()
            ->whereIn('collection_name', LandingMediaSectionMap::collections())
            ->with('model')
            ->orderBy('id')
            ->get();

        $plans = [];
        $ignoredMedia = 0;

        foreach ($mediaItems as $media) {
            $model = $this->resolveSupportedModel($media);

            if (! $model) {
                $ignoredMedia++;
                continue;
            }

            $slug = $this->resolveLandingSlug($media, $model);

            if ($slug === '') {
                $ignoredMedia++;
                continue;
            }

            $path = LandingMediaSectionMap::forCollection(
                (string) $media->collection_name
            );

            $sourceFile = $this->normalizeRelativePath(
                $media->getPathRelativeToRoot()
            );

            if (! File::isFile($this->absolutePath($root, $sourceFile))) {
                $legacyNumericFile = $this->normalizeRelativePath(
                    $media->id . '/' . $media->file_name
                );

                if (File::isFile($this->absolutePath($root, $legacyNumericFile))) {
                    $sourceFile = $legacyNumericFile;
                }
            }

            $sourceBase = $this->normalizeRelativePath(
                dirname($sourceFile)
            );
            $targetBase = $this->normalizeRelativePath(
                "landings/{$slug}/{$path['section']}"
            );
            $targetFile = $this->normalizeRelativePath(
                $targetBase . '/' . $media->file_name
            );

            $plans[] = [
                'media' => $media,
                'slug' => $slug,
                'section' => $path['section'],
                'media_type' => $path['media_type'],
                'source_base' => $sourceBase,
                'source_file' => $sourceFile,
                'target_base' => $targetBase,
                'target_file' => $targetFile,
                'migrated' => false,
            ];
        }

        $groups = [];

        foreach ($plans as $index => $plan) {
            $groupKey = $plan['source_base'] . '|' . $plan['target_base'];
            $groups[$groupKey]['source_base'] = $plan['source_base'];
            $groups[$groupKey]['target_base'] = $plan['target_base'];
            $groups[$groupKey]['indexes'][] = $index;
        }

        $copiedFiles = 0;

        foreach ($groups as $group) {
            if ($this->sameRelativePath(
                $group['source_base'],
                $group['target_base']
            )) {
                continue;
            }

            $sourceDirectory = $this->absolutePath(
                $root,
                $group['source_base']
            );
            $targetDirectory = $this->absolutePath(
                $root,
                $group['target_base']
            );

            if (! File::isDirectory($sourceDirectory)) {
                continue;
            }

            $copiedFiles += $this->copyDirectoryVerified(
                $sourceDirectory,
                $targetDirectory
            );
        }

        $organizedMedia = 0;
        $alreadyOrganized = 0;
        $missingFiles = 0;

        foreach ($plans as $index => $plan) {
            $media = $plan['media'];
            $targetFile = $this->absolutePath($root, $plan['target_file']);

            if (! File::isFile($targetFile)) {
                $sourceFile = $this->absolutePath($root, $plan['source_file']);

                if ($this->sameRelativePath(
                    $plan['source_file'],
                    $plan['target_file']
                ) && File::isFile($sourceFile)) {
                    $targetFile = $sourceFile;
                }
            }

            if (! File::isFile($targetFile)) {
                $missingFiles++;
                continue;
            }

            $wasAlreadyOrganized = $this->sameRelativePath(
                $plan['source_base'],
                $plan['target_base']
            ) && $media->getCustomProperty('landing_section_folder') === $plan['section'];

            $media
                ->setCustomProperty('landing_named_path', true)
                ->setCustomProperty('landing_path_slug', $plan['slug'])
                ->setCustomProperty('landing_section_folder', $plan['section'])
                ->setCustomProperty('landing_media_folder', $plan['media_type'])
                ->save();

            $plans[$index]['migrated'] = true;

            if ($wasAlreadyOrganized) {
                $alreadyOrganized++;
            } else {
                $organizedMedia++;
            }
        }

        $removedFolders = 0;

        foreach ($groups as $group) {
            if ($this->sameRelativePath(
                $group['source_base'],
                $group['target_base']
            )) {
                continue;
            }

            $allMigrated = collect($group['indexes'])
                ->every(fn (int $index): bool => $plans[$index]['migrated']);

            if (! $allMigrated || $group['source_base'] === '') {
                continue;
            }

            $sourceDirectory = $this->absolutePath(
                $root,
                $group['source_base']
            );

            if (
                File::isDirectory($sourceDirectory)
                && File::deleteDirectory($sourceDirectory)
            ) {
                $removedFolders++;
            }
        }

        return [
            'organized_media' => $organizedMedia,
            'already_organized' => $alreadyOrganized,
            'copied_files' => $copiedFiles,
            'removed_folders' => $removedFolders,
            'missing_files' => $missingFiles,
            'ignored_media' => $ignoredMedia,
        ];
    }

    private function resolveSupportedModel(Media $media): ?Model
    {
        $model = $media->model;

        if ($model instanceof Campaign || $model instanceof Review) {
            return $model;
        }

        if (is_a((string) $media->model_type, Campaign::class, true)) {
            return Campaign::withTrashed()->find($media->model_id);
        }

        if (is_a((string) $media->model_type, Review::class, true)) {
            return Review::withTrashed()->find($media->model_id);
        }

        return null;
    }

    private function resolveLandingSlug(Media $media, Model $model): string
    {
        $storedSlug = Str::slug((string) $media->getCustomProperty(
            'landing_path_slug',
            ''
        ));

        if ($storedSlug !== '') {
            return $storedSlug;
        }

        if ($model instanceof Campaign) {
            return Str::slug((string) ($model->slug ?: $model->title))
                ?: 'landing-' . (int) $model->getKey();
        }

        if ($model instanceof Review) {
            $campaign = Campaign::withTrashed()->find($model->campaign_id);

            if ($campaign) {
                return Str::slug((string) ($campaign->slug ?: $campaign->title))
                    ?: 'landing-' . (int) $campaign->getKey();
            }
        }

        return '';
    }

    private function copyDirectoryVerified(
        string $source,
        string $target
    ): int {
        File::ensureDirectoryExists($target, 0755, true);

        $copiedFiles = 0;
        $files = File::allFiles($source);

        foreach ($files as $file) {
            $sourceFile = $file->getPathname();
            $relativeFile = ltrim(
                substr($sourceFile, strlen($source)),
                DIRECTORY_SEPARATOR
            );
            $targetFile = $target . DIRECTORY_SEPARATOR . $relativeFile;

            File::ensureDirectoryExists(dirname($targetFile), 0755, true);

            if (File::exists($targetFile)) {
                if (filesize($sourceFile) !== filesize($targetFile)) {
                    throw new RuntimeException(
                        'Landing media filename conflict: ' . $targetFile
                    );
                }

                continue;
            }

            if (! @copy($sourceFile, $targetFile)) {
                throw new RuntimeException(
                    'Landing media could not be moved: ' . $sourceFile
                );
            }

            if (
                ! File::exists($targetFile)
                || filesize($sourceFile) !== filesize($targetFile)
            ) {
                throw new RuntimeException(
                    'Landing media move verification failed: ' . $targetFile
                );
            }

            $copiedFiles++;
        }

        return $copiedFiles;
    }

    private function absolutePath(string $root, string $relativePath): string
    {
        $relativePath = str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            $this->normalizeRelativePath($relativePath)
        );

        return rtrim($root, '/\\')
            . ($relativePath !== '' ? DIRECTORY_SEPARATOR . $relativePath : '');
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '.' || $path === '/') {
            return '';
        }

        return trim($path, '/');
    }

    private function sameRelativePath(string $left, string $right): bool
    {
        $left = $this->normalizeRelativePath($left);
        $right = $this->normalizeRelativePath($right);

        if (PHP_OS_FAMILY === 'Windows') {
            return mb_strtolower($left, 'UTF-8')
                === mb_strtolower($right, 'UTF-8');
        }

        return $left === $right;
    }
}
