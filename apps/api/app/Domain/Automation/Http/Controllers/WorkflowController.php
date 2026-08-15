<?php

namespace App\Domain\Automation\Http\Controllers;

use App\Domain\Automation\Application\ArchiveWorkflow;
use App\Domain\Automation\Application\CreateWorkflow;
use App\Domain\Automation\Application\DisableWorkflow;
use App\Domain\Automation\Application\EnableWorkflow;
use App\Domain\Automation\Application\PublishWorkflow;
use App\Domain\Automation\Application\RollbackWorkflow;
use App\Domain\Automation\Application\UpdateWorkflow;
use App\Domain\Automation\Http\Requests\RollbackWorkflowRequest;
use App\Domain\Automation\Http\Requests\StoreWorkflowRequest;
use App\Domain\Automation\Http\Requests\UpdateWorkflowRequest;
use App\Domain\Automation\Http\Resources\WorkflowResource;
use App\Domain\Automation\Http\Resources\WorkflowVersionResource;
use App\Domain\Automation\Models\Workflow;
use App\Domain\Automation\Models\WorkflowVersion;
use App\Domain\Automation\Support\WorkflowResourceAssembler;
use App\Domain\Automation\Support\WorkflowVersioning;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class WorkflowController extends Controller
{
    public function __construct(
        private readonly WorkflowVersioning $workflowVersioning,
        private readonly WorkflowResourceAssembler $assembler,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $workflows = Workflow::query()->orderByDesc('created_at')->get();

        return WorkflowResource::collection($workflows);
    }

    public function store(StoreWorkflowRequest $request, CreateWorkflow $action): JsonResponse
    {
        $workflow = $action->handle($request->validated());

        return $this->assembler->withVersion($workflow, $this->workflowVersioning->editableVersion($workflow))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Workflow $workflow): WorkflowResource
    {
        return $this->assembler->withVersion($workflow, $this->workflowVersioning->editableVersion($workflow));
    }

    public function update(UpdateWorkflowRequest $request, Workflow $workflow, UpdateWorkflow $action): WorkflowResource
    {
        $workflow = $action->handle($workflow, $request->validated());

        return $this->assembler->withVersion($workflow, $this->workflowVersioning->editableVersion($workflow));
    }

    public function versions(Workflow $workflow): AnonymousResourceCollection
    {
        return WorkflowVersionResource::collection($workflow->versions()->with('trigger')->get());
    }

    public function publish(Workflow $workflow, PublishWorkflow $action): WorkflowResource
    {
        $workflow = $action->handle($workflow);

        return $this->assembler->withVersion($workflow, $workflow->publishedVersion);
    }

    public function disable(Workflow $workflow, DisableWorkflow $action): WorkflowResource
    {
        return new WorkflowResource($action->handle($workflow));
    }

    public function enable(Workflow $workflow, EnableWorkflow $action): WorkflowResource
    {
        return new WorkflowResource($action->handle($workflow));
    }

    public function archive(Workflow $workflow, ArchiveWorkflow $action): WorkflowResource
    {
        return new WorkflowResource($action->handle($workflow));
    }

    public function rollback(RollbackWorkflowRequest $request, Workflow $workflow, RollbackWorkflow $action): WorkflowResource
    {
        $target = WorkflowVersion::query()->findOrFail($request->validated('version_id'));
        $workflow = $action->handle($workflow, $target);

        return $this->assembler->withVersion($workflow, $workflow->publishedVersion);
    }
}
