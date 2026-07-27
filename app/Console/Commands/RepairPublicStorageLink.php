<?php

namespace App\Console\Commands;

use App\Services\LandingMediaSectionOrganizerService;
use App\Services\PublicMediaStorageService;
use App\Services\ProductMediaSectionOrganizerService;
use Illuminate\Console\Command;
use Throwable;

class RepairPublicStorageLink extends Command
{
    protected $signature = 'storage:repair-public
                            {--force : Kept for backward compatibility}';

    protected $description = 'Prepare one direct public media folder and organize Product/Landing media by section.';

    public function handle(
        PublicMediaStorageService $storageService,
        ProductMediaSectionOrganizerService $productOrganizer,
        LandingMediaSectionOrganizerService $landingOrganizer
    ): int {
        try {
            $result = $storageService->prepare(true);
            $productResult = $productOrganizer->organize();
            $landingResult = $landingOrganizer->organize();

            $this->components->twoColumnDetail('Media root', $result['target']);
            $this->components->twoColumnDetail('Legacy root', $result['legacy']);
            $this->components->twoColumnDetail('Migrated files', (string) $result['copied_files']);
            $this->components->twoColumnDetail('Removed legacy files', (string) $result['removed_files']);
            $this->components->twoColumnDetail('Product media organized', (string) $productResult['organized_media']);
            $this->components->twoColumnDetail('Product files moved', (string) $productResult['copied_files']);
            $this->components->twoColumnDetail('Old Product folders removed', (string) $productResult['removed_folders']);
            $this->components->twoColumnDetail('Missing Product files', (string) $productResult['missing_files']);
            $this->components->twoColumnDetail('Landing media organized', (string) $landingResult['organized_media']);
            $this->components->twoColumnDetail('Landing files moved', (string) $landingResult['copied_files']);
            $this->components->twoColumnDetail('Old Landing folders removed', (string) $landingResult['removed_folders']);
            $this->components->twoColumnDetail('Missing Landing files', (string) $landingResult['missing_files']);
            $this->components->twoColumnDetail('Writable', $result['writable'] ? 'Yes' : 'No');

            if (! $result['writable']) {
                $this->components->error('The direct public media folder is not writable.');

                return self::FAILURE;
            }

            if (
                $productResult['missing_files'] > 0
                || $landingResult['missing_files'] > 0
            ) {
                $this->components->warn(
                    'Some Product/Landing media database rows do not have a physical file. Existing records were not deleted.'
                );
            }

            $this->components->info(
                'Single public media storage is ready. Product and Landing media are organized by section.'
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
