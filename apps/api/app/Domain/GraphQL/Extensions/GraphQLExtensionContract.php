<?php

namespace App\Domain\GraphQL\Extensions;

use App\Domain\GraphQL\Support\TypeRegistry;

/**
 * Spec section 8: "Apps SDK can register Queries, Mutations, Types,
 * Scalars, Directives." An extension is a same-process PHP class (see
 * docs/adr/029-graphql-platform.md for why a truly dynamic,
 * app-uploaded-at-runtime schema is out of scope — no sandboxed code
 * execution engine exists anywhere in this platform, and "Do NOT
 * introduce Apollo Federation" rules out remote schema stitching as the
 * alternative). Custom scalars/directives are registered the same way
 * as any other named type — through `types()` — since webonyx treats
 * `ScalarType` and `Directive` as ordinary values a factory can return.
 */
interface GraphQLExtensionContract
{
    /**
     * @return array<string, array<string, mixed>> field name => webonyx field config
     */
    public function queries(): array;

    /**
     * @return array<string, array<string, mixed>> field name => webonyx field config
     */
    public function mutations(): array;

    /**
     * @return array<string, callable(TypeRegistry): mixed> type name => lazy factory
     */
    public function types(): array;
}
