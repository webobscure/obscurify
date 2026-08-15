<?php

namespace App\Domain\Automation\Http\Controllers;

use App\Domain\Automation\Support\WorkflowTriggerRegistry;
use App\Domain\Automation\Support\WorkflowVariableRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Backs the Trigger Picker and the condition/action builders' variable
 * pickers (spec section 11) — merged built-in + app-contributed
 * catalogs, read-only.
 */
final class WorkflowCatalogController extends Controller
{
    public function variables(WorkflowVariableRegistry $registry): JsonResponse
    {
        return response()->json(['data' => $registry->all()->values()]);
    }

    public function triggers(WorkflowTriggerRegistry $registry): JsonResponse
    {
        return response()->json(['data' => $registry->all()->values()]);
    }
}
