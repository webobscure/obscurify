<?php

namespace App\Domain\Apps\Enums;

/**
 * The fixed, pluggable set of places an app can register a contribution
 * (spec sections 8/9) — "pluggable" means adding a new case here plus a
 * matching admin-surface reader, not a schema change (`app_extensions`
 * already stores `config` as jsonb regardless of which point it's for).
 *
 * AdminNavigation/AdminWidget/DashboardCard are the ones this milestone
 * actually renders in the admin UI (see AdminExtensionsController /
 * apps/admin's own consumption of GET /admin-extensions). Checkout/
 * Order/Product/Customer are registered and validated the same way but
 * stay backend-only contracts this milestone — there is no live
 * storefront-embedding mechanism yet (spec: "must not contain
 * storefront-specific logic"); see docs/architecture/apps.md §8.
 */
enum ExtensionPoint: string
{
    case Checkout = 'checkout';
    case Order = 'order';
    case Product = 'product';
    case Customer = 'customer';
    case AdminNavigation = 'admin_navigation';
    case AdminWidget = 'admin_widget';
    case DashboardCard = 'dashboard_card';

    // Milestone 19 (Automation Engine) — spec section 10: apps register
    // triggers/actions/templates/variables with no core changes, through
    // this exact existing mechanism. See docs/architecture/automation.md §9.
    case AutomationTrigger = 'automation_trigger';
    case AutomationAction = 'automation_action';
    case AutomationTemplate = 'automation_template';
    case AutomationVariable = 'automation_variable';
}
