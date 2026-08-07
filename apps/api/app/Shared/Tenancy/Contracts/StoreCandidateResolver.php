<?php

namespace App\Shared\Tenancy\Contracts;

use Illuminate\Http\Request;

/**
 * Resolves a *candidate* store identifier from the current request.
 *
 * The candidate is never trusted as authorization on its own — it must
 * still be validated against store membership before a TenantContext is
 * established. Implementations exist per resolution strategy (merchant
 * admin header today; storefront hostname/domain and API token binding
 * are future strategies described in ARCHITECTURE.md section 9).
 */
interface StoreCandidateResolver
{
    public function resolve(Request $request): ?string;
}
