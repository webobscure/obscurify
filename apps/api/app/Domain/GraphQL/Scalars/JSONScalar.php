<?php

namespace App\Domain\GraphQL\Scalars;

use GraphQL\Language\AST\Node;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\AST;

/**
 * A passthrough scalar for genuinely schemaless payloads — facet
 * counts (spec section 3's Search query), an App's `config` blob,
 * anything already stored as a free-form jsonb column with no fixed
 * shape a typed object could usefully model.
 */
final class JSONScalar extends ScalarType
{
    public string $name = 'JSON';

    public ?string $description = 'An arbitrary JSON value — used only where the shape is genuinely schemaless.';

    public function serialize(mixed $value): mixed
    {
        return $value;
    }

    public function parseValue(mixed $value): mixed
    {
        return $value;
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): mixed
    {
        return AST::valueFromASTUntyped($valueNode, $variables);
    }
}
