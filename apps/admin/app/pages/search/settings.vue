<template>
  <div>
    <PageHeader title="Search Settings" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="pending" class="hint">Loading…</p>

      <section v-else-if="settings" class="settings-card">
        <h2>Behavior</h2>
        <form @submit.prevent="handleSave">
          <label>
            Active provider
            <select v-model="form.active_provider_id">
              <option :value="null">— none —</option>
              <option v-for="provider in providers" :key="provider.id" :value="provider.id">{{ provider.name }} ({{ provider.code }})</option>
            </select>
          </label>
          <label>
            Results per page
            <input v-model.number="form.results_per_page" type="number" min="1" max="100">
          </label>
          <label>
            Autocomplete limit
            <input v-model.number="form.autocomplete_limit" type="number" min="1" max="50">
          </label>
          <label class="checkbox">
            <input v-model="form.typo_tolerance_enabled" type="checkbox">
            Typo tolerance
          </label>
          <label class="checkbox">
            <input v-model="form.synonyms_enabled" type="checkbox">
            Synonyms
          </label>
          <label class="checkbox">
            <input v-model="form.facets_enabled" type="checkbox">
            Facets
          </label>
          <div class="form-actions">
            <button type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save settings' }}</button>
          </div>
        </form>
      </section>

      <section class="builder">
        <h2>Providers</h2>
        <p class="hint">Only <code>database</code> is actually registered this milestone — every other code is a selectable placeholder for a future integration (see docs/architecture/search.md).</p>
        <form @submit.prevent="handleCreateProvider">
          <label>
            Code
            <select v-model="providerForm.code">
              <option value="database">database</option>
              <option value="meilisearch">meilisearch</option>
              <option value="typesense">typesense</option>
              <option value="opensearch">opensearch</option>
              <option value="elasticsearch">elasticsearch</option>
            </select>
          </label>
          <label>
            Name
            <input v-model="providerForm.name" type="text" required>
          </label>
          <div class="form-actions">
            <button type="submit" :disabled="creatingProvider">{{ creatingProvider ? 'Creating…' : 'Create provider' }}</button>
          </div>
        </form>

        <table v-if="providers.length">
          <thead>
            <tr>
              <th>Name</th>
              <th>Code</th>
              <th>Enabled</th>
              <th/>
            </tr>
          </thead>
          <tbody>
            <tr v-for="provider in providers" :key="provider.id">
              <td>{{ provider.name }}</td>
              <td>{{ provider.code }}</td>
              <td>{{ provider.is_enabled ? 'Yes' : 'No' }}</td>
              <td class="row-actions">
                <button type="button" class="link" @click="toggleProviderEnabled(provider)">{{ provider.is_enabled ? 'Disable' : 'Enable' }}</button>
                <button type="button" class="link danger" @click="handleDeleteProvider(provider)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { SearchProvider, SearchSettings } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

definePageMeta({ layout: 'settings' })

const activeStore = useActiveStore()

const settings = ref<SearchSettings | null>(null)
const providers = ref<SearchProvider[]>([])
const pending = ref(true)
const saving = ref(false)
const creatingProvider = ref(false)
const error = ref<string | null>(null)

const form = reactive<{ active_provider_id: string | null, results_per_page: number, autocomplete_limit: number, typo_tolerance_enabled: boolean, synonyms_enabled: boolean, facets_enabled: boolean }>({
  active_provider_id: null,
  results_per_page: 24,
  autocomplete_limit: 8,
  typo_tolerance_enabled: true,
  synonyms_enabled: true,
  facets_enabled: true,
})

const providerForm = reactive<{ code: string, name: string }>({ code: 'database', name: '' })

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    const [settingsResponse, providersResponse] = await Promise.all([
      useApi().search.settings.get(),
      useApi().search.providers.list(),
    ])
    settings.value = settingsResponse.data
    providers.value = providersResponse.data
    form.active_provider_id = settings.value.active_provider_id
    form.results_per_page = settings.value.results_per_page
    form.autocomplete_limit = settings.value.autocomplete_limit
    form.typo_tolerance_enabled = settings.value.typo_tolerance_enabled
    form.synonyms_enabled = settings.value.synonyms_enabled
    form.facets_enabled = settings.value.facets_enabled
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
    const response = await useApi().search.settings.update({ ...form })
    settings.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    saving.value = false
  }
}

async function handleCreateProvider() {
  creatingProvider.value = true
  error.value = null
  try {
    const response = await useApi().search.providers.create({ code: providerForm.code, name: providerForm.name })
    providers.value.push(response.data)
    providerForm.name = ''
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creatingProvider.value = false
  }
}

async function toggleProviderEnabled(provider: SearchProvider) {
  error.value = null
  try {
    const response = await useApi().search.providers.update(provider.id, { is_enabled: !provider.is_enabled })
    const idx = providers.value.findIndex(p => p.id === provider.id)
    if (idx !== -1) providers.value[idx] = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

async function handleDeleteProvider(provider: SearchProvider) {
  if (!confirm(`Delete provider "${provider.name}"? If it's the active provider, settings will fall back to database.`)) return
  error.value = null
  try {
    await useApi().search.providers.remove(provider.id)
    providers.value = providers.value.filter(p => p.id !== provider.id)
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
.settings-card,
.builder {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md, 0.5rem);
  padding: var(--space-4);
  margin-bottom: var(--space-6);
  max-width: 48rem;
}

.settings-card h2,
.builder h2 {
  margin: 0 0 var(--space-2);
  font-size: var(--text-base);
}

.settings-card form,
.builder form {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  align-items: flex-end;
  margin-top: var(--space-2);
}

.settings-card label,
.builder label {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.settings-card label.checkbox {
  flex-direction: row;
  align-items: center;
  gap: var(--space-2);
}

.settings-card input,
.settings-card select,
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
  margin-top: var(--space-4);
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
