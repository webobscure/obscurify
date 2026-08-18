<template>
  <div>
    <PageHeader title="Search Analytics" :breadcrumbs="[{ label: 'Search Dashboard', to: '/search' }, { label: 'Search Analytics' }]" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p v-if="error" class="error">{{ error }}</p>
      <p v-if="pending" class="hint">Loading…</p>

      <template v-else-if="summary">
        <div class="stats">
          <div class="stat"><span class="value">{{ summary.total_searches }}</span><span class="label">Total searches</span></div>
          <div class="stat"><span class="value">{{ summary.total_clicks }}</span><span class="label">Total clicks</span></div>
          <div class="stat"><span class="value">{{ summary.total_conversions }}</span><span class="label">Conversions</span></div>
          <div class="stat"><span class="value">{{ (summary.click_through_rate * 100).toFixed(1) }}%</span><span class="label">Click-through rate</span></div>
          <div class="stat"><span class="value">{{ (summary.conversion_rate * 100).toFixed(1) }}%</span><span class="label">Conversion rate</span></div>
        </div>

        <div class="columns">
          <section>
            <h2>Most popular searches</h2>
            <table v-if="summary.popular_searches.length">
              <thead><tr><th>Query</th><th>Count</th></tr></thead>
              <tbody>
                <tr v-for="row in summary.popular_searches" :key="row.query">
                  <td>{{ row.query }}</td>
                  <td>{{ row.count }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else class="hint">No searches in this period.</p>
          </section>

          <section>
            <h2>Top failed searches (zero results)</h2>
            <table v-if="summary.zero_result_searches.length">
              <thead><tr><th>Query</th><th>Count</th></tr></thead>
              <tbody>
                <tr v-for="row in summary.zero_result_searches" :key="row.query">
                  <td>{{ row.query }}</td>
                  <td>{{ row.count }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else class="hint">No zero-result searches in this period.</p>
          </section>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { SearchAnalyticsSummary } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const activeStore = useActiveStore()

const summary = ref<SearchAnalyticsSummary | null>(null)
const pending = ref(true)
const error = ref<string | null>(null)

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    summary.value = (await useApi().search.analytics()).data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
.stats {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-4);
  margin-bottom: var(--space-6);
}

.stat {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md, 0.5rem);
  padding: var(--space-3) var(--space-4);
  min-width: 9rem;
}

.stat .value {
  font-size: var(--text-xl, 1.5rem);
  font-weight: 600;
}

.stat .label {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.columns {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr));
  gap: var(--space-6);
}

.columns h2 {
  margin: 0 0 var(--space-3);
  font-size: var(--text-base);
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

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
