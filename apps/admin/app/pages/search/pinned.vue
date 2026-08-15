<template>
  <div>
    <PageHeader title="Pinned Search Results" :breadcrumbs="[{ label: 'Search Dashboard', to: '/search' }, { label: 'Pinned Products' }]" description="A pinned product always shows first for its exact keyword, regardless of relevance score or any boost/hide rule." />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p v-if="error" class="error">{{ error }}</p>

      <section class="builder">
        <h2>New pinned result</h2>
        <form @submit.prevent="handleCreate">
          <label>
            Keyword
            <input v-model="form.keyword" type="text" required placeholder="shoes">
          </label>
          <label>
            Product ID
            <input v-model="form.product_id" type="text" required>
          </label>
          <label>
            Position
            <input v-model.number="form.position" type="number" min="0">
          </label>
          <div class="form-actions">
            <button type="submit" :disabled="saving">{{ saving ? 'Creating…' : 'Pin product' }}</button>
          </div>
        </form>
      </section>

      <p v-if="pending" class="hint">Loading…</p>

      <table v-else-if="pinnedResults.length">
        <thead>
          <tr>
            <th>Position</th>
            <th>Keyword</th>
            <th>Product</th>
            <th>Active</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="pin in pinnedResults" :key="pin.id">
            <td>{{ pin.position }}</td>
            <td>{{ pin.keyword }}</td>
            <td>{{ pin.product_id }}</td>
            <td>{{ pin.is_active ? 'Yes' : 'No' }}</td>
            <td class="row-actions">
              <button type="button" class="link" @click="toggleActive(pin)">{{ pin.is_active ? 'Disable' : 'Enable' }}</button>
              <button type="button" class="link danger" @click="handleDelete(pin)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else class="hint">No pinned results yet.</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { PinnedSearchResult } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const activeStore = useActiveStore()

const pinnedResults = ref<PinnedSearchResult[]>([])
const pending = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)

const form = reactive<{ keyword: string, product_id: string, position: number }>({
  keyword: '',
  product_id: '',
  position: 0,
})

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    pinnedResults.value = (await useApi().search.pinnedResults.list()).data
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
    const response = await useApi().search.pinnedResults.create({
      keyword: form.keyword,
      product_id: form.product_id,
      position: form.position,
    })
    pinnedResults.value.push(response.data)
    form.keyword = ''
    form.product_id = ''
    form.position = 0
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    saving.value = false
  }
}

async function toggleActive(pin: PinnedSearchResult) {
  error.value = null
  try {
    const response = await useApi().search.pinnedResults.update(pin.id, { is_active: !pin.is_active })
    const idx = pinnedResults.value.findIndex(p => p.id === pin.id)
    if (idx !== -1) pinnedResults.value[idx] = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

async function handleDelete(pin: PinnedSearchResult) {
  if (!confirm(`Unpin product "${pin.product_id}" for keyword "${pin.keyword}"?`)) return
  error.value = null
  try {
    await useApi().search.pinnedResults.remove(pin.id)
    pinnedResults.value = pinnedResults.value.filter(p => p.id !== pin.id)
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
  max-width: 40rem;
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

.builder input {
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

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
