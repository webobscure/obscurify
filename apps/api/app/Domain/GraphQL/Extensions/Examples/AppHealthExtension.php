<?php

namespace App\Domain\GraphQL\Extensions\Examples;

use App\Domain\GraphQL\Exceptions\GraphQLUserError;
use App\Domain\GraphQL\Extensions\GraphQLExtensionContract;
use App\Domain\GraphQL\Support\GraphQLContext;
use App\Domain\GraphQL\Support\TypeRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * A minimal, real, end-to-end demonstration that the extension
 * mechanism actually works (spec section 8) — not a stub. Registers one
 * query field (`appHealth`), one type (`AppHealthStatus`), gated to the
 * App actor, so an installed app can verify its own token/connectivity
 * against the public GraphQL API the same way it would test a webhook
 * endpoint. A real third-party extension follows this exact shape:
 * implement GraphQLExtensionContract, register the instance in
 * GraphQLServiceProvider (see RegisterGraphQLExtensions).
 */
final class AppHealthExtension implements GraphQLExtensionContract
{
    public function queries(): array
    {
        return [
            'appHealth' => [
                'type' => Type::nonNull($this->typeRegistry()->get('AppHealthStatus')),
                'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                    if (! $context->isApp()) {
                        throw GraphQLUserError::forbidden('appHealth is only callable with an app token.');
                    }

                    $app = $context->installedApp?->app;

                    return [
                        'status' => 'ok',
                        'appName' => $app !== null ? $app->name : 'unknown',
                        'checkedAt' => now(),
                    ];
                },
            ],
        ];
    }

    public function mutations(): array
    {
        return [];
    }

    public function types(): array
    {
        return [
            'AppHealthStatus' => fn (TypeRegistry $types) => new ObjectType([
                'name' => 'AppHealthStatus',
                'fields' => [
                    'status' => Type::nonNull(Type::string()),
                    'appName' => Type::nonNull(Type::string()),
                    'checkedAt' => $types->get('DateTime'),
                ],
            ]),
        ];
    }

    private function typeRegistry(): TypeRegistry
    {
        return app(TypeRegistry::class);
    }
}
