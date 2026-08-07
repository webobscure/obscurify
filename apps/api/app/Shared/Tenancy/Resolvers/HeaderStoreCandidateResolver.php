<?php

namespace App\Shared\Tenancy\Resolvers;

use App\Shared\Tenancy\Contracts\StoreCandidateResolver;
use Illuminate\Http\Request;

/**
 * Merchant admin resolution strategy: the client sends the previously
 * activated store id in a header. This is only ever a candidate — see
 * EnsureTenantContext for the mandatory membership validation.
 */
final class HeaderStoreCandidateResolver implements StoreCandidateResolver
{
    public const string HEADER = 'X-Store-Id';

    public function resolve(Request $request): ?string
    {
        $value = $request->header(self::HEADER);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
