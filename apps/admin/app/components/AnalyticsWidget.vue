<template>
  <section class="widget" :class="widget.type">
    <header>
      <h3>{{ widget.title }}</h3>
      <div class="widget-actions">
        <button type="button" class="link" @click="$emit('drillDown', widget)">Details</button>
        <button type="button" class="link" @click="$emit('edit', widget)">Edit</button>
        <button type="button" class="link danger" @click="$emit('remove', widget)">Remove</button>
      </div>
    </header>

    <p v-if="pending" class="hint">Loading…</p>
    <p v-else-if="!data" class="hint">No metric configured for this widget yet.</p>

    <template v-else>
      <div v-if="widget.type === 'metric_card'" class="metric-card">
        <span class="metric-value">{{ formattedTotal }}</span>
        <span class="metric-range">{{ data.from }} – {{ data.to }}</span>
      </div>

      <div v-else-if="widget.type === 'line_chart' || widget.type === 'bar_chart'" class="chart">
        <svg viewBox="0 0 320 120" preserveAspectRatio="none">
          <polyline
            v-if="widget.type === 'line_chart'"
            :points="linePoints"
            fill="none"
            stroke="var(--color-primary, #2563eb)"
            stroke-width="2"
          />
          <rect
            v-for="(bar, index) in barRects"
            v-else
            :key="index"
            :x="bar.x"
            :y="bar.y"
            :width="bar.width"
            :height="bar.height"
            fill="var(--color-primary, #2563eb)"
          />
        </svg>
        <p v-if="!data.series.length" class="hint">No data points in range.</p>
      </div>

      <div v-else-if="widget.type === 'pie_chart'" class="pie">
        <svg viewBox="-1 -1 2 2" width="120" height="120">
          <path v-for="slice in pieSlices" :key="slice.label" :d="slice.path" :fill="slice.color" />
        </svg>
        <ul class="pie-legend">
          <li v-for="(slice, index) in pieSlices" :key="`legend-${slice.label}`">
            <span class="swatch" :style="{ background: pieColors[index % pieColors.length] }" />
            {{ slice.label }} — {{ formatMetricValue(slice.value) }}
          </li>
        </ul>
        <p v-if="!pieSlices.length" class="hint">No breakdown in range.</p>
      </div>

      <table v-else-if="widget.type === 'table'" class="widget-table">
        <thead>
          <tr v-if="hasBreakdown">
            <th>Label</th>
            <th>Value</th>
          </tr>
          <tr v-else>
            <th>Date</th>
            <th>Value</th>
            <th>Count</th>
          </tr>
        </thead>
        <tbody v-if="hasBreakdown">
          <tr v-for="entry in breakdownEntries" :key="entry.label">
            <td>{{ entry.label }}</td>
            <td>{{ formatMetricValue(entry.value) }}</td>
          </tr>
        </tbody>
        <tbody v-else>
          <tr v-for="point in data.series" :key="point.date">
            <td>{{ point.date }}</td>
            <td>{{ point.value !== null ? formatMetricValue(point.value) : '—' }}</td>
            <td>{{ point.count ?? '—' }}</td>
          </tr>
        </tbody>
      </table>

      <ol v-else-if="widget.type === 'leaderboard'" class="leaderboard">
        <li v-for="(entry, index) in breakdownEntries" :key="entry.label">
          <span class="rank">{{ index + 1 }}</span>
          <span class="label">{{ entry.label }}</span>
          <span class="value">{{ formatMetricValue(entry.value) }}</span>
        </li>
        <li v-if="!breakdownEntries.length" class="hint">No ranked data in range.</li>
      </ol>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { DashboardWidget, MetricDefinition, WidgetBreakdownEntry, WidgetData } from '@obscurify/types'

const props = defineProps<{
  widget: DashboardWidget
  data: WidgetData | null
  pending: boolean
  metrics: MetricDefinition[]
  currency: string
}>()

defineEmits<{
  edit: [widget: DashboardWidget]
  remove: [widget: DashboardWidget]
  drillDown: [widget: DashboardWidget]
}>()

const pieColors = ['#2563eb', '#16a34a', '#d97706', '#dc2626', '#7c3aed', '#0891b2', '#db2777', '#65a30d', '#4338ca', '#ea580c']

const metricUnit = computed(() => {
  const key = props.widget.config?.metric_key
  return props.metrics.find(m => m.key === key)?.unit ?? 'count'
})

