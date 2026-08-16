<?php

namespace App\Domain\Localization\Support;

/**
 * Walks the real runtime translation sources — `lang/{locale}/*.php`
 * (backend) and each frontend app's `i18n/locales/{locale}.json`
 * (Admin/Storefront UI copy) — and flattens each into dot-path key =>
 * value pairs, the shape `translations:scan`/`translations:missing`/
 * `translations:unused` all build on. Never reads the Translation
 * index table itself; this is what POPULATES it.
 */
final class TranslationFileScanner
{
    /**
     * @param  list<string>  $locales
     * @return array<string, array<string, array<string, string>>> namespace => locale => (dot-path key => value)
     */
    public function scanBackend(string $langPath, array $locales): array
    {
        $result = [];

        foreach ($locales as $locale) {
            $dir = "{$langPath}/{$locale}";

            if (! is_dir($dir)) {
                continue;
            }

            foreach (glob("{$dir}/*.php") ?: [] as $file) {
                $namespace = basename($file, '.php');
                $data = require $file;

                if (! is_array($data)) {
                    continue;
                }

                $result[$namespace][$locale] = $this->flatten($data);
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $locales
     * @return array<string, array<string, string>> locale => (dot-path key => value)
     */
    public function scanFrontendApp(string $localesPath, array $locales): array
    {
        $result = [];

        foreach ($locales as $locale) {
            $file = "{$localesPath}/{$locale}.json";

            if (! is_file($file)) {
                continue;
            }

            $data = json_decode((string) file_get_contents($file), true);

            if (! is_array($data)) {
                continue;
            }

            $result[$locale] = $this->flatten($data);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value) && $value !== [] && array_is_list($value) === false) {
                $flat = [...$flat, ...$this->flatten($value, $path)];

                continue;
            }

            $flat[$path] = is_array($value) ? json_encode($value) : (string) $value;
        }

        return $flat;
    }
}
