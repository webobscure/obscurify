<?php

use App\Domain\Themes\Application\CreateTheme;
use App\Domain\Themes\Application\PublishThemeVersion;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);

    app(TenantContext::class)->scope($this->storeA, function () {
        $theme = app(CreateTheme::class)->handle(['name' => 'Retail', 'slug' => 'retail']);
        app(PublishThemeVersion::class)->handle($theme->versions()->firstOrFail());
    });
});

it('lazily seeds and lists the built-in section/block library the first time presets are requested', function () {
    $response = $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/builder/presets', tenantHeader($this->storeA))->assertOk();

    $types = collect($response->json('data'))->pluck('type')->unique()->values()->all();
    expect($types)->toContain('section')->toContain('block');

    $handles = collect($response->json('data'))->pluck('handle');
    expect($handles)->toContain('hero')->toContain('button')->toContain('accordion');
});

it('filters presets by type', function () {
    $response = $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/builder/presets?type=block', tenantHeader($this->storeA))->assertOk();

    expect(collect($response->json('data'))->pluck('type')->unique()->values()->all())->toBe(['block']);
});

it('never lets Store A see Store B presets', function () {
    app(TenantContext::class)->scope($this->storeB, function () {
        $theme = app(CreateTheme::class)->handle(['name' => 'B Theme', 'slug' => 'b-theme']);
        app(PublishThemeVersion::class)->handle($theme->versions()->firstOrFail());
    });

    $bResponse = $this->actingAs($this->userB, 'sanctum')->getJson('/api/v1/builder/presets', tenantHeader($this->storeB))->assertOk();
    $bIds = collect($bResponse->json('data'))->pluck('id');

    $aResponse = $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/builder/presets', tenantHeader($this->storeA))->assertOk();
    $aIds = collect($aResponse->json('data'))->pluck('id');

    expect($aIds->intersect($bIds))->toBeEmpty();
});
