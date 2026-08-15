<?php

use App\Domain\Customers\Models\Customer;
use App\Domain\Notifications\Application\EnsureDefaultNotificationSetup;
use App\Domain\Notifications\Application\NotificationDispatcher;
use App\Domain\Notifications\Enums\NotificationChannelType;
use App\Domain\Notifications\Enums\NotificationDeliveryStatus;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Domain\Notifications\Enums\NotificationTriggerSource;
use App\Domain\Notifications\Jobs\SendNotificationDeliveryJob;
use App\Domain\Notifications\Models\NotificationDelivery;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Support\NotificationDispatchRequest;
use App\Domain\Notifications\Support\NotificationRecipientInput;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    app(TenantContext::class)->scope($this->store, function () {
        app(EnsureDefaultNotificationSetup::class)->handle($this->store);
    });
});

it('succeeds on the first attempt and marks the notification delivered', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create(['email' => 'ada@example.test']);

        $notification = app(NotificationDispatcher::class)->dispatch($this->store, new NotificationDispatchRequest(
            channel: NotificationChannelType::Email,
            triggeredBy: NotificationTriggerSource::Admin,
            recipients: [NotificationRecipientInput::customer($customer->id)],
            bodyText: 'Hello',
        ));

        $delivery = $notification->deliveries()->firstOrFail();
        expect($delivery->status)->toBe(NotificationDeliveryStatus::Succeeded);
        expect($delivery->attempt_count)->toBe(1);
        expect($notification->fresh()->status)->toBe(NotificationStatus::Delivered);
    });
});

it('fails against the reserved fake-provider failure address, schedules a retry, then exhausts after MAX_ATTEMPTS', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $notification = app(NotificationDispatcher::class)->dispatch($this->store, new NotificationDispatchRequest(
            channel: NotificationChannelType::Email,
            triggeredBy: NotificationTriggerSource::Admin,
            recipients: [NotificationRecipientInput::adHoc('always-fails@fail.test')],
            bodyText: 'Hello',
        ));

        $delivery = $notification->deliveries()->firstOrFail();
        expect($delivery->status)->toBe(NotificationDeliveryStatus::Failed);
        expect($delivery->attempt_count)->toBe(1);
        expect($delivery->next_retry_at)->not->toBeNull();
        expect($notification->fresh()->status)->toBe(NotificationStatus::Failed);

        // Drive it through the remaining attempts directly (bypassing the
        // backoff wait, which is RetryFailedNotificationDeliveriesCommand's
        // concern, not this job's own guard — see SendNotificationDeliveryJob).
        for ($i = 2; $i <= SendNotificationDeliveryJob::MAX_ATTEMPTS; $i++) {
            SendNotificationDeliveryJob::dispatch($delivery->id);
        }

        $delivery = $delivery->fresh();
        expect($delivery->attempt_count)->toBe(SendNotificationDeliveryJob::MAX_ATTEMPTS);
        expect($delivery->status)->toBe(NotificationDeliveryStatus::Exhausted);
        expect($delivery->next_retry_at)->toBeNull();
    });
});

it('re-dispatches only a failed delivery whose backoff window has passed', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $notification = app(NotificationDispatcher::class)->dispatch($this->store, new NotificationDispatchRequest(
            channel: NotificationChannelType::Email,
            triggeredBy: NotificationTriggerSource::Admin,
            recipients: [NotificationRecipientInput::adHoc('always-fails@fail.test')],
            bodyText: 'Hello',
        ));

        $delivery = $notification->deliveries()->firstOrFail();
        expect($delivery->attempt_count)->toBe(1);

        // Not yet due — command must not touch it.
        $this->artisan('notifications:retry-failed');
        expect($delivery->fresh()->attempt_count)->toBe(1);

        // Force it due, then the command should re-dispatch and it fails again.
        NotificationDelivery::withoutGlobalScopes()->whereKey($delivery->id)->update(['next_retry_at' => Carbon::now()->subMinute()]);
        $this->artisan('notifications:retry-failed');
        expect($delivery->fresh()->attempt_count)->toBe(2);
    });
});

it('marks a delivery suppressed instead of attempting it when the customer has disabled the channel', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create(['email' => 'ada@example.test']);
        NotificationPreference::query()->create([
            'customer_id' => $customer->id, 'email_enabled' => false,
        ]);

        $notification = app(NotificationDispatcher::class)->dispatch($this->store, new NotificationDispatchRequest(
            channel: NotificationChannelType::Email,
            triggeredBy: NotificationTriggerSource::Admin,
            recipients: [NotificationRecipientInput::customer($customer->id)],
            bodyText: 'Hello',
        ));

        $delivery = $notification->deliveries()->firstOrFail();
        expect($delivery->status)->toBe(NotificationDeliveryStatus::Suppressed);
        expect($delivery->attempt_count)->toBe(0);
    });
});
