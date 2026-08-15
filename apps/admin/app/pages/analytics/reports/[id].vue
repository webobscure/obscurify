<template>
  <div>
    <PageHeader :title="`${formatReportType(report?.report_type)} report`" :breadcrumbs="[{ label: 'Analytics', to: '/analytics' }, { label: 'Reports', to: '/analytics/reports' }, { label: 'Detail' }]" />

    <p v-if="error" class="error">{{ error }}</p>
    <p v-if="pending" class="hint">Loading…</p>

    <template v-else-if="report">
      <div class="meta-row">
        <span class="badge" :class="report.status">{{ report.status }}</span>
        <span v-if="report.row_count !== null">{{ report.row_count }} rows</span>
        <span v-if="report.generated_at">Generated {{ report.generated_at }}</span>
      </div>

      <p v-if="report.error_message" class="error">{{ report.error_message }}</p>

      <section v-if="report.result?.length" class="result">
        <table>
          <thead>
            <tr>
              <th v-for="key in resultColumns" :key="key">{{ key }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, index) in report.result" :key="index">
              <td v-for="key in resultColumns" :key="key">{{ formatCell(row[key]) }}</td>
            </tr>
          </tbody>
        </table>
      </section>
      <p v-else-if="report.status === 'completed'" class="hint">Report produced no rows.</p>

      <section class="export-center">
        <h2>Export Center</h2>
        <div class="export-actions">
          <button type="button" :disabled="exporting !== null" @click="handleExport('csv')">{{ exporting === 'csv' ? 'Exporting…' : 'Export CSV' }}</button>
          <button type="button" :disabled="exporting !== null" @click="handleExport('xlsx')">{{ exporting === 'xlsx' ? 'Exporting…' : 'Export Excel' }}</button>
          <button type="button" :disabled="exporting !== null" @click="handleExport('pdf')">{{ exporting === 'pdf' ? 'Exporting…' : 'Export PDF' }}</button>
        </div>

        <table v-if="exports.length" class="exports-table">
          <thead>
            <tr>
              <th>Format</th>
              <th>Status</th>
              <th/>
            </tr>
          </thead>
          <tbody>
            <tr v-for="exportItem in exports" :key="exportItem.id">
              <td>{{ exportItem.format.toUpperCase() }}</td>
              <td>{{ exportItem.status }}</td>
              <td>
                <a v-if="exportItem.download_url" :href="exportItem.download_url" target="_blank" rel="noopener">Download</a>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="hint">No exports triggered yet this session.</p>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { ExportFormat, Report, ReportExport, ReportType } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
const reportId = route.params.id as string

const report = ref<Report | null>(null)
const exports = ref<ReportExport[]>([])
const pending = ref(true)
const exporting = ref<ExportFormat | null>(null)
const error = ref<string | null>(null)

const resultColumns = computed(() => (report.value?.result?.length ? Object.keys(report.value.result[0]!) : []))

function formatReportType(type?: ReportType): string {
  if (!type) return 'Report'
  return type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}

function formatCell(value: unknown): string {
  if (value === null || value === undefined) return '—'
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}

async function load() {
  pending.value = true
  error.value = null
  try {
    report.value = (await useApi().analytics.reports.get(reportId)).data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

async function handleExport(format: ExportFormat) {
  exporting.value = format
  error.value = null
  try {
    const response = await useApi().analytics.exports.create(reportId, { format })
    exports.value.unshift(response.data)
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    exporting.value = null
  }
}

onMounted(load)
</script>

<style scoped>
.meta-row {
  display: flex;
  gap: var(--space-3);
  align-items: center;
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin-bottom: var(--space-4);
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

.result {
  overflow-x: auto;
  margin-bottom: var(--space-6);
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
  white-space: nowrap;
}

.export-center {
  border-top: 1px solid var(--color-border);
  padding-top: var(--space-4);
}

.export-center h2 {
  font-size: var(--text-base);
  margin: 0 0 var(--space-3);
}

.export-actions {
  display: flex;
  gap: var(--space-2);
  margin-bottom: var(--space-4);
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
