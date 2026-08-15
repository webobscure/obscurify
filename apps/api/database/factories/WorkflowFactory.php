<?php

namespace Database\Factories;

use App\Domain\Automation\Enums\WorkflowStatus;
use App\Domain\Automation\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workflow>
 *
 * Deliberately has no `store_id` state: Workflow::creating() always
 * forces it from TenantContext. Produces a bare Workflow with no
 * version/trigger/conditions/actions attached — use CreateWorkflow
 * directly when a test needs a real, executable workflow.
 */
class WorkflowFactory extends Factory
{
    protected $model = Workflow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'status' => WorkflowStatus::Draft->value,
        ];
    }
}
