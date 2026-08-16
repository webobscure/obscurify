<?php

namespace App\Domain\Localization\Application;

use App\Domain\Localization\Models\Locale;
use App\Domain\Localization\Support\TranslationFileScanner;

/**
 * Spec section 16: "Detect missing translations." A key is missing for
 * a locale when it exists in at least one OTHER active locale's file
 * for that namespace but not in this one's — i.e. the union of keys
 * across every locale is "the complete set this namespace should
 * have," and any locale short of that union has a gap. Reads the live
 * files directly (via the same TranslationFileScanner ScanTranslations
 * uses to populate the DB index) rather than the Translation table,
 * so this is accurate even if `translations:scan` hasn't been run
 * since the last edit.
 */
final class FindMissingTranslations
{
    public function __construct(private readonly TranslationFileScanner $scanner) {}

    /**
     * @return array<string, array<string, list<string>>> namespace => locale => list<missing dot-path key>
     */
    public function handle(): array
    {
        $locales = Locale::query()->pluck('code')->all();
        $missing = [];

        $sources = $this->scanner->scanBackend(base_path('lang'), $locales);

        foreach ($sources as $namespaceCode => $byLocale) {
            $this->collectMissing($missing, $namespaceCode, $byLocale, $locales);
        }

        foreach ((new ScanTranslations($this->scanner))->frontendAppPaths() as $namespaceCode => $localesPath) {
            $byLocale = $this->scanner->scanFrontendApp($localesPath, $locales);

            if ($byLocale === []) {
                continue;
            }

            $this->collectMissing($missing, $namespaceCode, $byLocale, $locales);
        }

        return $missing;
    }

    /**
     * @param  array<string, array<string, list<string>>>  $missing
     * @param  array<string, array<string, string>>  $byLocale
     * @param  list<string>  $locales
     */
    private function collectMissing(array &$missing, string $namespaceCode, array $byLocale, array $locales): void
    {
        $union = [];

        foreach ($byLocale as $keyValues) {
            $union = [...$union, ...array_keys($keyValues)];
        }

        $union = array_unique($union);

        foreach ($locales as $locale) {
            $present = array_keys($byLocale[$locale] ?? []);
            $gap = array_values(array_diff($union, $present));

            if ($gap !== []) {
                $missing[$namespaceCode][$locale] = $gap;
            }
        }
    }
}
