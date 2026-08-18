<template>
  <div>
    <PageHeader title="Notification Providers" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p v-if="error" class="error">{{ error }}</p>

      <section class="builder">
        <h2>New provider</h2>
        <p class="hint">Only <code>fake</code> is actually wired up to send anything this milestone — every other code is a selectable placeholder that fails at send time until a real integration exists.</p>
        <form @submit.prevent="handleCreate">
          <label>
            Code
            <select v-model="form.code">
              <option value="fake">fake</option>
              <option value="smtp">smtp</option>
              <option value="mailgun">mailgun</option>
              <option value="resend">resend</option>
              <option value="ses">ses</option>
              <option value="twilio">twilio</option>
              <option value="telegram">telegram</option>
              <option value="whatsapp">whatsapp</option>
              <option value="firebase_push">firebase_push</option>
              <option value="apps_sdk">apps_sdk</option>
            </select>
          </label>
          <label>
            Name
            <input v-model="form.name" type="text" required>
          </label>
          <div class="form-actions">
            <button type="submit" :disabled="saving">{{ saving ? 'Creating…' : 'Create provider' }}</button>
          </div>
        </form>
      </section>

      <p v-if="pending" class="hint">Loading…</p>

      <table v-else-if="providers.length">
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
              <button type="button" class="link" @click="toggleEnabled(provider)">{{ provider.is_enabled ? 'Disable' : 'Enable' }}</button>
              <button type="button" class="link danger" @click="handleDelete(provider)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else class="hint">No providers yet.</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { NotificationProvider } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

definePageMeta({ layout: 'settings' })

const activeStore = useActiveStore()

const providers = ref<NotificationProvider[]>([])
const pending = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)

const form = reactive<{ code: string, name: string }>({ code: 'fake', name: '' })

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    providers.value = (await useApi().notifications.providers.list()).data
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
    const response = await useApi().notifications.providers.create({ code: form.code, name: form.name })
    providers.value.push(response.data)
    form.name = ''
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    saving.value = false
  }
}

async function toggleEnabled(provider: NotificationProvider) {
  error.value = null
  try {
    const response = await useApi().notifications.providers.update(provider.id, { is_enabled: !provider.is_enabled })
    const index = providers.value.findIndex(p => p.id === provider.id)
    if (index !== -1) providers.value[index] = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

async function handleDelete(provider: NotificationProvider) {
  if (!confirm(`Delete provider "${provider.name}"? Any channel assigned to it will be unassigned.`)) return
  error.value = null
  try {
    await useApi().notifications.providers.remove(provider.id)
    providers.value = providers.value.filter(p => p.id !== provider.id)
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

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
