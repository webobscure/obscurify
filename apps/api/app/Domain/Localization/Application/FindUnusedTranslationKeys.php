<?php

namespace App\Domain\Localization\Application;

use App\Domain\Localization\Models\Locale;
use App\Domain\Localization\Models\TranslationKey;
use App\Domain\Localization\Support\TranslationFileScanner;

/**
 * Spec section 16: "Detect unused keys." A TranslationKey row is
 * unused when its (namespace, key) no longer appears in ANY currently
 * scanned file — i.e. it was removed from `lang/*.php`/the frontend
 * locale JSON since the last `translations:scan`, leaving a stale
 * index row behind. Never deletes anything itself — reporting only, an
 * operator decides whether to prune (see `translations:scan --prune`).
 */
final class FindUnusedTranslationKeys
{
    public function __construct(private readonly TranslationFileScanner $scanner) {}

    /**
     * @return array<string, list<string>> namespace code => list<orphaned key>
     */
    public function handle(): array
    {
        $locales = Locale::query()->pluck('code')->all();
        $live = [];

        foreach ($this->scanner->scanBackend(base_path('lang'), $locales) as $namespaceCode => $byLocale) {
            $live[$namespaceCode] = $this->unionKeys($byLocale);
        }

        foreach ((new ScanTranslations($this->scanner))->frontendAppPaths() as $namespaceCode => $localesPath) {
            $byLocale = $this->scanner->scanFrontendApp($localesPath, $locales);
            $live[$namespaceCode] = $this->unionKeys($byLocale);
        }

        $unused = [];

        TranslationKey::query()->with('namespace')->chunkById(200, function ($chunk) use (&$unused, $live) {
            foreach ($chunk as $translationKey) {
                $namespaceCode = $translationKey->namespace->code;

                if (! in_array($translationKey->key, $live[$namespaceCode] ?? [], true)) {
                    $unused[$namespaceCode][] = $translationKey->key;
                }
            }
        });

        return $unused;
    }

    /**
     * @param  array<string, array<string, string>>  $byLocale
     * @return list<string>
     */
    private function unionKeys(array $byLocale): array
    {
        $union = [];

        foreach ($byLocale as $keyValues) {
            $union = [...$union, ...array_keys($keyValues)];
        }

        return array_values(array_unique($union));
    }
}
