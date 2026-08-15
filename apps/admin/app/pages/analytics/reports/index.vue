<template>
  <div>
    <PageHeader title="Reports" :breadcrumbs="[{ label: 'Analytics', to: '/analytics' }, { label: 'Reports' }]" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p v-if="error" class="error">{{ error }}</p>

      <section class="builder">
        <h2>Run a report</h2>
        <form @submit.prevent="handleRun">
          <label>
            Report type
            <select v-model="form.reportType">
              <option v-for="type in reportTypes" :key="type" :value="type">{{ formatReportType(type) }}</option>
            </select>
          </label>
          <label>
            From
            <input v-model="form.from" type="date">
          </label>
          <label>
            To
            <input v-model="form.to" type="date">
          </label>
          <div class="form-actions">
            <button type="submit" :disabled="running">{{ running ? 'Running…' : 'Run report' }}</button>
            <button type="button" class="secondary" :disabled="saving" @click="handleSaveFilters">Save as saved report</button>
          </div>
        </form>
      </section>

      <p v-if="pending" class="hint">Loading…</p>

      <table v-else-if="reports.length">
        <thead>
          <tr>
            <th>Type</th>
            <th>Status</th>
            <th>Rows</th>
            <th>Generated</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="report in reports" :key="report.id">
            <td>{{ formatReportType(report.report_type) }}</td>
            <td><span class="badge" :class="report.status">{{ report.status }}</span></td>
            <td>{{ report.row_count ?? '—' }}</td>
            <td>{{ report.generated_at ?? '—' }}</td>
            <td><NuxtLink :to="`/analytics/reports/${report.id}`">Open</NuxtLink></td>
          </tr>
        </tbody>
      </table>
      <p v-else class="hint">No reports run yet.</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Report, ReportType } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const activeStore = useActiveStore()

const reportTypes: ReportType[] = ['orders', 'products', 'customers', 'inventory', 'shipping', 'payments', 'returns', 'promotions', 'automation_executions']

const reports = ref<Report[]>([])
const pending = ref(true)
const running = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)

const form = reactive<{ reportType: ReportType, from: string, to: string }>({
  reportType: 'orders',
  from: '',
  to: '',
})

function formatReportType(type: ReportType): string {
  return type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

function buildFilters(): Record<string, unknown> {
  const filters: Record<string, unknown> = {}
  if (form.from) filters.from = form.from
  if (form.to) filters.to = form.to
  return filters
}

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    reports.value = (await useApi().analytics.reports.list()).data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

async function handleRun() {
  running.value = true
  error.value = null
  try {
    const response = await useApi().analytics.reports.create({ report_type: form.reportType, filters: buildFilters() })
    await navigateTo(`/analytics/reports/${response.data.id}`)
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    running.value = false
  }
}

async function handleSaveFilters() {
  const name = prompt('Name this saved report:')
  if (!name) return
  saving.value = true
  error.value = null
  try {
    await useApi().analytics.savedReports.create({ name, report_type: form.reportType, filters: buildFilters() })
    await navigateTo('/analytics/saved-reports')
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    saving.value = false
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
  margin: 0 0 var(--space-3);
  font-size: var(--text-base);
}

.builder form {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  align-items: flex-end;
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
  gap: var(--space-2);
}

.form-actions .secondary {
  background: transparent;
  border: 1px solid var(--color-border);
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

.badge.failed {
  background: #fdeaea;
  color: #b00020;
}

.badge.pending {
  background: #fdf3e0;
  color: #a06400;
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
