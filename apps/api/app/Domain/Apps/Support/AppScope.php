<?php

namespace App\Domain\Apps\Support;

/**
 * The platform's OAuth scope registry — a plain string list, not a hard
 * PHP enum, so a new scope is a one-line addition here (spec section 4:
 * "future scopes should be easy to add") rather than a migration or a
 * breaking enum change. `scope:*` middleware and AppPermission both
 * treat scopes as opaque strings; this class is only consulted at
 * registration/consent time to reject an unknown scope.
 */
final class AppScope
{
    /**
     * @return string[]
     */
    public static function known(): array
    {
        return [
            'products.read',
            'products.write',
            'orders.read',
            'orders.write',
            'customers.read',
            'inventory.read',
            'inventory.write',
            'payments.read',
            'shipping.read',
            'webhooks.read',
            'webhooks.write',
            'automation.write',
        ];
    }

    public static function isKnown(string $scope): bool
    {
        return in_array($scope, self::known(), true);
    }
}
