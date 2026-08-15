<template>
  <div>
    <PageHeader title="Search Synonyms" :breadcrumbs="[{ label: 'Search Dashboard', to: '/search' }, { label: 'Synonyms' }]" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p v-if="error" class="error">{{ error }}</p>

      <section class="builder">
        <h2>New synonym</h2>
        <p class="hint">"tv" with synonyms "television" means a search for "tv" also matches "television". Bidirectional also makes a search for "television" match "tv".</p>
        <form @submit.prevent="handleCreate">
          <label>
            Term
            <input v-model="form.term" type="text" required placeholder="tv">
          </label>
          <label class="wide">
            Synonyms (comma-separated)
            <input v-model="form.synonymsText" type="text" required placeholder="television, telly">
          </label>
          <label class="checkbox">
            <input v-model="form.is_bidirectional" type="checkbox">
            Bidirectional
          </label>
          <div class="form-actions">
            <button type="submit" :disabled="saving">{{ saving ? 'Creating…' : 'Create synonym' }}</button>
          </div>
        </form>
      </section>

      <p v-if="pending" class="hint">Loading…</p>

      <table v-else-if="synonyms.length">
        <thead>
          <tr>
            <th>Term</th>
            <th>Synonyms</th>
            <th>Bidirectional</th>
            <th>Active</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="synonym in synonyms" :key="synonym.id">
            <td>{{ synonym.term }}</td>
            <td>{{ synonym.synonyms.join(', ') }}</td>
            <td>{{ synonym.is_bidirectional ? 'Yes' : 'No' }}</td>
            <td>{{ synonym.is_active ? 'Yes' : 'No' }}</td>
            <td class="row-actions">
              <button type="button" class="link" @click="toggleActive(synonym)">{{ synonym.is_active ? 'Disable' : 'Enable' }}</button>
              <button type="button" class="link danger" @click="handleDelete(synonym)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else class="hint">No synonyms yet.</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { SearchSynonym } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const activeStore = useActiveStore()

const synonyms = ref<SearchSynonym[]>([])
const pending = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)

const form = reactive<{ term: string, synonymsText: string, is_bidirectional: boolean }>({
  term: '',
  synonymsText: '',
  is_bidirectional: false,
})

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    synonyms.value = (await useApi().search.synonyms.list()).data
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
    const response = await useApi().search.synonyms.create({
      term: form.term,
      synonyms: form.synonymsText.split(',').map(s => s.trim()).filter(Boolean),
      is_bidirectional: form.is_bidirectional,
    })
    synonyms.value.push(response.data)
    form.term = ''
    form.synonymsText = ''
    form.is_bidirectional = false
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    saving.value = false
  }
}

async function toggleActive(synonym: SearchSynonym) {
  error.value = null
  try {
    const response = await useApi().search.synonyms.update(synonym.id, { is_active: !synonym.is_active })
    const idx = synonyms.value.findIndex(s => s.id === synonym.id)
    if (idx !== -1) synonyms.value[idx] = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

async function handleDelete(synonym: SearchSynonym) {
  if (!confirm(`Delete synonym "${synonym.term}"?`)) return
  error.value = null
  try {
    await useApi().search.synonyms.remove(synonym.id)
    synonyms.value = synonyms.value.filter(s => s.id !== synonym.id)
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

.builder label.wide {
  flex-basis: 100%;
}

.builder label.checkbox {
  flex-direction: row;
  align-items: center;
  gap: var(--space-2);
}

.builder input[type="text"] {
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
