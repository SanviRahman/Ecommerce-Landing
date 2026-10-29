<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\HomepageMediaSectionOrganizerService;
use Illuminate\Console\Command;
use Throwable;

final class OrganizeHomepageMedia extends Command
{
    protected $signature = 'media:organize-homepage {--apply : Copy verified files and switch path metadata (local only)}';

    protected $description = 'Review and optionally organize existing dedicated Homepage media by named section, without removing old files.';

    public function handle(HomepageMediaSectionOrganizerService $organizer): int
    {
        if ($this->option('apply') && ! app()->environment('local', 'testing')) {
            $this->error('Homepage media reorganization is permitted only in a reviewed local/test environment.');
            return self::FAILURE;
        }

        try {
            $result = $organizer->organize((bool) $this->option('apply'));
            foreach ($result as $key => $value) {
                $this->components->twoColumnDetail(ucwords($key), (string) $value);
            }
            if (! $this->option('apply')) {
                $this->info('Dry run only. Back up public/storage and database before using --apply.');
            } else {
                $this->info('Dedicated Homepage media organized; old numeric files retained for rollback.');
            }
            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Homepage media reorganization stopped: ' . $exception->getMessage());
            return self::FAILURE;
        }
    }
}
