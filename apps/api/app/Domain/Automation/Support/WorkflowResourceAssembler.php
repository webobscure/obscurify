<?php

namespace App\Domain\Automation\Support;

use App\Domain\Automation\Http\Resources\WorkflowResource;
use App\Domain\Automation\Models\Workflow;
use App\Domain\Automation\Models\WorkflowVersion;

/**
 * Attaches a fully-loaded WorkflowVersion (trigger/conditions/actions)
 * onto a Workflow as its virtual `version` relation before serializing
 * — the one assembly step every controller action that returns a full
 * WorkflowResource needs (create, show, update, publish, rollback,
 * template instantiate), so it lives here once rather than being
 * re-implemented per controller.
 */
final class WorkflowResourceAssembler
{
    public function withVersion(Workflow $workflow, ?WorkflowVersion $version): WorkflowResource
    {
        if ($version !== null) {
            $version->loadMissing(['trigger', 'actions']);
            WorkflowConditionTreeLoader::load($version->rootConditions);
        }

        $workflow->setRelation('version', $version);

        return new WorkflowResource($workflow);
    }
}
