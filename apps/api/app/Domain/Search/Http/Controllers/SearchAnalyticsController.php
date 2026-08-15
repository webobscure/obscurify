<?php

namespace App\Domain\Search\Http\Controllers;

use App\Domain\Search\Support\SearchAnalyticsSummary;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class SearchAnalyticsController extends Controller
{
    public function show(Request $request, TenantContext $tenantContext, SearchAnalyticsSummary $summary): JsonResponse
    {
        $from = $request->filled('from') ? Carbon::parse($request->string('from')->toString()) : now()->subDays(30)->startOfDay();
        $to = $request->filled('to') ? Carbon::parse($request->string('to')->toString()) : now()->endOfDay();

        return response()->json(['data' => $summary->build($tenantContext->storeId(), $from, $to)]);
    }
}
