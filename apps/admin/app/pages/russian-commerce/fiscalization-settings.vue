<template>
  <div>
    <PageHeader title="Fiscalization Settings" :breadcrumbs="[{ label: 'Russian Commerce', to: '/russian-commerce/legal-profile' }, { label: 'Fiscalization Settings' }]" />

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
          <label class="checkbox">
            <input v-model="form.receipts_required" type="checkbox">
            Receipts required
          </label>
          <div class="form-actions">
            <button type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save settings' }}</button>
          </div>
        </form>
        <p v-if="form.receipts_required && !form.active_provider_id" class="hint warning">
          Receipts are required but no active provider is selected — new orders will fail to fiscalize
          until one is chosen (see FiscalizationNotConfiguredException).
        </p>
      </section>

      <section class="builder">
        <h2>Providers</h2>
        <p class="hint">
          Only <code>fake</code> is actually registered this milestone — no real OFD provider
          (ATOL, OrangeData, CloudKassir) is integrated. See docs/architecture/fiscalization.md.
        </p>
        <form @submit.prevent="handleCreateProvider">
          <label>
            Code
            <input v-model="providerForm.code" type="text" required placeholder="fake">
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
              <th>Credentials</th>
              <th/>
            </tr>
          </thead>
          <tbody>
            <tr v-for="provider in providers" :key="provider.id">
              <td>{{ provider.name }}</td>
              <td>{{ provider.code }}</td>
              <td>{{ provider.is_enabled ? 'Yes' : 'No' }}</td>
              <td>{{ provider.has_credentials ? 'Configured' : 'Not configured' }}</td>
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
import type { FiscalizationProvider, FiscalizationSettings } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

definePageMeta({ layout: 'settings' })

const activeStore = useActiveStore()

const settings = ref<FiscalizationSettings | null>(null)
const providers = ref<FiscalizationProvider[]>([])
const pending = ref(true)
const saving = ref(false)
const creatingProvider = ref(false)
const error = ref<string | null>(null)

const form = reactive<{ active_provider_id: string | null, receipts_required: boolean }>({
  active_provider_id: null,
  receipts_required: false,
})

const providerForm = reactive<{ code: string, name: string }>({ code: '', name: '' })

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    const [settingsResponse, providersResponse] = await Promise.all([
      useApi().russianCommerce.fiscalizationSettings.get(),
      useApi().russianCommerce.fiscalizationProviders.list(),
    ])
    settings.value = settingsResponse.data
    providers.value = providersResponse.data
    form.active_provider_id = settings.value.active_provider_id
    form.receipts_required = settings.value.receipts_required
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
    const response = await useApi().russianCommerce.fiscalizationSettings.update({ ...form })
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
    const response = await useApi().russianCommerce.fiscalizationProviders.create({ code: providerForm.code, name: providerForm.name })
    providers.value.push(response.data)
    providerForm.code = ''
    providerForm.name = ''
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creatingProvider.value = false
  }
}

async function toggleProviderEnabled(provider: FiscalizationProvider) {
  error.value = null
  try {
    const response = await useApi().russianCommerce.fiscalizationProviders.update(provider.id, { is_enabled: !provider.is_enabled })
    const idx = providers.value.findIndex(p => p.id === provider.id)
    if (idx !== -1) providers.value[idx] = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

async function handleDeleteProvider(provider: FiscalizationProvider) {
  if (!confirm(`Delete provider "${provider.name}"? If it's the active provider, fiscalization settings will be left with no active provider.`)) return
  error.value = null
  try {
    await useApi().russianCommerce.fiscalizationProviders.remove(provider.id)
    providers.value = providers.value.filter(p => p.id !== provider.id)
    if (form.active_provider_id === provider.id) form.active_provider_id = null
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

.hint.warning {
  color: var(--color-danger, #c00);
}
</style>
