<?php

use App\Domain\Themes\Application\CreateTheme;
use App\Domain\Themes\Application\PublishThemeVersion;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
});

it('404s when the store has no active theme yet', function () {
    $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/builder/theme-customizer', tenantHeader($this->storeA))->assertStatus(404);
});

it('resolves the active theme\'s current draft version, returns the known field schema, and saves through the existing settings endpoint', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        $theme = app(CreateTheme::class)->handle(['name' => 'Retail', 'slug' => 'retail']);
        app(PublishThemeVersion::class)->handle($theme->versions()->firstOrFail());
    });

    $response = $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/builder/theme-customizer', tenantHeader($this->storeA))->assertOk();

    $fieldKeys = collect($response->json('data.schema'))->pluck('key');
    expect($fieldKeys)->toContain('logo')
        ->and($fieldKeys)->toContain('color_primary')
        ->and($fieldKeys)->toContain('border_radius')
        ->and($fieldKeys)->toContain('announcement_bar_text')
        ->and($fieldKeys)->toContain('favicon')
        ->and($response->json('data.values'))->toBe([]);

    $draftVersionId = $response->json('data.theme_version_id');

    $this->actingAs($this->userA, 'sanctum')->patchJson(
        "/api/v1/theme-versions/{$draftVersionId}/settings",
        ['settings' => ['color_primary' => '#ff0000', 'container_width' => '1200px']],
        tenantHeader($this->storeA),
    )->assertOk();

    $refetched = $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/builder/theme-customizer', tenantHeader($this->storeA))->assertOk();
    expect($refetched->json('data.values.color_primary'))->toBe('#ff0000')
        ->and($refetched->json('data.values.container_width'))->toBe('1200px');
});
