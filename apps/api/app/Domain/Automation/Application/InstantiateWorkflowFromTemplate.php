<?php

namespace App\Domain\Automation\Application;

use App\Domain\Automation\Models\Workflow;
use App\Domain\Automation\Models\WorkflowTemplate;

/**
 * Turns a WorkflowTemplate's portable `definition` blob
 * ({trigger, conditions, actions}) into a real, store-owned Workflow —
 * always created as a draft, never auto-published, so a merchant can
 * review/adjust template-supplied ids (a promotion_id in a
 * CreateDiscountCode action, a tag_id, ...) before it goes live.
 */
final class InstantiateWorkflowFromTemplate
{
    public function __construct(private readonly CreateWorkflow $createWorkflow) {}

    public function handle(WorkflowTemplate $template, ?string $name = null): Workflow
    {
        $definition = $template->definition;

        return $this->createWorkflow->handle([
            'name' => $name ?? $template->name,
            'description' => $template->description,
            'trigger' => $definition['trigger'] ?? null,
            'conditions' => $definition['conditions'] ?? [],
            'actions' => $definition['actions'] ?? [],
        ]);
    }
}
