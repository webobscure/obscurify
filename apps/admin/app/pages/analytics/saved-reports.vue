<template>
  <div>
    <PageHeader title="Saved Reports" :breadcrumbs="[{ label: 'Analytics', to: '/analytics' }, { label: 'Saved Reports' }]" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p v-if="error" class="error">{{ error }}</p>

      <section class="builder">
        <h2>Save a report</h2>
        <form @submit.prevent="handleCreate">
          <label>
            Name
            <input v-model="form.name" type="text" required>
          </label>
          <label>
            Report type
            <select v-model="form.reportType">
              <option v-for="type in reportTypes" :key="type" :value="type">{{ formatReportType(type) }}</option>
            </select>
          </label>
          <div class="form-actions">
            <button type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save report' }}</button>
          </div>
        </form>
      </section>

      <p v-if="pending" class="hint">Loading…</p>

      <table v-else-if="savedReports.length">
        <thead>
          <tr>
            <th>Name</th>
            <th>Type</th>
            <th/>
          </tr>
        </thead>
        <tbody>
          <tr v-for="savedReport in savedReports" :key="savedReport.id">
            <td>{{ savedReport.name }}</td>
            <td>{{ formatReportType(savedReport.report_type) }}</td>
            <td class="row-actions">
              <button type="button" class="link" :disabled="running === savedReport.id" @click="handleRun(savedReport)">
                {{ running === savedReport.id ? 'Running…' : 'Run' }}
              </button>
              <button type="button" class="link danger" @click="handleRemove(savedReport)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else class="hint">No saved reports yet.</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { ReportType, SavedReport } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const activeStore = useActiveStore()

const reportTypes: ReportType[] = ['orders', 'products', 'customers', 'inventory', 'shipping', 'payments', 'returns', 'promotions', 'automation_executions']

const savedReports = ref<SavedReport[]>([])
const pending = ref(true)
const saving = ref(false)
const running = ref<string | null>(null)
const error = ref<string | null>(null)

const form = reactive<{ name: string, reportType: ReportType }>({ name: '', reportType: 'orders' })

function formatReportType(type: ReportType): string {
  return type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    savedReports.value = (await useApi().analytics.savedReports.list()).data
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
    const response = await useApi().analytics.savedReports.create({ name: form.name, report_type: form.reportType })
    savedReports.value.push(response.data)
    form.name = ''
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    saving.value = false
  }
}

async function handleRun(savedReport: SavedReport) {
  running.value = savedReport.id
  error.value = null
  try {
    const response = await useApi().analytics.reports.create({
      report_type: savedReport.report_type,
      filters: savedReport.filters,
      columns: savedReport.columns,
      saved_report_id: savedReport.id,
    })
    await navigateTo(`/analytics/reports/${response.data.id}`)
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
    running.value = null
  }
}

async function handleRemove(savedReport: SavedReport) {
  if (!confirm(`Delete saved report "${savedReport.name}"?`)) return
  error.value = null
  try {
    await useApi().analytics.savedReports.remove(savedReport.id)
    savedReports.value = savedReports.value.filter(r => r.id !== savedReport.id)
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
