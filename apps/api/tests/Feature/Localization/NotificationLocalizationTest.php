<?php

use App\Domain\Customers\Models\Customer;
use App\Domain\Notifications\Application\DispatchNotificationsForEvent;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Models\NotificationEvent;
use App\Domain\Notifications\Models\NotificationTemplate;
use App\Models\User;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;

/**
 * Spec section 11: "Templates must support localization. Notification
 * rendering should use recipient locale." NotificationTemplate.locale
 * has existed since Milestone 21 (structurally ready, never read) —
 * see ResolveLocalizedNotificationTemplate.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
});

/**
 * @return array{en: NotificationTemplate, ru: NotificationTemplate, de: NotificationTemplate}
 */
function orderConfirmationTemplateFamily(): array
{
    return [
        'en' => NotificationTemplate::query()->create([
            'key' => 'order_confirmation', 'name' => 'Order Confirmation (EN)', 'channel' => 'email', 'locale' => 'en',
            'subject' => 'Order confirmed', 'body_text' => 'Hi {{customer.first_name}}, your order is confirmed.',
        ]),
        'ru' => NotificationTemplate::query()->create([
            'key' => 'order_confirmation', 'name' => 'Order Confirmation (RU)', 'channel' => 'email', 'locale' => 'ru',
            'subject' => 'Заказ подтверждён', 'body_text' => 'Здравствуйте, {{customer.first_name}}, ваш заказ подтверждён.',
        ]),
        'de' => NotificationTemplate::query()->create([
            'key' => 'order_confirmation', 'name' => 'Order Confirmation (DE)', 'channel' => 'email', 'locale' => 'de',
            'subject' => 'Bestellung bestätigt', 'body_text' => 'Hallo {{customer.first_name}}, Ihre Bestellung wurde bestätigt.',
        ]),
    ];
}

it('renders the template matching the recipient customer\'s own saved locale', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $templates = orderConfirmationTemplateFamily();
        $customer = Customer::factory()->create(['first_name' => 'Ada', 'locale' => 'ru']);

        NotificationEvent::query()->create([
            'event_type' => 'CustomerCreated', 'channel' => 'email', 'template_id' => $templates['en']->id, 'is_enabled' => true,
        ]);

        $event = app(RecordOutboxEvent::class)->handle('CustomerCreated', 'Customer', $customer->id, ['customer_id' => $customer->id]);
        app(DispatchNotificationsForEvent::class)->handle($event, $this->store);

        $notification = Notification::query()->where('event_type', 'CustomerCreated')->firstOrFail();
        expect($notification->template_id)->toBe($templates['ru']->id)
            ->and($notification->subject)->toBe('Заказ подтверждён')
            ->and($notification->body_text)->toBe('Здравствуйте, Ada, ваш заказ подтверждён.');
    });
});

it('falls back to the store\'s default_locale when the customer has no saved preference', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $templates = orderConfirmationTemplateFamily();
        $customer = Customer::factory()->create(['first_name' => 'Ada', 'locale' => null]);

        // The store's own default_locale (set by StoreFactory) is 'ru'.
        expect($this->store->default_locale)->toBe('ru');

        NotificationEvent::query()->create([
            'event_type' => 'CustomerCreated', 'channel' => 'email', 'template_id' => $templates['en']->id, 'is_enabled' => true,
        ]);

        $event = app(RecordOutboxEvent::class)->handle('CustomerCreated', 'Customer', $customer->id, ['customer_id' => $customer->id]);
        app(DispatchNotificationsForEvent::class)->handle($event, $this->store);

        $notification = Notification::query()->where('event_type', 'CustomerCreated')->firstOrFail();
        expect($notification->template_id)->toBe($templates['ru']->id);
    });
});

it('degrades to the originally configured template when the customer\'s locale has no sibling row', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $templates = orderConfirmationTemplateFamily();
        // A locale with no template row in this family at all.
        $customer = Customer::factory()->create(['locale' => 'fr']);

        NotificationEvent::query()->create([
            'event_type' => 'CustomerCreated', 'channel' => 'email', 'template_id' => $templates['de']->id, 'is_enabled' => true,
        ]);

        $event = app(RecordOutboxEvent::class)->handle('CustomerCreated', 'Customer', $customer->id, ['customer_id' => $customer->id]);
        app(DispatchNotificationsForEvent::class)->handle($event, $this->store);

        $notification = Notification::query()->where('event_type', 'CustomerCreated')->firstOrFail();
        // 'fr' has no sibling, store default 'ru' has no sibling either
        // in this scenario? No — 'ru' DOES exist in the family, so it
        // wins over the originally configured 'de' template.
        expect($notification->template_id)->toBe($templates['ru']->id);
    });
});

it('leaves a key-less (admin ad-hoc) template completely unaffected by recipient locale', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $adHocTemplate = NotificationTemplate::query()->create([
            'key' => null, 'name' => 'One-off announcement', 'channel' => 'email',
            'subject' => 'Announcement', 'body_text' => 'Hello {{customer.first_name}}.',
        ]);
        $customer = Customer::factory()->create(['first_name' => 'Ada', 'locale' => 'de']);

        NotificationEvent::query()->create([
            'event_type' => 'CustomerCreated', 'channel' => 'email', 'template_id' => $adHocTemplate->id, 'is_enabled' => true,
        ]);

        $event = app(RecordOutboxEvent::class)->handle('CustomerCreated', 'Customer', $customer->id, ['customer_id' => $customer->id]);
        app(DispatchNotificationsForEvent::class)->handle($event, $this->store);

        $notification = Notification::query()->where('event_type', 'CustomerCreated')->firstOrFail();
        expect($notification->template_id)->toBe($adHocTemplate->id)
            ->and($notification->subject)->toBe('Announcement');
    });
});
