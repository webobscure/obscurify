<?php

use App\Domain\Notifications\Support\NotificationTemplateRenderer;

it('interpolates dot-path variables from the context', function () {
    $renderer = app(NotificationTemplateRenderer::class);

    $rendered = $renderer->render('Hi {{customer.first_name}}, your order #{{order.number}} shipped.', [
        'customer' => ['first_name' => 'Ada'],
        'order' => ['number' => 42],
    ]);

    expect($rendered)->toBe('Hi Ada, your order #42 shipped.');
});

it('renders a missing path as an empty string rather than leaving the placeholder', function () {
    $renderer = app(NotificationTemplateRenderer::class);

    expect($renderer->render('Hello {{customer.first_name}}!', []))->toBe('Hello !');
});

it('stringifies a non-scalar value as JSON', function () {
    $renderer = app(NotificationTemplateRenderer::class);

    $rendered = $renderer->render('Items: {{order.items}}', ['order' => ['items' => ['a', 'b']]]);

    expect($rendered)->toBe('Items: ["a","b"]');
});

it('returns an empty string for a null or empty template', function () {
    $renderer = app(NotificationTemplateRenderer::class);

    expect($renderer->render(null, ['x' => 'y']))->toBe('');
    expect($renderer->render('', ['x' => 'y']))->toBe('');
});

it('leaves ordinary text with no placeholders untouched', function () {
    $renderer = app(NotificationTemplateRenderer::class);

    expect($renderer->render('No variables here.', []))->toBe('No variables here.');
});
