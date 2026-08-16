<?php

namespace App\Domain\Notifications\Support;

use App\Domain\Notifications\Models\NotificationTemplate;
use App\Domain\Stores\Models\Store;

/**
 * Spec section 11: "Templates must support localization. Notification
 * rendering should use recipient locale." `NotificationTemplate.locale`
 * has existed since Milestone 21 (default 'en', structurally ready but
 * never read — see that table's own migration docblock); this is what
 * finally reads it.
 *
 * A template family shares the same `key` across one row per locale
 * (e.g. three `order_confirmation` rows: locale=ru/en/de, same
 * channel) — resolution order: recipient locale -> store's own
 * default_locale -> the originally-configured base template
 * (whatever locale that happens to be), so a family with only a
 * partial set of locale rows still degrades to *something* rather than
 * failing the send. A `key`-less template (an admin ad-hoc compose has
 * no family to select within) is returned unchanged.
 */
final class ResolveLocalizedNotificationTemplate
{
    public function handle(NotificationTemplate $base, ?string $recipientLocale, Store $store): NotificationTemplate
    {
        if ($base->key === null) {
            return $base;
        }

        foreach (array_filter([$recipientLocale, $store->default_locale]) as $candidateLocale) {
            if ($candidateLocale === $base->locale) {
                return $base;
            }

            $match = NotificationTemplate::query()
                ->where('key', $base->key)
                ->where('channel', $base->channel->value)
                ->where('locale', $candidateLocale)
                ->where('is_active', true)
                ->first();

            if ($match !== null) {
                return $match;
            }
        }

        return $base;
    }
}
