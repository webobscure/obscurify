<template>
  <div>
    <PageHeader title="Delivery Log" description="Also serves as Failed Deliveries / Retry Queue — filter by status below." :breadcrumbs="[{ label: 'Notification Center', to: '/notifications' }, { label: 'Delivery Log' }]" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p v-if="error" class="error">{{ error }}</p>

      <div class="filters">
        <select v-model="statusFilter">
          <option value="">All statuses</option>
          <option value="pending">Pending</option>
          <option value="sending">Sending</option>
          <option value="succeeded">Succeeded</option>
          <option value="failed">Failed (Retry Queue)</option>
          <option value="exhausted">Exhausted (dead letter)</option>
          <option value="suppressed">Suppressed</option>
        </select>
      </div>

      <p v-if="pending" class="hint">Loading…</p>

      <table v-else-if="deliveries.length">
        <thead>
          <tr>
            <th>Channel</th>
            <th>Status</th>
            <th>Attempts</th>
            <th>Last attempted</th>
            <th>Next retry</th>
            <th>Error</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="delivery in deliveries" :key="delivery.id">
            <td>{{ delivery.channel }}</td>
            <td><span class="badge" :class="delivery.status">{{ delivery.status }}</span></td>
            <td>{{ delivery.attempt_count }}</td>
            <td>{{ delivery.last_attempted_at ?? '—' }}</td>
            <td>{{ delivery.next_retry_at ?? '—' }}</td>
            <td>{{ delivery.error_message ?? '—' }}</td>
            <td>
              <button v-if="delivery.status === 'failed'" type="button" class="link" :disabled="retrying === delivery.id" @click="handleRetry(delivery.id)">
                {{ retrying === delivery.id ? 'Retrying…' : 'Retry now' }}
              </button>
              <NuxtLink :to="`/notifications/${delivery.notification_id}`">Notification</NuxtLink>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else class="hint">No deliveries match this filter.</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { NotificationDelivery, NotificationDeliveryStatus } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

definePageMeta({ layout: 'settings' })

const activeStore = useActiveStore()

const deliveries = ref<NotificationDelivery[]>([])
const pending = ref(true)
const retrying = ref<string | null>(null)
const error = ref<string | null>(null)
const statusFilter = ref<NotificationDeliveryStatus | ''>('')

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    deliveries.value = (await useApi().notifications.deliveries.list({ status: statusFilter.value || undefined })).data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

async function handleRetry(deliveryId: string) {
  retrying.value = deliveryId
  error.value = null
  try {
    await useApi().notifications.deliveries.retry(deliveryId)
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    retrying.value = null
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
watch(statusFilter, load)
</script>

<style scoped>
.filters {
  margin-bottom: var(--space-3);
}

.filters select {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--text-sm);
}

th,
td {
  text-align: left;
  padding: var(--space-1) var(--space-2);
  border-bottom: 1px solid var(--color-border);
}

td .link {
  margin-right: var(--space-2);
}

.badge {
  display: inline-block;
  padding: 0.1rem 0.6rem;
  border-radius: 999px;
  background: var(--color-surface-muted, #f0f0f0);
  color: var(--color-text-muted);
  font-size: var(--text-xs);
}

.badge.succeeded {
  background: #e6f4ea;
  color: #1e7e34;
}

.badge.failed,
.badge.exhausted {
  background: #fdeaea;
  color: #b00020;
}

.badge.pending,
.badge.sending {
  background: #fdf3e0;
  color: #a06400;
}

.badge.suppressed {
  background: #f3f3f3;
  color: #888;
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
