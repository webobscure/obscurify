<template>
  <div>
    <PageHeader
      :title="group?.name ?? 'Customer Group'"
      :breadcrumbs="[{ label: 'Customer Groups', to: '/customer-groups' }, { label: group?.name ?? '' }]"
    />

    <p v-if="error" class="error">{{ error }}</p>

    <template v-if="group">
      <section>
        <h2>Details</h2>
        <form class="details" @submit.prevent="handleSave">
          <label>
            Name
            <input v-model="form.name" type="text" :disabled="group.type === 'protected'">
          </label>
          <label>
            Status
            <select v-model="form.status">
              <option value="active">Active</option>
              <option value="archived">Archived</option>
            </select>
          </label>
          <p class="muted">Type: {{ group.type }}{{ group.type === 'protected' ? ' — cannot be renamed or deleted' : '' }}</p>

          <template v-if="group.type !== 'manual'">
            <h3>Rules ({{ group.type }} — computed automatically)</h3>
            <SegmentRuleBuilder :nodes="form.rules" @update="form.rules = $event" />
          </template>

          <div class="actions">
            <button type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</button>
            <button type="button" class="danger" :disabled="group.type === 'protected' || deleting" @click="handleDelete">
              {{ deleting ? 'Deleting…' : 'Delete group' }}
            </button>
          </div>
        </form>
      </section>

      <section v-if="group.type === 'manual'">
        <h2>Members ({{ members.length }})</h2>
        <form class="add-member" @submit.prevent="handleAddMember">
          <input v-model="newMemberId" type="text" placeholder="Customer ID">
          <button type="submit" :disabled="addingMember">Add</button>
        </form>
        <ul v-if="members.length">
          <li v-for="member in members" :key="member.id">
            {{ member.email ?? member.id }}
            <button type="button" @click="handleRemoveMember(member.id)">Remove</button>
          </li>
        </ul>
        <p v-else class="muted">No members yet.</p>
      </section>
    </template>
    <p v-else-if="pending">Loading…</p>
  </div>
</template>

<script setup lang="ts">
import type { Customer, CustomerGroup, SegmentRuleInput } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
const groupId = route.params.id as string

const group = ref<CustomerGroup | null>(null)
const members = ref<Customer[]>([])
const form = reactive<{ name: string, status: string, rules: SegmentRuleInput[] }>({ name: '', status: 'active', rules: [] })
const newMemberId = ref('')

const pending = ref(true)
const saving = ref(false)
const deleting = ref(false)
const addingMember = ref(false)
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
    const response = await useApi().customerGroups.get(groupId)
    group.value = response.data
    form.name = response.data.name
    form.status = response.data.status
    form.rules = (response.data.rules ?? []).map(toRuleInput)

    if (response.data.type === 'manual') {
      // No dedicated "list this group's members" endpoint exists — the
      // admin customer search endpoint's own group_id filter (spec
      // section 10) already returns exactly this roster as full Customer
      // records, so this reuses it rather than adding a duplicate one.
      members.value = (await useApi().customers.list({ group_id: groupId })).data
    }
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
    const response = await useApi().customerGroups.update(groupId, {
      name: form.name,
      status: form.status,
      ...(group.value?.type !== 'manual' ? { rules: form.rules } : {}),
    })
    group.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    saving.value = false
  }
}

async function handleDelete() {
  if (!confirm('Delete this customer group?')) return
  deleting.value = true
  error.value = null
  try {
    await useApi().customerGroups.remove(groupId)
    await navigateTo('/customer-groups')
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
    deleting.value = false
  }
}

async function handleAddMember() {
  if (!newMemberId.value) return
  addingMember.value = true
  error.value = null
  try {
    await useApi().customerGroups.addMember(groupId, newMemberId.value)
    const customer = await useApi().customers.get(newMemberId.value)
    members.value.push(customer.data)
    newMemberId.value = ''
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    addingMember.value = false
  }
}

async function handleRemoveMember(customerId: string) {
  error.value = null
  try {
    await useApi().customerGroups.removeMember(groupId, customerId)
    members.value = members.value.filter(m => m.id !== customerId)
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

onMounted(load)
</script>

<style scoped>
section {
  margin-top: var(--space-4);
}

.details {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  max-width: 40rem;
}

label {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

input,
select {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
}

.muted {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
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

.add-member {
  display: flex;
  gap: var(--space-2);
  margin: var(--space-2) 0;
}

ul {
  list-style: none;
  margin: 0;
  padding: 0;
}

li {
  display: flex;
  justify-content: space-between;
  padding: var(--space-1) 0;
  border-bottom: 1px solid var(--color-border);
}
</style>
