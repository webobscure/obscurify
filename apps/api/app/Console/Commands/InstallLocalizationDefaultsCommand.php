<?php

namespace App\Console\Commands;

use App\Domain\Localization\Application\EnsureDefaultLanguages;
use Illuminate\Console\Command;

/**
 * Idempotent, safe to re-run after a platform upgrade — matches
 * `search:install`/`notifications:install`'s own convention.
 */
class InstallLocalizationDefaultsCommand extends Command
{
    protected $signature = 'localization:install';

    protected $description = 'Seed the platform-wide Language/Locale catalog (ru default, en, de)';

    public function handle(EnsureDefaultLanguages $ensureDefaults): int
    {
        $languages = $ensureDefaults->handle();

        $this->info('Ensured '.count($languages).' language(s): '.implode(', ', array_map(fn ($l) => $l->code, $languages)));

        return self::SUCCESS;
    }
}
