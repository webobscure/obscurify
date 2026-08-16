<?php

use App\Domain\Localization\Application\EnsureDefaultLanguages;
use App\Domain\Localization\Application\FindMissingTranslations;
use App\Domain\Localization\Application\FindUnusedTranslationKeys;
use App\Domain\Localization\Application\ScanTranslations;
use App\Domain\Localization\Models\Translation;
use App\Domain\Localization\Models\TranslationKey;
use App\Domain\Localization\Models\TranslationNamespace;

/**
 * Spec section 16: "Create translation helper commands. Detect missing
 * translations. Detect unused keys." — exercises the real
 * `lang/{locale}/*.php` files on disk (this suite doesn't fabricate a
 * temp lang directory; it scans the actual ones this milestone ships),
 * so these tests also serve as a live check that every backend
 * namespace stays in sync across ru/en/de.
 */
beforeEach(function () {
    app(EnsureDefaultLanguages::class)->handle();
});

it('scans the real lang files and populates the Translation index', function () {
    $result = app(ScanTranslations::class)->handle();

    expect($result['namespaces'])->toBeGreaterThan(0)
        ->and($result['keys'])->toBeGreaterThan(0)
        ->and($result['translations'])->toBeGreaterThan(0);

    expect(TranslationNamespace::query()->where('code', 'validation')->exists())->toBeTrue()
        ->and(TranslationNamespace::query()->where('code', 'payments')->exists())->toBeTrue()
        ->and(TranslationKey::query()->whereHas('namespace', fn ($q) => $q->where('code', 'payments'))->where('key', 'unknown_provider')->exists())->toBeTrue();

    $key = TranslationKey::query()->whereHas('namespace', fn ($q) => $q->where('code', 'payments'))->where('key', 'unknown_provider')->firstOrFail();
    expect(Translation::query()->where('translation_key_id', $key->id)->where('locale_code', 'ru')->value('value'))
        ->toBe('Неизвестный платёжный провайдер «:code».');
});

it('is idempotent — re-scanning does not duplicate keys or translations', function () {
    app(ScanTranslations::class)->handle();
    $firstCount = TranslationKey::query()->count();

    app(ScanTranslations::class)->handle();
    $secondCount = TranslationKey::query()->count();

    expect($secondCount)->toBe($firstCount);
});

it('reports no missing translations across the real ru/en/de lang files', function () {
    $missing = app(FindMissingTranslations::class)->handle();

    expect($missing)->toBe([]);
});

it('detects a genuinely missing translation when a locale lacks a key its siblings have', function () {
    file_put_contents(base_path('lang/en/__tooling_test.php'), "<?php\nreturn ['only_in_en' => 'x'];\n");

    try {
        $missing = app(FindMissingTranslations::class)->handle();

        expect($missing)->toHaveKey('__tooling_test')
            ->and($missing['__tooling_test']['ru'] ?? [])->toContain('only_in_en')
            ->and($missing['__tooling_test']['de'] ?? [])->toContain('only_in_en');
    } finally {
        @unlink(base_path('lang/en/__tooling_test.php'));
    }
});

it('detects an unused index key once removed from the source file', function () {
    app(ScanTranslations::class)->handle();

    $namespace = TranslationNamespace::query()->where('code', 'payments')->firstOrFail();
    $stale = TranslationKey::query()->create(['namespace_id' => $namespace->id, 'key' => 'this_key_no_longer_exists_anywhere']);
    Translation::query()->create(['translation_key_id' => $stale->id, 'locale_code' => 'en', 'value' => 'stale', 'source' => 'scan']);

    $unused = app(FindUnusedTranslationKeys::class)->handle();

    expect($unused['payments'] ?? [])->toContain('this_key_no_longer_exists_anywhere');
});
