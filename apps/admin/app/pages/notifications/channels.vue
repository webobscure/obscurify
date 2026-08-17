<template>
  <div>
    <PageHeader title="Notification Channels" :breadcrumbs="[{ label: 'Notification Center', to: '/notifications' }, { label: 'Channels' }]" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="pending" class="hint">Loading…</p>

      <table v-else-if="channels.length">
        <thead>
          <tr>
            <th>Channel</th>
            <th>Provider</th>
            <th>Enabled</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="channel in channels" :key="channel.id">
            <td>{{ channel.channel }}</td>
            <td>
              <select :value="channel.provider_id ?? ''" @change="reassign(channel, ($event.target as HTMLSelectElement).value)">
                <option value="">No provider</option>
                <option v-for="provider in providers" :key="provider.id" :value="provider.id">{{ provider.name }} ({{ provider.code }})</option>
              </select>
            </td>
            <td>
              <button type="button" class="link" @click="toggleEnabled(channel)">{{ channel.is_enabled ? 'Disable' : 'Enable' }}</button>
            </td>
            <td/>
          </tr>
        </tbody>
      </table>
      <p v-else class="hint">No channels yet.</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { NotificationChannel, NotificationProvider } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

definePageMeta({ layout: 'settings' })

const activeStore = useActiveStore()

const channels = ref<NotificationChannel[]>([])
const providers = ref<NotificationProvider[]>([])
const pending = ref(true)
const error = ref<string | null>(null)

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    const api = useApi()
    const [channelsResponse, providersResponse] = await Promise.all([
      api.notifications.channels.list(),
      api.notifications.providers.list(),
    ])
    channels.value = channelsResponse.data
    providers.value = providersResponse.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

async function reassign(channel: NotificationChannel, providerId: string) {
  error.value = null
  try {
    const response = await useApi().notifications.channels.update(channel.id, { provider_id: providerId || null })
    const index = channels.value.findIndex(c => c.id === channel.id)
    if (index !== -1) channels.value[index] = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

async function toggleEnabled(channel: NotificationChannel) {
  error.value = null
  try {
    const response = await useApi().notifications.channels.update(channel.id, { is_enabled: !channel.is_enabled })
    const index = channels.value.findIndex(c => c.id === channel.id)
    if (index !== -1) channels.value[index] = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
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

select {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
}

.link {
  background: none;
  border: none;
  color: var(--color-text-muted);
  cursor: pointer;
  padding: 0;
  text-decoration: underline;
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
