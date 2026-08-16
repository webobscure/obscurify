<?php

namespace App\Console\Commands;

use App\Domain\Localization\Application\FindUnusedTranslationKeys;
use Illuminate\Console\Command;

class FindUnusedTranslationsCommand extends Command
{
    protected $signature = 'translations:unused';

    protected $description = 'List every Translation index key no longer present in any lang/*.php or frontend locale file';

    public function handle(FindUnusedTranslationKeys $find): int
    {
        $unused = $find->handle();

        if ($unused === []) {
            $this->info('No unused translation keys.');

            return self::SUCCESS;
        }

        $total = 0;

        foreach ($unused as $namespace => $keys) {
            $this->warn("[{$namespace}] ".count($keys).' unused');

            foreach ($keys as $key) {
                $this->line("  - {$key}");
            }

            $total += count($keys);
        }

        $this->error("{$total} unused translation key(s) total. Run `php artisan translations:scan` after removing them from source.");

        return self::FAILURE;
    }
}
