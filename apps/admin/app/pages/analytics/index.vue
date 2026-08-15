<template>
  <div>
    <PageHeader title="Analytics">
      <template #actions>
        <NuxtLink to="/analytics/reports">Reports</NuxtLink>
        ·
        <NuxtLink to="/analytics/saved-reports">Saved Reports</NuxtLink>
      </template>
    </PageHeader>

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p v-if="error" class="error">{{ error }}</p>

      <section class="builder">
        <h2>{{ editingWidget ? 'Edit widget' : 'Add widget' }}</h2>
        <form @submit.prevent="handleSubmit">
          <label>
            Title
            <input v-model="form.title" type="text" required>
          </label>
          <label>
            Type
            <select v-model="form.type">
              <option value="metric_card">Metric card</option>
              <option value="line_chart">Line chart</option>
              <option value="bar_chart">Bar chart</option>
              <option value="pie_chart">Pie chart</option>
              <option value="table">Table</option>
              <option value="leaderboard">Leaderboard</option>
            </select>
          </label>
          <label>
            Metric
            <select v-model="form.metricKey" required>
              <option value="">Select a metric…</option>
              <option v-for="metric in metrics" :key="metric.key" :value="metric.key">{{ metric.label }}</option>
            </select>
          </label>
          <label>
            Time range
            <select v-model="form.timeDimension">
              <option value="today">Today</option>
              <option value="yesterday">Yesterday</option>
              <option value="last_7_days">Last 7 days</option>
              <option value="last_30_days">Last 30 days</option>
              <option value="month">This month</option>
              <option value="quarter">This quarter</option>
              <option value="year">This year</option>
            </select>
          </label>
          <div class="form-actions">
            <button type="submit" :disabled="saving">{{ saving ? 'Saving…' : editingWidget ? 'Save widget' : 'Add widget' }}</button>
            <button v-if="editingWidget" type="button" class="secondary" @click="cancelEdit">Cancel</button>
          </div>
        </form>
      </section>

      <p v-if="pending" class="hint">Loading dashboard…</p>

      <section v-else class="widgets">
        <AnalyticsWidget
          v-for="widget in widgets"
          :key="widget.id"
          :widget="widget"
          :data="widgetData[widget.id] ?? null"
          :pending="widgetPending[widget.id] ?? false"
          :metrics="metrics"
          :currency="currency"
          @edit="startEdit"
          @remove="handleRemove"
          @drill-down="openDrillDown"
        />
        <p v-if="!widgets.length" class="hint">No widgets yet — add one above.</p>
      </section>

      <section v-if="drillDownWidget" class="drill-down">
        <header>
          <h2>{{ drillDownWidget.title }} — details</h2>
          <button type="button" class="link" @click="drillDownWidget = null">Close</button>
        </header>
        <p v-if="drillDownPending" class="hint">Loading…</p>
        <table v-else-if="drillDownRows.length">
          <thead>
            <tr>
              <th v-for="key in drillDownColumns" :key="key">{{ key }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, index) in drillDownRows" :key="index">
              <td v-for="key in drillDownColumns" :key="key">{{ formatCell(row[key]) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="hint">No events in range.</p>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Dashboard, DashboardWidget, DashboardWidgetType, MetricDefinition, TimeDimension, WidgetData } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const activeStore = useActiveStore()

const dashboard = ref<Dashboard | null>(null)
const widgets = ref<DashboardWidget[]>([])
const metrics = ref<MetricDefinition[]>([])
const widgetData = reactive<Record<string, WidgetData | null>>({})
const widgetPending = reactive<Record<string, boolean>>({})

const pending = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)
const editingWidget = ref<DashboardWidget | null>(null)

const currency = computed(() => activeStore.store.value?.default_currency ?? 'USD')

const form = reactive<{ title: string, type: DashboardWidgetType, metricKey: string, timeDimension: TimeDimension }>({
  title: '',
  type: 'metric_card',
  metricKey: '',
  timeDimension: 'last_30_days',
})

const drillDownWidget = ref<DashboardWidget | null>(null)
const drillDownPending = ref(false)
const drillDownRows = ref<Record<string, unknown>[]>([])
const drillDownColumns = computed(() => (drillDownRows.value.length ? Object.keys(drillDownRows.value[0]!) : []))

function resetForm() {
  form.title = ''
  form.type = 'metric_card'
  form.metricKey = ''
  form.timeDimension = 'last_30_days'
  editingWidget.value = null
}

async function loadWidgetData(widget: DashboardWidget) {
  widgetPending[widget.id] = true
  try {
    const response = await useApi().analytics.widgets.data(widget.id)
    widgetData[widget.id] = response.data
  } catch {
    widgetData[widget.id] = null
  } finally {
    widgetPending[widget.id] = false
  }
}

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    const api = useApi()
    const [dashboardResponse, metricsResponse] = await Promise.all([
      api.analytics.dashboards.default(),
      api.analytics.metrics(),
    ])

    dashboard.value = dashboardResponse.data
    widgets.value = dashboardResponse.data.widgets ?? []
    metrics.value = metricsResponse.data

    await Promise.all(widgets.value.map(loadWidgetData))
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

function startEdit(widget: DashboardWidget) {
  editingWidget.value = widget
  form.title = widget.title
  form.type = widget.type
  form.metricKey = widget.config?.metric_key ?? ''
  form.timeDimension = widget.config?.time_dimension ?? 'last_30_days'
}

function cancelEdit() {
  resetForm()
}

async function handleSubmit() {
  if (!dashboard.value) return
  saving.value = true
  error.value = null
  try {
    const api = useApi()
    const config = { metric_key: form.metricKey, time_dimension: form.timeDimension }

    if (editingWidget.value) {
      const response = await api.analytics.widgets.update(editingWidget.value.id, { title: form.title, type: form.type, config })
      const index = widgets.value.findIndex(w => w.id === editingWidget.value!.id)
      if (index !== -1) widgets.value[index] = response.data
      await loadWidgetData(response.data)
    } else {
      const response = await api.analytics.widgets.create(dashboard.value.id, {
        title: form.title,
        type: form.type,
        config,
        position: widgets.value.length,
      })
      widgets.value.push(response.data)
      await loadWidgetData(response.data)
    }
    resetForm()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    saving.value = false
  }
}

async function handleRemove(widget: DashboardWidget) {
  if (!confirm(`Remove widget "${widget.title}"?`)) return
  error.value = null
  try {
    await useApi().analytics.widgets.remove(widget.id)
    widgets.value = widgets.value.filter(w => w.id !== widget.id)
    widgetData[widget.id] = null
    widgetPending[widget.id] = false
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

function formatCell(value: unknown): string {
  if (value === null || value === undefined) return '—'
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}

async function openDrillDown(widget: DashboardWidget) {
  drillDownWidget.value = widget
  drillDownPending.value = true
  drillDownRows.value = []
  try {
    const response = await useApi().analytics.widgets.drillDown(widget.id)
    drillDownRows.value = response.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    drillDownPending.value = false
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

.widgets {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr));
  gap: var(--space-4);
}

.drill-down {
  margin-top: var(--space-6);
  border-top: 1px solid var(--color-border);
  padding-top: var(--space-4);
}

.drill-down header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-3);
}

.drill-down .link {
  background: none;
  border: none;
  color: var(--color-text-muted);
  cursor: pointer;
  text-decoration: underline;
}

.drill-down table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--text-sm);
}

.drill-down th,
.drill-down td {
  text-align: left;
  padding: var(--space-1) var(--space-2);
  border-bottom: 1px solid var(--color-border);
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
