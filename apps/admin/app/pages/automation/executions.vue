<template>
  <div>
    <PageHeader title="Execution History" :breadcrumbs="[{ label: 'Automation', to: '/automation' }, { label: 'Executions' }]" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <form class="filters" @submit.prevent="load()">
        <select v-model="statusFilter">
          <option value="">Any status</option>
          <option value="pending">Pending</option>
          <option value="running">Running</option>
          <option value="waiting">Waiting</option>
          <option value="completed">Completed</option>
          <option value="failed">Failed</option>
          <option value="dead_letter">Dead letter</option>
        </select>
        <button type="submit">Filter</button>
      </form>

      <table v-if="executions.length">
        <thead>
          <tr>
            <th>Status</th>
            <th>Attempts</th>
            <th>Started</th>
            <th>Completed</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="execution in executions" :key="execution.id">
            <td><span class="badge" :class="execution.status">{{ execution.status }}</span></td>
            <td>{{ execution.attempts }}</td>
            <td>{{ execution.started_at ?? '—' }}</td>
            <td>{{ execution.completed_at ?? '—' }}</td>
            <td><NuxtLink :to="`/automation/executions/${execution.id}`">View</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else>No executions yet.</p>

      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { WorkflowExecution } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const executions = ref<WorkflowExecution[]>([])
const statusFilter = ref('')
const error = ref<string | null>(null)
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  error.value = null
  try {
    executions.value = (await useApi().automation.executions.list({ status: statusFilter.value || undefined })).data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
.filters {
  display: flex;
  gap: var(--space-2);
  margin-bottom: var(--space-4);
}

.filters select {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
}

.filters button {
  padding: var(--space-1) var(--space-3);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: transparent;
  cursor: pointer;
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

.badge {
  display: inline-block;
  padding: 0.1rem 0.6rem;
  border-radius: 999px;
  background: var(--color-surface-muted, #f0f0f0);
  color: var(--color-text-muted);
  font-size: var(--text-xs);
}

.badge.completed {
  background: #e6f4ea;
  color: #1e7e34;
}

.badge.failed,
.badge.dead_letter {
  background: #fdeaea;
  color: #c0392b;
}

.badge.waiting {
  background: #fdf3e0;
  color: #a06400;
}
</style>
