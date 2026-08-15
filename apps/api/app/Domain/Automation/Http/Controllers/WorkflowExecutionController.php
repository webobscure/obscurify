<?php

namespace App\Domain\Automation\Http\Controllers;

use App\Domain\Automation\Http\Resources\WorkflowExecutionResource;
use App\Domain\Automation\Models\WorkflowExecution;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Execution History / Execution Logs (spec section 11) — read-only;
 * executions and their steps are only ever written by WorkflowRunner.
 */
final class WorkflowExecutionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = WorkflowExecution::query()->orderByDesc('created_at');

        if ($request->filled('workflow_id')) {
            $query->where('workflow_id', $request->string('workflow_id')->toString());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return WorkflowExecutionResource::collection($query->paginate(25));
    }

    public function show(WorkflowExecution $execution): WorkflowExecutionResource
    {
        $execution->load('steps');

        return new WorkflowExecutionResource($execution);
    }
}
