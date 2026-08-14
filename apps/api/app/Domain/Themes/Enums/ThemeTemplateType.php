<?php

namespace App\Domain\Themes\Enums;

/**
 * The fixed set of template slots a theme version may fill (spec
 * section 4). `Checkout` and `Blog` are listed in the spec as "future"
 * — the enum cases exist now (a theme CAN define them) but Commerce
 * Core's actual checkout flow and Milestone 14's blog routes don't
 * render through ThemeRenderer yet; see docs/architecture/themes.md.
 */
enum ThemeTemplateType: string
{
    case Home = 'home';
    case Collection = 'collection';
    case Product = 'product';
    case Cart = 'cart';
    case Checkout = 'checkout';
    case Search = 'search';
    case Blog = 'blog';
    case NotFound = '404';
    case Page = 'page';
}
