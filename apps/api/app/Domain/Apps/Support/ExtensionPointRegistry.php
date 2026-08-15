<?php

namespace App\Domain\Apps\Support;

use App\Domain\Apps\Enums\ExtensionPoint;
use Illuminate\Validation\ValidationException;

/**
 * Validates an app's extension contribution against its extension
 * point's minimal required config shape — "pluggable" (spec section 8)
 * means a new point is one new ExtensionPoint case plus one new `match`
 * arm here, not a schema change (`app_extensions.config` is jsonb
 * regardless of point). AdminNavigation/AdminWidget/DashboardCard are
 * the points the admin UI actually reads (see AdminExtensionsController);
 * Checkout/Order/Product/Customer are validated the same way but stay
 * backend-only contracts this milestone (no live storefront-embedding
 * mechanism exists — see docs/architecture/apps.md §8).
 */
final class ExtensionPointRegistry
{
    /**
     * @param  array<string, mixed>  $config
     */
    public static function assertValidConfig(ExtensionPoint $point, array $config): void
    {
        $required = match ($point) {
            ExtensionPoint::AdminNavigation => ['label', 'path'],
            ExtensionPoint::AdminWidget => ['title', 'location'],
            ExtensionPoint::DashboardCard => ['title'],
            ExtensionPoint::Checkout, ExtensionPoint::Order, ExtensionPoint::Product, ExtensionPoint::Customer => [],
            // Milestone 19: an app-registered automation action must name
            // itself and where WorkflowActionExecutor should POST to when
            // the action runs; a trigger/variable just needs to declare
            // the event_type/label a merchant would see in the picker; a
            // template is a full portable workflow definition.
            ExtensionPoint::AutomationAction => ['label', 'target_url'],
            ExtensionPoint::AutomationTrigger => ['event_type', 'label'],
            ExtensionPoint::AutomationVariable => ['source', 'key', 'label', 'type'],
            ExtensionPoint::AutomationTemplate => ['name', 'definition'],
        };

        $missing = array_diff($required, array_keys($config));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'config' => 'Missing required config key(s) for '.$point->value.': '.implode(', ', $missing),
            ]);
        }
    }
}
