<?php

namespace App\Domain\Apps\Enums;

/**
 * Private: belongs to the store that created it, installable only
 * there. Public: platform-level (`store_id = null`), installable by any
 * store via OAuth — "internal support only" (spec section 2): no
 * marketplace listing/discovery UI exists, a merchant is given its
 * install link directly by whoever built it.
 */
enum AppType: string
{
    case Private = 'private';
    case Public = 'public';
}
