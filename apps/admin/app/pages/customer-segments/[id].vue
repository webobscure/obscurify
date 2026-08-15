<template>
  <div>
    <PageHeader
      :title="segment?.name ?? 'Customer Segment'"
      :breadcrumbs="[{ label: 'Customer Segments', to: '/customer-segments' }, { label: segment?.name ?? '' }]"
    />

    <p v-if="error" class="error">{{ error }}</p>

    <template v-if="segment">
      <form class="details" @submit.prevent="handleSave">
        <label>
          Name
          <input v-model="form.name" type="text">
        </label>
        <label>
          Status
          <select v-model="form.status">
            <option value="active">Active</option>
            <option value="archived">Archived</option>
          </select>
        </label>

        <h2>Rules</h2>
        <p class="hint">Combined with AND at the top level. A segment with no rules matches nobody.</p>
        <SegmentRuleBuilder :nodes="form.rules" @update="form.rules = $event" />

        <div class="actions">
          <button type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</button>
          <button type="button" class="danger" :disabled="deleting" @click="handleDelete">
            {{ deleting ? 'Deleting…' : 'Delete segment' }}
          </button>
        </div>
      </form>
    </template>
    <p v-else-if="pending">Loading…</p>
  </div>
</template>

<script setup lang="ts">
import type { CustomerSegment, SegmentRuleInput } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
const segmentId = route.params.id as string

const segment = ref<CustomerSegment | null>(null)
const form = reactive<{ name: string, status: string, rules: SegmentRuleInput[] }>({ name: '', status: 'active', rules: [] })

const pending = ref(true)
const saving = ref(false)
const deleting = ref(false)
const error = ref<string | null>(null)

function toRuleInput(rule: { boolean_operator: string | null, field: string | null, operator: string | null, value: unknown, children: unknown[] }): SegmentRuleInput {
  if (rule.boolean_operator) {
    return { boolean_operator: rule.boolean_operator as SegmentRuleInput['boolean_operator'], children: (rule.children as typeof rule[]).map(toRuleInput) }
  }

  return { field: rule.field as SegmentRuleInput['field'], operator: rule.operator as SegmentRuleInput['operator'], value: rule.value }
}

async function load() {
  pending.value = true
  error.value = null
  try {
    const response = await useApi().customerSegments.get(segmentId)
    segment.value = response.data
    form.name = response.data.name
    form.status = response.data.status
    form.rules = (response.data.rules ?? []).map(toRuleInput)
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

async function handleSave() {
  saving.value = true
  error.value = null
  try {
    const response = await useApi().customerSegments.update(segmentId, { name: form.name, status: form.status, rules: form.rules })
    segment.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    saving.value = false
  }
}

async function handleDelete() {
  if (!confirm('Delete this customer segment?')) return
  deleting.value = true
  error.value = null
  try {
    await useApi().customerSegments.remove(segmentId)
    await navigateTo('/customer-segments')
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
    deleting.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.details {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  max-width: 48rem;
}

label {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  max-width: 20rem;
}

input,
select {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin: 0;
}

.actions {
  display: flex;
  gap: var(--space-2);
}

.actions button {
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.danger {
  background: transparent;
  border: 1px solid var(--color-danger, #c00);
  color: var(--color-danger, #c00);
}
</style>
