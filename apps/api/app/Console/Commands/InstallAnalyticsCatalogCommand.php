<?php

namespace App\Console\Commands;

use App\Domain\Analytics\Application\RegisterBuiltInAnalyticsCatalog;
use Illuminate\Console\Command;

class InstallAnalyticsCatalogCommand extends Command
{
    protected $signature = 'analytics:install';

    protected $description = 'Register the built-in metric catalog';

    public function handle(RegisterBuiltInAnalyticsCatalog $register): int
    {
        $register->handle();

        $this->info('Analytics catalog installed.');

        return self::SUCCESS;
    }
}
