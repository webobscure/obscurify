<?php

namespace App\Domain\Automation\Http\Controllers;

use App\Domain\Automation\Application\InstantiateWorkflowFromTemplate;
use App\Domain\Automation\Http\Requests\InstantiateWorkflowTemplateRequest;
use App\Domain\Automation\Http\Resources\WorkflowTemplateResource;
use App\Domain\Automation\Models\WorkflowTemplate;
use App\Domain\Automation\Support\WorkflowResourceAssembler;
use App\Domain\Automation\Support\WorkflowVersioning;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class WorkflowTemplateController extends Controller
{
    public function __construct(
        private readonly WorkflowVersioning $workflowVersioning,
        private readonly WorkflowResourceAssembler $assembler,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return WorkflowTemplateResource::collection(WorkflowTemplate::query()->orderBy('category')->orderBy('name')->get());
    }

    public function instantiate(InstantiateWorkflowTemplateRequest $request, WorkflowTemplate $template, InstantiateWorkflowFromTemplate $action): JsonResponse
    {
        $workflow = $action->handle($template, $request->validated('name'));

        return $this->assembler->withVersion($workflow, $this->workflowVersioning->editableVersion($workflow))
            ->response()
            ->setStatusCode(201);
    }
}
