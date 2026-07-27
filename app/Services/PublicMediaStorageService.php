<?php

namespace App\Services;

use FilesystemIterator;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;

class PublicMediaStorageService
{
    public function canonicalRoot(): string
    {
        return $this->configuredRoot();
    }

    public function configuredRoot(): string
    {
        $root = trim((string) config('filesystems.disks.public.root', ''));

        if ($root === '') {
            throw new RuntimeException('The public filesystem root is not configured.');
        }

        return $this->normalizePath($root);
    }

    public function legacyRoot(): string
    {
        return $this->normalizePath(storage_path('app/public'));
    }

    public function legacyRoots(): array
    {
        $roots = [
            $this->legacyRoot(),
            $this->normalizePath(public_path('storage')),
        ];

        $unique = [];

        foreach ($roots as $root) {
            $key = PHP_OS_FAMILY === 'Windows'
                ? mb_strtolower($root, 'UTF-8')
                : $root;

            $unique[$key] = $root;
        }

        return array_values($unique);
    }

    public function prepare(bool $removeLegacyFiles = true): array
    {
        $target = $this->configuredRoot();

        File::ensureDirectoryExists(dirname($target), 0755, true);

        if ($this->isLinkedDirectory($target)) {
            $this->removeDirectoryLink($target);
        }

        File::ensureDirectoryExists($target, 0755, true);

        $copiedFiles = 0;
        $removedFiles = 0;
        $migratedRoots = [];

        foreach ($this->legacyRoots() as $legacy) {
            if ($this->samePath($target, $legacy)) {
                continue;
            }

            if ($this->isLinkedDirectory($legacy)) {
                $linkedTarget = realpath($legacy);

                if (
                    $linkedTarget
                    && ! $this->samePath($linkedTarget, $target)
                    && File::isDirectory($linkedTarget)
                ) {
                    $copiedFiles += $this->copyDirectoryVerified(
                        $linkedTarget,
                        $target
                    );
                }

                if ($removeLegacyFiles) {
                    $this->removeDirectoryLink($legacy);
                }

                $migratedRoots[] = $legacy;
                continue;
            }

            if (! File::isDirectory($legacy)) {
                continue;
            }

            if ($this->sameRealPath($target, $legacy)) {
                continue;
            }

            $copiedFiles += $this->copyDirectoryVerified($legacy, $target);

            if ($removeLegacyFiles) {
                $removedFiles += $this->clearDirectoryContents($legacy);
            }

            $migratedRoots[] = $legacy;
        }

        return [
            'target'          => $target,
            'legacy'          => $this->legacyRoot(),
            'legacy_roots'    => $this->legacyRoots(),
            'migrated_roots'  => $migratedRoots,
            'copied_files'    => $copiedFiles,
            'removed_files'   => $removedFiles,
            'writable'        => File::isDirectory($target) && is_writable($target),
        ];
    }

    public function isDirectDirectory(string $path): bool
    {
        return File::isDirectory($path) && ! $this->isLinkedDirectory($path);
    }

    public function samePath(string $left, string $right): bool
    {
        $left = $this->normalizePath($left);
        $right = $this->normalizePath($right);

        if (PHP_OS_FAMILY === 'Windows') {
            return mb_strtolower($left, 'UTF-8') === mb_strtolower($right, 'UTF-8');
        }

        return $left === $right;
    }

    public function sameRealPath(string $left, string $right): bool
    {
        $leftReal = realpath($left);
        $rightReal = realpath($right);

        if (! $leftReal || ! $rightReal) {
            return false;
        }

        return $this->samePath($leftReal, $rightReal);
    }

    private function copyDirectoryVerified(string $source, string $target): int
    {
        $source = $this->normalizePath(realpath($source) ?: $source);
        $target = $this->normalizePath($target);
        $copied = 0;

        if (! File::isDirectory($source)) {
            return 0;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $source,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isLink()) {
                continue;
            }

            $sourcePath = $item->getPathname();
            $relativePath = ltrim(
                substr($sourcePath, strlen($source)),
                DIRECTORY_SEPARATOR
            );
            $targetPath = $target . DIRECTORY_SEPARATOR . $relativePath;

            if ($item->isDir()) {
                File::ensureDirectoryExists($targetPath, 0755, true);
                continue;
            }

            File::ensureDirectoryExists(dirname($targetPath), 0755, true);

            $needsCopy = ! File::exists($targetPath)
                || filesize($sourcePath) !== filesize($targetPath);

            if ($needsCopy && ! @copy($sourcePath, $targetPath)) {
                throw new RuntimeException(
                    'Media file could not be copied: ' . $relativePath
                );
            }

            if (
                ! File::exists($targetPath)
                || filesize($sourcePath) !== filesize($targetPath)
            ) {
                throw new RuntimeException(
                    'Media copy verification failed: ' . $relativePath
                );
            }

            if ($needsCopy) {
                $copied++;
            }
        }

        return $copied;
    }

    private function clearDirectoryContents(string $directory): int
    {
        $directory = $this->normalizePath($directory);
        $removed = 0;

        if (! File::isDirectory($directory) || $this->isLinkedDirectory($directory)) {
            return $removed;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();

            if ($item->isLink()) {
                @unlink($path);
                continue;
            }

            if ($item->isFile()) {
                if (! @unlink($path)) {
                    throw new RuntimeException(
                        'Legacy media file could not be removed: ' . $path
                    );
                }

                $removed++;
                continue;
            }

            @rmdir($path);
        }

        return $removed;
    }

    private function isLinkedDirectory(string $path): bool
    {
        if (is_link($path)) {
            return true;
        }

        if (! File::exists($path)) {
            return false;
        }

        $realPath = realpath($path);

        return $realPath !== false && ! $this->samePath($realPath, $path);
    }

    private function removeDirectoryLink(string $path): void
    {
        clearstatcache(true, $path);

        if (PHP_OS_FAMILY === 'Windows') {
            if (@rmdir($path)) {
                return;
            }

            $safePath = str_replace('"', '""', $path);

            $process = new Process([
                'cmd.exe',
                '/D',
                '/S',
                '/C',
                'rmdir "' . $safePath . '"',
            ]);

            $process->setTimeout(30);
            $process->run();

            clearstatcache(true, $path);

            if ($process->isSuccessful() && ! File::exists($path) && ! is_link($path)) {
                return;
            }

            $message = trim($process->getErrorOutput() ?: $process->getOutput());

            throw new RuntimeException(
                'Existing public storage junction could not be removed.'
                . ($message !== '' ? ' ' . $message : '')
            );
        }

        if (is_link($path) && @unlink($path)) {
            return;
        }

        if (@rmdir($path)) {
            return;
        }

        throw new RuntimeException(
            'Existing public storage symbolic link could not be removed.'
        );
    }

    private function normalizePath(string $path): string
    {
        return rtrim(
            str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path),
            DIRECTORY_SEPARATOR
        );
    }
}
