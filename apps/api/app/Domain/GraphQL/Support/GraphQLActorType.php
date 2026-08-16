<?php

namespace App\Domain\GraphQL\Support;

/**
 * Who is calling the single POST /graphql endpoint — mirrors the four
 * REST auth guards (Sanctum session, CustomerAccessToken bearer,
 * AppToken bearer, unauthenticated storefront) collapsed into one enum
 * since GraphQL has no separate route per guard to hang middleware off
 * (see GraphQLAuthenticator).
 */
enum GraphQLActorType: string
{
    case Guest = 'guest';
    case Customer = 'customer';
    case Merchant = 'merchant';
    case App = 'app';
}
