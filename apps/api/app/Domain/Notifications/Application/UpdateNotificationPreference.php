<?php

namespace App\Domain\Notifications\Application;

use App\Domain\Customers\Models\Customer;
use App\Domain\Notifications\Models\NotificationPreference;

/**
 * Upserts rather than requiring a pre-existing row — a customer who has
 * never touched their preferences has none yet (spec section 6 doesn't
 * ask for a seeded default row per customer the way notification
 * channels are seeded per store).
 */
final class UpdateNotificationPreference
{
    /**
     * @param  array{email_enabled?: bool, sms_enabled?: bool, push_enabled?: bool, marketing_opt_in?: bool, transactional_only?: bool, quiet_hours_start?: ?string, quiet_hours_end?: ?string, quiet_hours_timezone?: ?string}  $data
     */
    public function handle(Customer $customer, array $data): NotificationPreference
    {
        $preference = NotificationPreference::query()->firstOrNew(['customer_id' => $customer->id]);

        $preference->fill([
            'email_enabled' => $data['email_enabled'] ?? $preference->email_enabled ?? true,
            'sms_enabled' => $data['sms_enabled'] ?? $preference->sms_enabled ?? false,
            'push_enabled' => $data['push_enabled'] ?? $preference->push_enabled ?? false,
            'marketing_opt_in' => $data['marketing_opt_in'] ?? $preference->marketing_opt_in ?? false,
            'transactional_only' => $data['transactional_only'] ?? $preference->transactional_only ?? true,
            'quiet_hours_start' => array_key_exists('quiet_hours_start', $data) ? $data['quiet_hours_start'] : $preference->quiet_hours_start,
            'quiet_hours_end' => array_key_exists('quiet_hours_end', $data) ? $data['quiet_hours_end'] : $preference->quiet_hours_end,
            'quiet_hours_timezone' => array_key_exists('quiet_hours_timezone', $data) ? $data['quiet_hours_timezone'] : $preference->quiet_hours_timezone,
        ])->save();

        return $preference->fresh();
    }
}
