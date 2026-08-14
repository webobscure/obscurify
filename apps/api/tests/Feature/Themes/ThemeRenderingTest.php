<?php

use App\Domain\Themes\Application\CreateTheme;
use App\Domain\Themes\Application\PublishThemeVersion;
use App\Domain\Themes\Enums\ThemeTemplateType;
use App\Domain\Themes\Models\ThemeBlock;
use App\Domain\Themes\Models\ThemeSetting;
use App\Domain\Themes\Support\ThemeRenderer;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

it('resolves the active version, merges section defaults with instance overrides, and resolves blocks', function () {
    [$theme, $version] = app(TenantContext::class)->scope($this->store, function () {
        $theme = app(CreateTheme::class)->handle(['name' => 'Retail', 'slug' => 'retail']);
        $version = $theme->versions()->firstOrFail();

        $hero = $version->sections()->where('handle', 'hero')->firstOrFail();

        ThemeBlock::query()->create([
            'theme_version_id' => $version->id,
            'theme_section_id' => $hero->id,
            'handle' => 'button',
            'name' => 'Button',
            'schema' => [['id' => 'label', 'type' => 'text', 'default' => 'Shop now']],
        ]);

        $template = $version->templates()->where('type', ThemeTemplateType::Home->value)->firstOrFail();
        $template->update(['sections' => [[
            'id' => 'hero-1',
            'section_handle' => 'hero',
            // Only overrides `heading` — `subheading`/`image` must fall
            // back to the section type's own schema defaults.
            'settings' => ['heading' => 'Big Sale'],
            'blocks' => [['id' => 'btn-1', 'block_handle' => 'button', 'settings' => []]],
        ]]]);

        ThemeSetting::query()->create(['theme_version_id' => $version->id, 'key' => 'color_primary', 'value' => '#ff0000']);

        return [$theme, $version];
    });

    app(TenantContext::class)->scope($this->store, function () use ($theme, $version) {
        app(PublishThemeVersion::class)->handle($version);

        $page = app(ThemeRenderer::class)->render($this->store->id, ThemeTemplateType::Home);

        expect($page->template)->toBe('home')
            ->and($page->isPreview)->toBeFalse()
            ->and($page->themeId)->toBe($theme->id)
            ->and($page->globalSettings)->toBe(['color_primary' => '#ff0000']);

        $section = $page->sections[0];
        expect($section->handle)->toBe('hero')
            ->and($section->settings['heading'])->toBe('Big Sale')
            ->and($section->settings['subheading'])->toBe('')
            ->and($section->blocks)->toHaveCount(1)
            ->and($section->blocks[0]['handle'])->toBe('button')
            ->and($section->blocks[0]['settings']['label'])->toBe('Shop now');
    });
});

it('preview renders the current draft, never the live active version', function () {
    [$theme, $v1] = app(TenantContext::class)->scope($this->store, function () {
        $theme = app(CreateTheme::class)->handle(['name' => 'Retail', 'slug' => 'retail']);
        $v1 = $theme->versions()->firstOrFail();

        return [$theme, $v1];
    });

    app(TenantContext::class)->scope($this->store, function () use ($v1) {
        app(PublishThemeVersion::class)->handle($v1);
    });

    // Edit the NEW draft (v2) that publishing just created — the live
    // site (v1) must not reflect this.
    app(TenantContext::class)->scope($this->store, function () use ($theme) {
        $draft = $theme->fresh()->versions()->where('status', 'draft')->firstOrFail();
        $template = $draft->templates()->where('type', 'home')->firstOrFail();
        $template->update(['sections' => [['id' => 'hero-1', 'section_handle' => 'hero', 'settings' => ['heading' => 'Draft Only'], 'blocks' => []]]]);
    });

    app(TenantContext::class)->scope($this->store, function () {
        $live = app(ThemeRenderer::class)->render($this->store->id, ThemeTemplateType::Home);
        expect($live->sections[0]->settings['heading'])->toBe('Welcome to our store')
            ->and($live->isPreview)->toBeFalse();

        $preview = app(ThemeRenderer::class)->render($this->store->id, ThemeTemplateType::Home, preview: true);
        expect($preview->sections[0]->settings['heading'])->toBe('Draft Only')
            ->and($preview->isPreview)->toBeTrue();
    });
});

it('the storefront endpoint renders the active theme without any admin session', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $theme = app(CreateTheme::class)->handle(['name' => 'Retail', 'slug' => 'retail']);
        app(PublishThemeVersion::class)->handle($theme->versions()->firstOrFail());
    });

    domainForStore($this->store, 'render-test.localhost');

    $response = $this->getJson(storefrontUrl('render-test.localhost', '/api/v1/storefront/theme/home'))->assertOk();

    expect($response->json('data.template'))->toBe('home')
        ->and($response->json('data.sections.0.handle'))->toBe('hero');
});

it('404s when the store has no active theme yet', function () {
    domainForStore($this->store, 'no-theme-test.localhost');

    $this->getJson(storefrontUrl('no-theme-test.localhost', '/api/v1/storefront/theme/home'))->assertStatus(404);
});
