<?php

namespace App\Console\Commands;

use App\Domain\Localization\Application\FindMissingTranslations;
use Illuminate\Console\Command;

class FindMissingTranslationsCommand extends Command
{
    protected $signature = 'translations:missing';

    protected $description = 'List every namespace/locale/key combination with no translation on disk';

    public function handle(FindMissingTranslations $find): int
    {
        $missing = $find->handle();

        if ($missing === []) {
            $this->info('No missing translations.');

            return self::SUCCESS;
        }

        $total = 0;

        foreach ($missing as $namespace => $byLocale) {
            foreach ($byLocale as $locale => $keys) {
                $this->warn("[{$namespace}] {$locale}: ".count($keys).' missing');

                foreach ($keys as $key) {
                    $this->line("  - {$key}");
                }

                $total += count($keys);
            }
        }

        $this->error("{$total} missing translation(s) total.");

        return self::FAILURE;
    }
}
