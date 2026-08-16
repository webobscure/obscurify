<?php

namespace App\Domain\GraphQL\Types;

use App\Domain\Analytics\Models\Report;
use App\Domain\GraphQL\Support\TypeRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * Merchant-only (spec section 3: "Analytics (merchant only)") — mirrors
 * ReportController::store exactly, calling the same RunReport service.
 * `result`/`filters` stay JSON: Report.result is a list of arbitrary
 * column-keyed rows shaped by whatever `columns` the caller requested,
 * the same reason SearchResult.facets stays JSON (see SearchTypes).
 */
final class AnalyticsTypes
{
    public static function report(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'AnalyticsReport',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'reportType' => Type::nonNull(Type::string()),
                'status' => Type::nonNull(Type::string()),
                'columns' => Type::listOf(Type::string()),
                'result' => $types->get('JSON'),
                'rowCount' => Type::int(),
                'errorMessage' => Type::string(),
                'generatedAt' => $types->get('DateTime'),
            ],
            'resolveField' => function (Report $report, array $args, mixed $context, ResolveInfo $info) {
                return match ($info->fieldName) {
                    'id' => $report->id,
                    'reportType' => $report->report_type->value,
                    'status' => $report->status->value,
                    'columns' => $report->columns,
                    'result' => $report->result,
                    'rowCount' => $report->row_count,
                    'errorMessage' => $report->error_message,
                    'generatedAt' => $report->generated_at,
                    default => null,
                };
            },
        ]);
    }
}
