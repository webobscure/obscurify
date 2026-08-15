<?php

namespace App\Console\Commands;

use App\Domain\Automation\Application\RegisterBuiltInAutomationCatalog;
use Illuminate\Console\Command;

/**
 * Seeds (or re-seeds, idempotently) the built-in workflow variable and
 * template catalogs — run once per environment, or again after a
 * platform upgrade adds new built-ins. Also called from DatabaseSeeder
 * for fresh installs.
 */
class InstallAutomationCatalogCommand extends Command
{
    protected $signature = 'automation:install';

    protected $description = 'Register built-in automation variables and starter templates';

    public function handle(RegisterBuiltInAutomationCatalog $register): int
    {
        $register->handle();

        $this->info('Automation catalog installed.');

        return self::SUCCESS;
    }
}
