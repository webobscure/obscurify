<?php

namespace App\Domain\Notifications\Application;

use App\Domain\Notifications\Enums\NotificationChannelType;
use App\Domain\Notifications\Models\NotificationChannel;
use App\Domain\Notifications\Models\NotificationProvider;
use App\Domain\Stores\Models\Store;
use App\Shared\Tenancy\TenantContext;

/**
 * Idempotently seeds a store's default notification setup: one "fake"
 * NotificationProvider and one NotificationChannel row per channel
 * type, all pointing at it and enabled — so FakeNotificationProvider
 * genuinely works out of the box (spec: "FakeNotificationProvider as
 * the default reference implementation") without a merchant having to
 * configure anything first. Called both from `notifications:install`
 * and lazily by the admin Channels/Providers list endpoints (the same
 * "auto-create on first read" convention Milestone 20's default
 * dashboard uses), so tests and fresh stores never need an explicit
 * install step.
 */
final class EnsureDefaultNotificationSetup
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Store $store): void
    {
        $this->tenantContext->scope($store, function () use ($store) {
            $provider = NotificationProvider::query()->firstOrCreate(
                ['store_id' => $store->id, 'code' => NotificationProvider::FAKE],
                ['name' => 'Fake Provider', 'is_enabled' => true, 'config' => []],
            );

            foreach (NotificationChannelType::cases() as $channel) {
                NotificationChannel::query()->firstOrCreate(
                    ['store_id' => $store->id, 'channel' => $channel->value],
                    ['provider_id' => $provider->id, 'is_enabled' => true],
                );
            }
        });
    }
}
