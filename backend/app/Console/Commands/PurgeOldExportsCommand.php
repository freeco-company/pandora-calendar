<?php

namespace App\Console\Commands;

use App\Services\Export\UserDataExporter;
use Illuminate\Console\Command;

class PurgeOldExportsCommand extends Command
{
    protected $signature = 'exports:purge {--days=7}';

    protected $description = 'Delete user data export files older than N days (default 7).';

    public function handle(UserDataExporter $exporter): int
    {
        $days = (int) $this->option('days');
        $count = $exporter->purgeOldExports($days);
        $this->info("Purged {$count} old export(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
