<?php

namespace App\Domain\Localization\Application;

use App\Domain\Localization\Models\Language;
use App\Domain\Localization\Models\Locale;
use Illuminate\Support\Facades\Cache;

/**
 * Idempotent, safe to re-run — matches `search:install`/
 * `notifications:install`'s own convention, applied to the
 * platform-wide Language/Locale catalog instead of a per-store setup
 * (there is no store loop here: Language/Locale are shared reference
 * data, not tenant-scoped — see those models' own docblocks).
 *
 * Russian is the platform default (spec: "Russian must become the
 * default language for the entire platform"); German falls back to
 * English before reaching the platform default, since a Russian
 * fallback for a German-speaking user would be a worse guess than
 * English for a term neither has translated yet.
 */
final class EnsureDefaultLanguages
{
    /**
     * @return list<Language>
     */
    public function handle(): array
    {
        $ru = Language::query()->firstOrCreate(['code' => 'ru'], ['name' => 'Russian', 'native_name' => 'Русский', 'is_active' => true, 'sort_order' => 0]);
        $en = Language::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'native_name' => 'English', 'is_active' => true, 'sort_order' => 1]);
        $de = Language::query()->firstOrCreate(['code' => 'de'], ['name' => 'German', 'native_name' => 'Deutsch', 'is_active' => true, 'sort_order' => 2]);

        Locale::query()->firstOrCreate(['code' => 'ru'], ['language_code' => $ru->code, 'fallback_locale_code' => null, 'is_default' => true, 'is_active' => true]);
        Locale::query()->firstOrCreate(['code' => 'en'], ['language_code' => $en->code, 'fallback_locale_code' => null, 'is_default' => false, 'is_active' => true]);
        Locale::query()->firstOrCreate(['code' => 'de'], ['language_code' => $de->code, 'fallback_locale_code' => 'en', 'is_default' => false, 'is_active' => true]);

        Cache::forget('localization.active_locale_codes');
        Cache::forget('localization.default_locale_code');

        return [$ru, $en, $de];
    }
}
