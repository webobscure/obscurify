<?php

namespace App\Domain\Automation\Http\Requests\Concerns;

use App\Domain\Automation\Enums\WorkflowConditionBoolean;
use App\Domain\Automation\Enums\WorkflowConditionOperator;
use Illuminate\Contracts\Validation\Validator;

/**
 * The `conditions` array is an arbitrarily-nested tree (spec section 4),
 * validated recursively here — same shape and same reasoning as M18's
 * ValidatesSegmentRuleTree. Each node is either a group node
 * (`boolean_operator` + `children`) or a condition node (`variable_key`
 * + `operator`) — never both, never neither. `variable_key` is a
 * free-form dot path (it can reference an app-contributed variable, not
 * just the built-in catalog), so only non-emptiness is checked here.
 */
trait ValidatesWorkflowConditionTree
{
    protected function validateConditionTree(Validator $validator, string $inputKey = 'conditions'): void
    {
        $nodes = $this->input($inputKey);

        if (! is_array($nodes)) {
            return;
        }

        $this->validateNodes($validator, $inputKey, $nodes);
    }

    /**
     * @param  array<int|string, mixed>  $nodes
     */
    private function validateNodes(Validator $validator, string $path, array $nodes): void
    {
        foreach ($nodes as $index => $node) {
            $nodePath = "{$path}.{$index}";

            if (! is_array($node)) {
                $validator->errors()->add($nodePath, 'Each condition must be an object.');

                continue;
            }

            $isGroup = array_key_exists('boolean_operator', $node) && $node['boolean_operator'] !== null;
            $isCondition = array_key_exists('variable_key', $node) && $node['variable_key'] !== null;

            if ($isGroup === $isCondition) {
                $validator->errors()->add($nodePath, 'Each condition must be either a group (boolean_operator + children) or a condition (variable_key + operator), not both or neither.');

                continue;
            }

            if ($isGroup) {
                if (! in_array($node['boolean_operator'], array_column(WorkflowConditionBoolean::cases(), 'value'), true)) {
                    $validator->errors()->add("{$nodePath}.boolean_operator", 'Invalid boolean_operator.');
                }

                $children = $node['children'] ?? [];

                if (! is_array($children) || $children === []) {
                    $validator->errors()->add("{$nodePath}.children", 'A group condition must have at least one child.');

                    continue;
                }

                $this->validateNodes($validator, "{$nodePath}.children", $children);

                continue;
            }

            if (! is_string($node['variable_key']) || $node['variable_key'] === '') {
                $validator->errors()->add("{$nodePath}.variable_key", 'Invalid variable_key.');
            }

            if (! isset($node['operator']) || ! in_array($node['operator'], array_column(WorkflowConditionOperator::cases(), 'value'), true)) {
                $validator->errors()->add("{$nodePath}.operator", 'Invalid operator.');
            }
        }
    }
}
