<template>
  <div class="builder">
    <div v-for="(action, index) in actions" :key="index" class="action">
      <div class="action-row">
        <span class="position">{{ index + 1 }}.</span>

        <select :value="action.type" @change="setType(index, ($event.target as HTMLSelectElement).value as WorkflowActionType)">
          <option v-for="t in ACTION_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
        </select>

        <div class="move">
          <button type="button" :disabled="index === 0" @click="move(index, -1)">↑</button>
          <button type="button" :disabled="index === actions.length - 1" @click="move(index, 1)">↓</button>
        </div>

        <button type="button" class="remove" @click="remove(index)">Remove</button>
      </div>

      <div class="config">
        <!-- Tag / group actions -->
        <template v-if="['add_customer_tag', 'remove_customer_tag'].includes(action.type)">
          <label>Tag ID<input type="text" :value="cfg(action, 'tag_id')" @input="setConfig(index, 'tag_id', ($event.target as HTMLInputElement).value)"></label>
        </template>
        <template v-else-if="['add_customer_to_group', 'remove_customer_from_group'].includes(action.type)">
          <label>Group ID<input type="text" :value="cfg(action, 'group_id')" @input="setConfig(index, 'group_id', ($event.target as HTMLInputElement).value)"></label>
        </template>

        <!-- Discounts -->
        <template v-else-if="action.type === 'create_discount_code'">
          <label>Promotion ID<input type="text" :value="cfg(action, 'promotion_id')" @input="setConfig(index, 'promotion_id', ($event.target as HTMLInputElement).value)"></label>
          <label>Code prefix<input type="text" :value="cfg(action, 'code_prefix')" @input="setConfig(index, 'code_prefix', ($event.target as HTMLInputElement).value)"></label>
          <label>Expires in (days)<input type="number" :value="cfg(action, 'expires_in_days')" @input="setConfigNumber(index, 'expires_in_days', ($event.target as HTMLInputElement).value)"></label>
        </template>
        <template v-else-if="action.type === 'expire_discount'">
          <label>Discount code ID<input type="text" :value="cfg(action, 'discount_code_id')" @input="setConfig(index, 'discount_code_id', ($event.target as HTMLInputElement).value)"></label>
        </template>

        <!-- Events / webhooks -->
        <template v-else-if="action.type === 'publish_event'">
          <label>Event type<input type="text" :value="cfg(action, 'event_type')" @input="setConfig(index, 'event_type', ($event.target as HTMLInputElement).value)"></label>
        </template>
        <template v-else-if="action.type === 'call_app_webhook'">
          <label>Target URL<input type="text" :value="cfg(action, 'target_url')" @input="setConfig(index, 'target_url', ($event.target as HTMLInputElement).value)"></label>
        </template>
        <template v-else-if="action.type === 'app_action'">
          <label>App extension ID<input type="text" :value="cfg(action, 'extension_id')" @input="setConfig(index, 'extension_id', ($event.target as HTMLInputElement).value)"></label>
        </template>

        <!-- Notifications / tasks -->
        <template v-else-if="['send_email_notification', 'send_sms_notification', 'send_push_notification', 'send_in_app_notification', 'send_webhook_notification'].includes(action.type)">
          <label>Template ID (optional)<input type="text" :value="cfg(action, 'template_id')" @input="setConfig(index, 'template_id', ($event.target as HTMLInputElement).value)"></label>
          <label v-if="action.type === 'send_email_notification'">Subject<input type="text" :value="cfg(action, 'subject')" @input="setConfig(index, 'subject', ($event.target as HTMLInputElement).value)"></label>
          <label>Body<input type="text" :value="cfg(action, 'body_text')" @input="setConfig(index, 'body_text', ($event.target as HTMLInputElement).value)"></label>
          <label>To (optional override — required for webhook)<input type="text" :value="cfg(action, 'to')" @input="setConfig(index, 'to', ($event.target as HTMLInputElement).value)"></label>
        </template>
        <template v-else-if="action.type === 'create_task'">
          <label>Title<input type="text" :value="cfg(action, 'title')" @input="setConfig(index, 'title', ($event.target as HTMLInputElement).value)"></label>
          <label>Description<input type="text" :value="cfg(action, 'description')" @input="setConfig(index, 'description', ($event.target as HTMLInputElement).value)"></label>
          <label>Due in (days)<input type="number" :value="cfg(action, 'due_in_days')" @input="setConfigNumber(index, 'due_in_days', ($event.target as HTMLInputElement).value)"></label>
        </template>

        <!-- Metadata -->
        <template v-else-if="['update_customer_metadata', 'update_order_metadata'].includes(action.type)">
          <label class="wide">
            Metadata (JSON object)
            <textarea :value="jsonCfg(action, 'metadata')" rows="2" @input="setConfigJson(index, 'metadata', ($event.target as HTMLTextAreaElement).value)" />
          </label>
        </template>

        <!-- Delay -->
        <template v-else-if="action.type === 'delay'">
          <label>Wait
            <select :value="cfg(action, 'delay_type') || 'minutes'" @change="setConfig(index, 'delay_type', ($event.target as HTMLSelectElement).value)">
              <option value="minutes">Minutes</option>
              <option value="hours">Hours</option>
              <option value="until_date">Until date</option>
              <option value="until_event">Until event</option>
            </select>
          </label>
          <label v-if="['minutes', 'hours'].includes(cfg(action, 'delay_type') || 'minutes')">
            Value
            <input type="number" :value="cfg(action, 'value')" @input="setConfigNumber(index, 'value', ($event.target as HTMLInputElement).value)">
          </label>
          <label v-else-if="cfg(action, 'delay_type') === 'until_date'">
            Until (ISO date/time)
            <input type="text" :value="cfg(action, 'until')" @input="setConfig(index, 'until', ($event.target as HTMLInputElement).value)">
          </label>
          <label v-else>
            Event type to wait for
            <input type="text" :value="cfg(action, 'event_type')" @input="setConfig(index, 'event_type', ($event.target as HTMLInputElement).value)">
          </label>
        </template>
      </div>
    </div>

    <p class="actions">
      <button type="button" @click="addAction">+ Action</button>
    </p>
  </div>
