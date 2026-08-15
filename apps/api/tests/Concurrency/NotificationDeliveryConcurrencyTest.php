<?php

use App\Domain\Notifications\Application\EnsureDefaultNotificationSetup;
use App\Domain\Notifications\Application\NotificationDispatcher;
use App\Domain\Notifications\Enums\NotificationChannelType;
use App\Domain\Notifications\Enums\NotificationDeliveryStatus;
use App\Domain\Notifications\Enums\NotificationTriggerSource;
use App\Domain\Notifications\Jobs\SendNotificationDeliveryJob;
use App\Domain\Notifications\Models\NotificationDelivery;
use App\Domain\Notifications\Support\NotificationDispatchRequest;
use App\Domain\Notifications\Support\NotificationRecipientInput;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Same fork-based real-concurrency pattern as
 * WorkflowExecutionConcurrencyTest — proves SendNotificationDeliveryJob's
 * guarded claim UPDATE actually prevents the race it exists to prevent:
 * without it, a manual "Retry" click racing an automatic
 * notifications:retry-failed pass could both read the same starting
 * attempt_count and both call the provider, corrupting the retry count
 * and potentially sending twice.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);

    $this->deliveryId = app(TenantContext::class)->scope($this->store, function () {
        app(EnsureDefaultNotificationSetup::class)->handle($this->store);

        $notification = app(NotificationDispatcher::class)->dispatch($this->store, new NotificationDispatchRequest(
            channel: NotificationChannelType::Email,
            triggeredBy: NotificationTriggerSource::Admin,
            recipients: [NotificationRecipientInput::adHoc('customer@example.test')],
            bodyText: 'Hello',
        ));

        $delivery = $notification->deliveries()->firstOrFail();

        // Reset it back to pending so both forked workers race on a
        // genuinely claimable row, rather than racing on the row this
        // synchronous dispatch() call already resolved.
        $delivery->update(['status' => NotificationDeliveryStatus::Pending->value, 'attempt_count' => 0]);

        return $delivery->id;
    });
});

afterEach(function () {
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('lets two workers race to send the same pending delivery, without double-sending or corrupting attempt_count', function () {
    $run = function () {
        SendNotificationDeliveryJob::dispatchSync($this->deliveryId);

        return true;
    };

    $results = runConcurrently([$run, $run]);

    $succeeded = array_filter($results, fn ($r) => $r['ok']);
    expect($succeeded)->toHaveCount(2);

    app(TenantContext::class)->scope($this->store, function () {
        $delivery = NotificationDelivery::query()->findOrFail($this->deliveryId);
        expect($delivery->status)->toBe(NotificationDeliveryStatus::Succeeded);
        // Exactly one of the two workers actually claimed and ran the
        // send — the other's claim UPDATE affected 0 rows and returned
        // immediately, so attempt_count reflects one real attempt, not two.
        expect($delivery->attempt_count)->toBe(1);
    });
});
