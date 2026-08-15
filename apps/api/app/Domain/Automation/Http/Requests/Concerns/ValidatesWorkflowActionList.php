<?php

namespace App\Domain\Automation\Http\Requests\Concerns;

use App\Domain\Automation\Enums\WorkflowActionType;
use Illuminate\Contracts\Validation\Validator;

/**
 * `actions` is a flat, ordered list (spec explicitly excludes branching
 * — see "No visual BPMN editor," spec section 16) — validated here
 * rather than declaratively since `type` determines which shape
 * `config` must have (see WorkflowActionExecutor), which Laravel's
 * `rules()` array can't express per-item.
 */
trait ValidatesWorkflowActionList
{
    protected function validateActionList(Validator $validator, string $inputKey = 'actions'): void
    {
        $actions = $this->input($inputKey);

        if (! is_array($actions)) {
            return;
        }

        foreach ($actions as $index => $action) {
            $path = "{$inputKey}.{$index}";

            if (! is_array($action)) {
                $validator->errors()->add($path, 'Each action must be an object.');

                continue;
            }

            if (! isset($action['type']) || ! in_array($action['type'], array_column(WorkflowActionType::cases(), 'value'), true)) {
                $validator->errors()->add("{$path}.type", 'Invalid action type.');
            }

            if (isset($action['config']) && ! is_array($action['config'])) {
                $validator->errors()->add("{$path}.config", 'config must be an object.');
            }
        }
    }
}
