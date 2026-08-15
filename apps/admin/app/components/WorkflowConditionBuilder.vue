<template>
  <div class="builder">
    <div v-for="(node, index) in nodes" :key="index" class="node">
      <div class="node-row">
        <select :value="nodeKind(node)" @change="setKind(index, ($event.target as HTMLSelectElement).value as 'condition' | 'group')">
          <option value="condition">Condition</option>
          <option value="group">Group (AND/OR)</option>
        </select>

        <template v-if="nodeKind(node) === 'condition'">
          <input
            list="workflow-variable-keys"
            :value="node.variable_key"
            type="text"
            placeholder="Variable (e.g. order.total_amount)"
            @input="update(index, { ...node, variable_key: ($event.target as HTMLInputElement).value })"
          >

          <select :value="node.operator" @change="update(index, { ...node, operator: ($event.target as HTMLSelectElement).value as WorkflowConditionOperator })">
            <option value="" disabled>Operator…</option>
            <option v-for="o in OPERATORS" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>

          <input
            v-if="!['is_true', 'is_false'].includes(node.operator ?? '')"
            :value="valueAsString(node.value)"
            type="text"
            placeholder="Value (e.g. 500 or SE,NO or a segment/group id or tag slug)"
            @input="update(index, { ...node, value: parseValue(($event.target as HTMLInputElement).value) })"
          >
        </template>

        <template v-else>
          <select :value="node.boolean_operator" @change="update(index, { ...node, boolean_operator: ($event.target as HTMLSelectElement).value as WorkflowConditionBoolean })">
            <option value="and">AND</option>
            <option value="or">OR</option>
          </select>
          <span class="hint">— all children below combined with this operator</span>
        </template>

        <button type="button" class="remove" @click="remove(index)">Remove</button>
      </div>

      <div v-if="nodeKind(node) === 'group'" class="children">
        <WorkflowConditionBuilder :nodes="node.children ?? []" @update="setChildren(index, $event)" />
      </div>
    </div>

    <p class="actions">
      <button type="button" @click="addCondition">+ Condition</button>
      <button type="button" @click="addGroup">+ Group</button>
    </p>
  </div>
</template>

<script setup lang="ts">
import type { WorkflowConditionBoolean, WorkflowConditionInput, WorkflowConditionOperator } from '@obscurify/types'

/**
 * Recursive by nature, same self-referencing-SFC pattern as
 * SegmentRuleBuilder (M18) and MenuItemTree before it. `variable_key` is
 * free text with a `<datalist>` assist (`#workflow-variable-keys`,
 * populated once by the page that owns this tree from GET
 * /automation/variables) rather than a closed `<select>`, since app-
 * contributed variables aren't known ahead of time — see
 * docs/architecture/automation.md §7. Purely presentational: every
 * mutation produces a new array and emits it up, exactly like
 * SegmentRuleBuilder.
 */
const props = defineProps<{ nodes: WorkflowConditionInput[] }>()
const emit = defineEmits<{ update: [nodes: WorkflowConditionInput[]] }>()

const OPERATORS: Array<{ value: WorkflowConditionOperator, label: string }> = [
  { value: 'equals', label: 'equals' },
  { value: 'not_equals', label: 'not equals' },
  { value: 'greater_than', label: '>' },
  { value: 'greater_than_or_equal', label: '>=' },
  { value: 'less_than', label: '<' },
  { value: 'less_than_or_equal', label: '<=' },
  { value: 'contains', label: 'contains' },
  { value: 'starts_with', label: 'starts with' },
  { value: 'ends_with', label: 'ends with' },
  { value: 'is_true', label: 'is true' },
  { value: 'is_false', label: 'is false' },
  { value: 'in_set', label: 'in (comma-separated)' },
  { value: 'not_in_set', label: 'not in (comma-separated)' },
  { value: 'in_segment', label: 'customer in segment (id)' },
  { value: 'not_in_segment', label: 'customer not in segment (id)' },
  { value: 'in_group', label: 'customer in group (id)' },
  { value: 'not_in_group', label: 'customer not in group (id)' },
  { value: 'has_tag', label: 'customer has tag (slug)' },
  { value: 'not_has_tag', label: 'customer does not have tag (slug)' },
]

function nodeKind(node: WorkflowConditionInput): 'condition' | 'group' {
  return node.boolean_operator ? 'group' : 'condition'
}

function valueAsString(value: unknown): string {
  if (value == null) return ''
  return Array.isArray(value) ? value.join(',') : String(value)
}

/** Comma-separated -> string[]; numeric strings -> number; everything else stays a string. */
function parseValue(raw: string): unknown {
  if (raw.includes(',')) return raw.split(',').map(v => v.trim()).filter(Boolean)
  if (raw.trim() !== '' && !Number.isNaN(Number(raw))) return Number(raw)
  return raw
}

function update(index: number, node: WorkflowConditionInput) {
  const next = [...props.nodes]
  next[index] = node
  emit('update', next)
}

function setKind(index: number, kind: 'condition' | 'group') {
  update(index, kind === 'group' ? { boolean_operator: 'and', children: [] } : { variable_key: undefined, operator: undefined, value: undefined })
}

function setChildren(index: number, children: WorkflowConditionInput[]) {
  update(index, { ...props.nodes[index], children })
}

function remove(index: number) {
  emit('update', props.nodes.filter((_, i) => i !== index))
}

function addCondition() {
  emit('update', [...props.nodes, { variable_key: undefined, operator: undefined, value: undefined }])
}

function addGroup() {
  emit('update', [...props.nodes, { boolean_operator: 'and', children: [] }])
}
</script>

<style scoped>
.builder {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.node {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: var(--space-2);
}

.node-row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-wrap: wrap;
}

.node-row select,
.node-row input {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.remove {
  margin-left: auto;
  background: transparent;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  padding: var(--space-1) var(--space-2);
  cursor: pointer;
}

.children {
  margin-top: var(--space-2);
  margin-left: var(--space-4);
  padding-left: var(--space-3);
  border-left: 1px solid var(--color-border);
}

.actions {
  display: flex;
  gap: var(--space-2);
  margin: 0;
}

.actions button {
  padding: var(--space-1) var(--space-3);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: transparent;
  cursor: pointer;
}
</style>
