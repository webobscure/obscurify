<?php

namespace App\Shared\Tenancy\Resolvers;

use App\Domain\Domains\Models\Domain;
use App\Shared\Tenancy\Contracts\StoreCandidateResolver;
use App\Shared\Tenancy\Support\HostNormalizer;
use Illuminate\Http\Request;

/**
 * Storefront resolution strategy: the Store is looked up from the
 * request's hostname via the `domains` table.
 *
 * `withoutGlobalScopes()` is required and correct here, not a bypass of
 * tenant isolation: Domain::class uses BelongsToTenant, whose scope
 * depends on TenantContext already being set — but resolving the Domain
 * row is exactly the step that determines what TenantContext to set.
 * There is no active tenant yet at this point in the request lifecycle.
 *
 * Like HeaderStoreCandidateResolver, this only ever produces a
 * *candidate* — see EnsureStorefrontTenantContext for where it becomes
 * the active TenantContext.
 */
final class DomainStoreCandidateResolver implements StoreCandidateResolver
{
    public function resolve(Request $request): ?string
    {
        $host = HostNormalizer::normalize($request->getHost());

        $domain = Domain::withoutGlobalScopes()->where('domain', $host)->first();

        return $domain?->store_id;
    }
}
