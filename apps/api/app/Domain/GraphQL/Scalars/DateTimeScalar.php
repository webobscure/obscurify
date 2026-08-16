<?php

namespace App\Domain\GraphQL\Scalars;

use Carbon\CarbonInterface;
use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use Illuminate\Support\Carbon;

/**
 * ISO-8601 datetime scalar — every timestamp field in this schema
 * (created_at, occurred_at, ...) uses this rather than a plain String,
 * so a schema-aware client knows to parse it as a date rather than
 * display it verbatim.
 */
final class DateTimeScalar extends ScalarType
{
    public string $name = 'DateTime';

    public ?string $description = 'An ISO-8601 encoded datetime string.';

    public function serialize(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        if (is_string($value)) {
            return Carbon::parse($value)->toIso8601String();
        }

        throw new Error('DateTime scalar can only serialize a Carbon instance or a date string.');
    }

    public function parseValue(mixed $value): Carbon
    {
        if (! is_string($value)) {
            throw new Error('DateTime scalar can only parse a string.');
        }

        return Carbon::parse($value);
    }

    public function parseLiteral(Node $valueNode, ?array $variables = null): Carbon
    {
        if (! $valueNode instanceof StringValueNode) {
            throw new Error('DateTime scalar can only parse string literals.', [$valueNode]);
        }

        return Carbon::parse($valueNode->value);
    }
}
