<template>
  <div>
    <PageHeader title="Search Rules &amp; Ranking" :breadcrumbs="[{ label: 'Search Dashboard', to: '/search' }, { label: 'Rules & Ranking' }]" description="Boost or hide a product for a specific search keyword (or every keyword, if left blank). Rows are shown in position order — the same order applied when two rules affect the same search." />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p v-if="error" class="error">{{ error }}</p>

      <section class="builder">
        <h2>New rule</h2>
        <form @submit.prevent="handleCreate">
          <label>
            Name
            <input v-model="form.name" type="text" required placeholder="Boost summer collection">
          </label>
          <label>
            Keyword (blank = every search)
            <input v-model="form.keyword" type="text" placeholder="summer">
          </label>
          <label>
            Action
            <select v-model="form.action">
              <option value="boost">Boost</option>
              <option value="hide">Hide</option>
            </select>
          </label>
          <label>
            Product ID
            <input v-model="form.product_id" type="text" required>
          </label>
          <label v-if="form.action === 'boost'">
            Boost amount
            <input v-model.number="form.boost_amount" type="number">
          </label>
          <label>
            Position
            <input v-model.number="form.position" type="number" min="0">
          </label>
          <div class="form-actions">
            <button type="submit" :disabled="saving">{{ saving ? 'Creating…' : 'Create rule' }}</button>
          </div>
        </form>
      </section>

      <p v-if="pending" class="hint">Loading…</p>

      <table v-else-if="rules.length">
        <thead>
          <tr>
            <th>Position</th>
            <th>Name</th>
            <th>Keyword</th>
            <th>Action</th>
            <th>Product</th>
            <th>Boost</th>
            <th>Active</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="rule in rules" :key="rule.id">
            <td>{{ rule.position }}</td>
            <td>{{ rule.name }}</td>
            <td>{{ rule.keyword ?? 'Any' }}</td>
            <td><span class="badge" :class="rule.action">{{ rule.action }}</span></td>
            <td>{{ rule.product_id }}</td>
            <td>{{ rule.boost_amount ?? '—' }}</td>
            <td>{{ rule.is_active ? 'Yes' : 'No' }}</td>
            <td class="row-actions">
              <button type="button" class="link" @click="toggleActive(rule)">{{ rule.is_active ? 'Disable' : 'Enable' }}</button>
              <button type="button" class="link danger" @click="handleDelete(rule)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else class="hint">No rules yet.</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { SearchRule, SearchRuleAction } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

definePageMeta({ layout: 'settings' })

const activeStore = useActiveStore()

const rules = ref<SearchRule[]>([])
const pending = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)

const form = reactive<{ name: string, keyword: string, action: SearchRuleAction, product_id: string, boost_amount: number | null, position: number }>({
  name: '',
  keyword: '',
  action: 'boost',
  product_id: '',
  boost_amount: null,
  position: 0,
})

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    rules.value = (await useApi().search.rules.list()).data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

async function handleCreate() {
  saving.value = true
  error.value = null
  try {
    const response = await useApi().search.rules.create({
      name: form.name,
      keyword: form.keyword || null,
      action: form.action,
      product_id: form.product_id,
      boost_amount: form.action === 'boost' ? form.boost_amount : null,
      position: form.position,
    })
    rules.value.push(response.data)
    form.name = ''
    form.keyword = ''
    form.product_id = ''
    form.boost_amount = null
    form.position = 0
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    saving.value = false
  }
}

async function toggleActive(rule: SearchRule) {
  error.value = null
  try {
    const response = await useApi().search.rules.update(rule.id, { is_active: !rule.is_active })
    const idx = rules.value.findIndex(r => r.id === rule.id)
    if (idx !== -1) rules.value[idx] = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

async function handleDelete(rule: SearchRule) {
  if (!confirm(`Delete rule "${rule.name}"?`)) return
  error.value = null
  try {
    await useApi().search.rules.remove(rule.id)
    rules.value = rules.value.filter(r => r.id !== rule.id)
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
.builder {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md, 0.5rem);
  padding: var(--space-4);
  margin-bottom: var(--space-6);
  max-width: 48rem;
}

.builder h2 {
  margin: 0 0 var(--space-2);
  font-size: var(--text-base);
}

.builder form {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  align-items: flex-end;
  margin-top: var(--space-2);
}

.builder label {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.builder input,
.builder select {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
}

.form-actions {
  display: flex;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  text-align: left;
  padding: var(--space-2);
  border-bottom: 1px solid var(--color-border);
}

.row-actions {
  display: flex;
  gap: var(--space-2);
}

.link {
  background: none;
  border: none;
  color: var(--color-text-muted);
  cursor: pointer;
  padding: 0;
  text-decoration: underline;
}

.link.danger {
  color: var(--color-danger, #c00);
}

.badge {
  display: inline-block;
  padding: 0.1rem 0.6rem;
  border-radius: 999px;
  background: var(--color-surface-muted, #f0f0f0);
  color: var(--color-text-muted);
  font-size: var(--text-xs);
}

.badge.boost {
  background: #e6f4ea;
  color: #1e7e34;
}

.badge.hide {
  background: #fdeaea;
  color: #b00020;
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
