<?php

namespace App\Domain\Localization\Application;

use App\Domain\Localization\Enums\TranslationSource;
use App\Domain\Localization\Models\Locale;
use App\Domain\Localization\Models\Translation;
use App\Domain\Localization\Models\TranslationKey;
use App\Domain\Localization\Models\TranslationNamespace;
use App\Domain\Localization\Support\TranslationFileScanner;

/**
 * Populates the Translation index (spec section 16's "detect missing
 * translations, detect unused keys" tooling reads this index rather
 * than re-parsing files itself) by walking the real runtime sources —
 * `lang/{locale}/*.php` and each frontend app's `i18n/locales/{locale}.json`
 * — never the reverse. Safe to re-run at any time (`php artisan
 * translations:scan`); every write is an upsert.
 */
final class ScanTranslations
{
    public function __construct(private readonly TranslationFileScanner $scanner) {}

    /**
     * @return array{namespaces: int, keys: int, translations: int}
     */
    public function handle(): array
    {
        $locales = Locale::query()->pluck('code')->all();
        $namespaceCount = 0;
        $keyCount = 0;
        $translationCount = 0;

        $backend = $this->scanner->scanBackend(base_path('lang'), $locales);

        foreach ($backend as $namespaceCode => $byLocale) {
            [$ns, $keys, $translations] = $this->upsertNamespace($namespaceCode, $byLocale);
            $namespaceCount += $ns;
            $keyCount += $keys;
            $translationCount += $translations;
        }

        foreach ($this->frontendAppPaths() as $namespaceCode => $localesPath) {
            $byLocale = $this->scanner->scanFrontendApp($localesPath, $locales);
            [$ns, $keys, $translations] = $this->upsertNamespace($namespaceCode, $byLocale);
            $namespaceCount += $ns;
            $keyCount += $keys;
            $translationCount += $translations;
        }

        return ['namespaces' => $namespaceCount, 'keys' => $keyCount, 'translations' => $translationCount];
    }

    /**
     * @return array<string, string> namespace code => absolute locales directory
     */
    public function frontendAppPaths(): array
    {
        return [
            'admin' => base_path('../admin/i18n/locales'),
            'storefront' => base_path('../storefront/i18n/locales'),
        ];
    }

    /**
     * @param  array<string, array<string, string>>  $byLocale  locale => (dot-path key => value)
     * @return array{0: int, 1: int, 2: int} namespaces touched, keys upserted, translations upserted
     */
    private function upsertNamespace(string $namespaceCode, array $byLocale): array
    {
        if ($byLocale === []) {
            return [0, 0, 0];
        }

        $namespace = TranslationNamespace::query()->firstOrCreate(['code' => $namespaceCode]);

        $allKeys = [];

        foreach ($byLocale as $keyValues) {
            foreach (array_keys($keyValues) as $key) {
                $allKeys[$key] = true;
            }
        }

        $keyCount = 0;
        $translationCount = 0;

        foreach (array_keys($allKeys) as $key) {
            $translationKey = TranslationKey::query()->firstOrCreate([
                'namespace_id' => $namespace->id,
                'key' => $key,
            ]);
            $keyCount++;

            foreach ($byLocale as $localeCode => $keyValues) {
                if (! array_key_exists($key, $keyValues)) {
                    continue;
                }

                Translation::query()->updateOrCreate(
                    ['translation_key_id' => $translationKey->id, 'locale_code' => $localeCode],
                    ['value' => $keyValues[$key], 'source' => TranslationSource::Scan->value],
                );
                $translationCount++;
            }
        }

        return [1, $keyCount, $translationCount];
    }
}
