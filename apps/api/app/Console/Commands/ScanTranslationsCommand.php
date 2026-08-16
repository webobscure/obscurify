<?php

namespace App\Console\Commands;

use App\Domain\Localization\Application\ScanTranslations;
use Illuminate\Console\Command;

class ScanTranslationsCommand extends Command
{
    protected $signature = 'translations:scan';

    protected $description = 'Populate the Translation index from lang/*.php and each frontend app\'s i18n/locales/*.json';

    public function handle(ScanTranslations $scan): int
    {
        $result = $scan->handle();

        $this->info("Scanned {$result['namespaces']} namespace(s), {$result['keys']} key(s), {$result['translations']} translation(s).");

        return self::SUCCESS;
    }
}