function formatMetricValue(value: number): string {
  if (metricUnit.value === 'currency') return formatMoney({ amount: value, currency: props.currency })
  if (metricUnit.value === 'percentage') return `${(value / 100).toFixed(2)}%`
  return new Intl.NumberFormat('ru-RU').format(value)
}

const formattedTotal = computed(() => {
  const total = props.data?.total
  return total === null || total === undefined ? '—' : formatMetricValue(total)
})

const hasBreakdown = computed(() => !!props.data?.breakdown && Object.keys(props.data.breakdown).length > 0)

const breakdownEntries = computed<WidgetBreakdownEntry[]>(() => {
  if (!props.data?.breakdown) return []
  return Object.values(props.data.breakdown).sort((a, b) => b.value - a.value).slice(0, 10)
})

const linePoints = computed(() => {
  const series = props.data?.series ?? []
  if (!series.length) return ''
  const values = series.map(p => p.value ?? 0)
  const max = Math.max(...values, 1)
  const stepX = series.length > 1 ? 320 / (series.length - 1) : 0
  return series.map((point, index) => `${index * stepX},${120 - ((point.value ?? 0) / max) * 110}`).join(' ')
})

const barRects = computed(() => {
  const series = props.data?.series ?? []
  if (!series.length) return []
  const values = series.map(p => p.value ?? 0)
  const max = Math.max(...values, 1)
  const barWidth = 320 / series.length
  return series.map((point, index) => {
    const height = ((point.value ?? 0) / max) * 110
    return { x: index * barWidth + barWidth * 0.15, y: 120 - height, width: barWidth * 0.7, height }
  })
})

const pieSlices = computed(() => {
  const entries = breakdownEntries.value
  const total = entries.reduce((sum, entry) => sum + entry.value, 0)
  if (!total) return []

  let cumulative = 0
  return entries.map((entry, index) => {
    const fraction = entry.value / total
    const startAngle = cumulative * 2 * Math.PI
    cumulative += fraction
    const endAngle = cumulative * 2 * Math.PI
    const x1 = Math.sin(startAngle)
    const y1 = -Math.cos(startAngle)
    const x2 = Math.sin(endAngle)
    const y2 = -Math.cos(endAngle)
    const largeArc = fraction > 0.5 ? 1 : 0
    return {
      label: entry.label,
      value: entry.value,
      color: pieColors[index % pieColors.length],
      path: `M 0 0 L ${x1} ${y1} A 1 1 0 ${largeArc} 1 ${x2} ${y2} Z`,
    }
  })
})
</script>

<style scoped>
.widget {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md, 0.5rem);
  padding: var(--space-4);
  background: var(--color-surface, #fff);
}

header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-3);
}

h3 {
  margin: 0;
  font-size: var(--text-base);
  font-weight: var(--font-weight-semibold);
}

.widget-actions {
  display: flex;
  gap: var(--space-2);
}

.link {
  background: none;
  border: none;
  color: var(--color-text-muted);
  cursor: pointer;
  padding: 0;
  font-size: var(--text-xs);
  text-decoration: underline;
}

.link.danger {
  color: var(--color-danger, #c00);
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.metric-card {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.metric-value {
  font-size: var(--text-2xl, 2rem);
  font-weight: var(--font-weight-semibold);
}

.metric-range {
  color: var(--color-text-muted);
  font-size: var(--text-xs);
}

.chart svg {
  width: 100%;
  height: 8rem;
}

.pie {
  display: flex;
  align-items: center;
  gap: var(--space-4);
}

.pie-legend {
  list-style: none;
  margin: 0;
  padding: 0;
  font-size: var(--text-sm);
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.swatch {
  display: inline-block;
  width: 0.7rem;
  height: 0.7rem;
  border-radius: 999px;
  margin-right: var(--space-1);
}

.widget-table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--text-sm);
}

.widget-table th,
.widget-table td {
  text-align: left;
  padding: var(--space-1) var(--space-2);
  border-bottom: 1px solid var(--color-border);
}

.leaderboard {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.leaderboard li {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: var(--text-sm);
}

.leaderboard .rank {
  width: 1.5rem;
  color: var(--color-text-muted);
  font-weight: var(--font-weight-semibold);
}

.leaderboard .label {
  flex: 1;
}
</style>
