<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

it('creates a workflow as a draft with exactly one version', function () {
    $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/automation/workflows', [
        'name' => 'Welcome Flow',
        'trigger' => ['event_type' => 'CustomerCreated'],
        'conditions' => [],
        'actions' => [['type' => 'create_internal_notification', 'config' => ['title' => 'Hi']]],
    ], tenantHeader($this->store));

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.version.trigger.event_type', 'CustomerCreated')
        ->assertJsonCount(1, 'data.version.actions');
});

it('refuses to publish a workflow with no trigger', function () {
    $created = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/automation/workflows', [
        'name' => 'No Trigger Flow',
    ], tenantHeader($this->store));

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/automation/workflows/{$created->json('data.id')}/publish", [], tenantHeader($this->store))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('trigger');
});

it('publishes a draft, edits create a new draft version, and the previous version is archived on republish', function () {
    $created = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/automation/workflows', [
        'name' => 'Versioned Flow',
        'trigger' => ['event_type' => 'CustomerCreated'],
        'actions' => [['type' => 'create_task', 'config' => ['title' => 'v1']]],
    ], tenantHeader($this->store));
    $id = $created->json('data.id');

    $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/automation/workflows/{$id}/publish", [], tenantHeader($this->store))->assertOk();

    $edited = $this->actingAs($this->user, 'sanctum')->patchJson("/api/v1/automation/workflows/{$id}", [
        'actions' => [['type' => 'create_task', 'config' => ['title' => 'v2']]],
    ], tenantHeader($this->store));
    $edited->assertOk()->assertJsonPath('data.version.trigger.event_type', 'CustomerCreated');

    $published = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/automation/workflows/{$id}/publish", [], tenantHeader($this->store));
    $published->assertOk();

    $versions = $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/automation/workflows/{$id}/versions", tenantHeader($this->store));
    $versions->assertOk()->assertJsonCount(2, 'data');
    $statuses = collect($versions->json('data'))->pluck('status', 'version_number');
    expect($statuses[1])->toBe('archived');
    expect($statuses[2])->toBe('published');
});

it('disables and re-enables a published workflow, and rejects disabling a non-published one', function () {
    $created = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/automation/workflows', [
        'name' => 'Disable Flow',
        'trigger' => ['event_type' => 'CustomerCreated'],
    ], tenantHeader($this->store));
    $id = $created->json('data.id');

    $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/automation/workflows/{$id}/disable", [], tenantHeader($this->store))
        ->assertUnprocessable();

    $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/automation/workflows/{$id}/publish", [], tenantHeader($this->store))->assertOk();

    $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/automation/workflows/{$id}/disable", [], tenantHeader($this->store))
        ->assertOk()->assertJsonPath('data.status', 'disabled');

    $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/automation/workflows/{$id}/enable", [], tenantHeader($this->store))
        ->assertOk()->assertJsonPath('data.status', 'published');
});

it('archives a workflow', function () {
    $created = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/automation/workflows', [
        'name' => 'Archive Flow',
    ], tenantHeader($this->store));

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/v1/automation/workflows/{$created->json('data.id')}/archive", [], tenantHeader($this->store))
        ->assertOk()->assertJsonPath('data.status', 'archived');
});

it('rolls back to an old version, cloning its content into a brand new published version', function () {
    $created = $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/automation/workflows', [
        'name' => 'Rollback Flow',
        'trigger' => ['event_type' => 'CustomerCreated'],
        'actions' => [['type' => 'create_task', 'config' => ['title' => 'original']]],
    ], tenantHeader($this->store));
    $id = $created->json('data.id');
    $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/automation/workflows/{$id}/publish", [], tenantHeader($this->store));

    $versions = $this->actingAs($this->user, 'sanctum')->getJson("/api/v1/automation/workflows/{$id}/versions", tenantHeader($this->store));
    $v1Id = collect($versions->json('data'))->firstWhere('version_number', 1)['id'];

    $this->actingAs($this->user, 'sanctum')->patchJson("/api/v1/automation/workflows/{$id}", [
        'actions' => [['type' => 'create_task', 'config' => ['title' => 'changed']]],
    ], tenantHeader($this->store));
    $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/automation/workflows/{$id}/publish", [], tenantHeader($this->store));

    $rolledBack = $this->actingAs($this->user, 'sanctum')->postJson("/api/v1/automation/workflows/{$id}/rollback", [
        'version_id' => $v1Id,
    ], tenantHeader($this->store));

    $rolledBack->assertOk()->assertJsonPath('data.version.version_number', 3);
    expect($rolledBack->json('data.version.actions.0.config.title'))->toBe('original');
});
