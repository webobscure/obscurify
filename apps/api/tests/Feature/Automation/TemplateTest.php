<?php

use App\Domain\Automation\Application\RegisterBuiltInAutomationCatalog;
use App\Domain\Automation\Models\WorkflowTemplate;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    app(RegisterBuiltInAutomationCatalog::class)->handle();
});

it('registering the built-in catalog is idempotent', function () {
    $countBefore = WorkflowTemplate::count();
    app(RegisterBuiltInAutomationCatalog::class)->handle();
    expect(WorkflowTemplate::count())->toBe($countBefore);
});

it('lists the 8 starter templates over the API', function () {
    $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/automation/templates', tenantHeader($this->store));

    $response->assertOk()->assertJsonCount(8, 'data');
    expect(collect($response->json('data'))->pluck('key'))->toContain('welcome-customer', 'high-value-order', 'back-in-stock');
});

it('instantiates a template into a real, draft workflow via the API', function () {
    $template = WorkflowTemplate::query()->where('key', 'high-value-order')->firstOrFail();

    $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/automation/templates/{$template->id}/instantiate", [], tenantHeader($this->store));

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.version.trigger.event_type', 'OrderCreated')
        ->assertJsonCount(1, 'data.version.conditions')
        ->assertJsonCount(2, 'data.version.actions');
});