</template>

<script setup lang="ts">
import type { WorkflowActionInput, WorkflowActionType } from '@obscurify/types'

/**
 * A flat, ordered list — spec explicitly excludes branching (no visual
 * BPMN editor), so this is reorder/add/remove only, no nesting. Each
 * action type gets its own small labeled config form rather than a raw
 * JSON editor, mirroring how SegmentRuleBuilder favors typed inputs
 * over free-form JSON wherever the shape is known ahead of time; the
 * two metadata actions keep a JSON textarea since their config is
 * genuinely an open key/value bag by design.
 */
const props = defineProps<{ actions: WorkflowActionInput[] }>()
const emit = defineEmits<{ update: [actions: WorkflowActionInput[]] }>()

const ACTION_TYPES: Array<{ value: WorkflowActionType, label: string }> = [
  { value: 'add_customer_tag', label: 'Add customer tag' },
  { value: 'remove_customer_tag', label: 'Remove customer tag' },
  { value: 'add_customer_to_group', label: 'Add customer to group' },
  { value: 'remove_customer_from_group', label: 'Remove customer from group' },
  { value: 'create_discount_code', label: 'Create discount code' },
  { value: 'expire_discount', label: 'Expire discount' },
  { value: 'publish_event', label: 'Publish event' },
  { value: 'call_app_webhook', label: 'Call installed app webhook' },
  { value: 'send_email_notification', label: 'Send email notification' },
  { value: 'send_sms_notification', label: 'Send SMS notification' },
  { value: 'send_push_notification', label: 'Send push notification' },
  { value: 'send_in_app_notification', label: 'Send in-app notification' },
  { value: 'send_webhook_notification', label: 'Send webhook notification' },
  { value: 'update_customer_metadata', label: 'Update customer metadata' },
  { value: 'update_order_metadata', label: 'Update order metadata' },
  { value: 'create_task', label: 'Create task' },
  { value: 'delay', label: 'Delay execution' },
  { value: 'app_action', label: 'Custom app action' },
]

function cfg(action: WorkflowActionInput, key: string): string {
  const value = action.config?.[key]
  return value == null ? '' : String(value)
}

function jsonCfg(action: WorkflowActionInput, key: string): string {
  const value = action.config?.[key]
  return value == null ? '' : JSON.stringify(value)
}

function update(index: number, action: WorkflowActionInput) {
  const next = [...props.actions]
  next[index] = action
  emit('update', next)
}

function setType(index: number, type: WorkflowActionType) {
  update(index, { type, config: {} })
}

function setConfig(index: number, key: string, value: string) {
  const action = props.actions[index]!
  update(index, { ...action, config: { ...action.config, [key]: value } })
}

function setConfigNumber(index: number, key: string, value: string) {
  const action = props.actions[index]!
  const parsed = value === '' ? undefined : Number(value)
  update(index, { ...action, config: { ...action.config, [key]: parsed } })
}

function setConfigJson(index: number, key: string, raw: string) {
  const action = props.actions[index]!
  let parsed: unknown = {}
  try {
    parsed = raw.trim() === '' ? {} : JSON.parse(raw)
  } catch {
    // Leave the field editable — an invalid intermediate JSON string
    // while typing isn't an error state worth blocking on here.
    return
  }
  update(index, { ...action, config: { ...action.config, [key]: parsed } })
}

function move(index: number, delta: number) {
  const next = [...props.actions]
  const target = index + delta
  ;[next[index], next[target]] = [next[target]!, next[index]!]
  emit('update', next)
}

function remove(index: number) {
  emit('update', props.actions.filter((_, i) => i !== index))
}

function addAction() {
  emit('update', [...props.actions, { type: 'send_in_app_notification', config: {} }])
}
</script>

<style scoped>
.builder {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.action {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: var(--space-2);
}

.action-row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.position {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.action-row select {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
}

.move {
  display: flex;
  gap: var(--space-1);
}

.move button {
  padding: 0 var(--space-2);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: transparent;
  cursor: pointer;
}

.move button:disabled {
  opacity: 0.4;
  cursor: default;
}

.remove {
  margin-left: auto;
  background: transparent;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  padding: var(--space-1) var(--space-2);
  cursor: pointer;
}

.config {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
  margin-top: var(--space-2);
}

.config label {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.config label.wide {
  flex-basis: 100%;
}

.config input,
.config select,
.config textarea {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font: inherit;
}

.actions {
  display: flex;
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
