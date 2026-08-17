<template>
  <div>
    <PageHeader title="Search Dashboard">
      <template #actions>
        <NuxtLink to="/search/synonyms">Synonyms</NuxtLink>
        ·
        <NuxtLink to="/search/rules">Rules &amp; Ranking</NuxtLink>
        ·
        <NuxtLink to="/search/pinned">Pinned Products</NuxtLink>
        ·
        <NuxtLink to="/search/settings">Settings</NuxtLink>
        ·
        <NuxtLink to="/search/analytics">Analytics</NuxtLink>
      </template>
    </PageHeader>

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p v-if="error" class="error">{{ error }}</p>

      <section class="index-card">
        <h2>Search index</h2>
        <p v-if="pending" class="hint">Loading…</p>
        <dl v-else-if="index">
          <div><dt>Status</dt><dd><span class="badge" :class="index.status">{{ index.status }}</span></dd></div>
          <div><dt>Documents</dt><dd>{{ index.document_count }}</dd></div>
          <div><dt>Last full reindex</dt><dd>{{ index.last_full_reindex_at ?? 'Never' }}</dd></div>
          <div><dt>Last indexed</dt><dd>{{ index.last_indexed_at ?? 'Never' }}</dd></div>
          <div v-if="index.error_message"><dt>Error</dt><dd class="error">{{ index.error_message }}</dd></div>
        </dl>
        <div class="form-actions">
          <button type="button" :disabled="reindexing" @click="handleReindex">{{ reindexing ? 'Reindexing…' : 'Full reindex' }}</button>
        </div>
      </section>

      <section class="preview-card">
        <h2>Try a search</h2>
        <p class="hint">Runs the exact same pipeline a storefront customer would hit — merchandising rules, synonyms, and ranking included.</p>
        <form @submit.prevent="handlePreview">
          <label class="wide">
            Query
            <input v-model="query" type="text" placeholder="e.g. running shoes">
          </label>
          <div class="form-actions">
            <button type="submit" :disabled="previewing">{{ previewing ? 'Searching…' : 'Search' }}</button>
          </div>
        </form>

        <p v-if="previewResult" class="hint">{{ previewResult.meta.total }} result(s)</p>
        <table v-if="previewResult && previewResult.data.length">
          <thead>
            <tr>
              <th>Title</th>
              <th>Vendor</th>
              <th>Price</th>
              <th>Available</th>
              <th>Score</th>
              <th>Pinned</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in previewResult.data" :key="item.product_id">
              <td>{{ item.title }}</td>
              <td>{{ item.vendor ?? '—' }}</td>
              <td>{{ item.price.min !== null ? (item.price.min / 100).toFixed(2) : '—' }}</td>
              <td>{{ item.availability ? 'Yes' : 'No' }}</td>
              <td>{{ item.score.toFixed(1) }}</td>
              <td>{{ item.is_pinned ? 'Yes' : '' }}</td>
            </tr>
          </tbody>
        </table>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { SearchIndex, SearchResponse } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

definePageMeta({ layout: 'settings' })

const activeStore = useActiveStore()

const index = ref<SearchIndex | null>(null)
const pending = ref(true)
const reindexing = ref(false)
const previewing = ref(false)
const error = ref<string | null>(null)
const query = ref('')
const previewResult = ref<SearchResponse | null>(null)

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    index.value = (await useApi().search.index.get()).data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

async function handleReindex() {
  reindexing.value = true
  error.value = null
  try {
    index.value = (await useApi().search.index.reindex()).data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    reindexing.value = false
  }
}

async function handlePreview() {
  previewing.value = true
  error.value = null
  try {
    previewResult.value = await useApi().search.preview({ q: query.value })
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    previewing.value = false
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
.index-card,
.preview-card {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md, 0.5rem);
  padding: var(--space-4);
  margin-bottom: var(--space-6);
  max-width: 48rem;
}

.index-card h2,
.preview-card h2 {
  margin: 0 0 var(--space-3);
  font-size: var(--text-base);
}

dl {
  display: grid;
  gap: var(--space-2);
  margin: 0 0 var(--space-3);
}

dl div {
  display: flex;
  justify-content: space-between;
  gap: var(--space-3);
}

dt {
  color: var(--color-text-muted);
}

dd {
  margin: 0;
  font-weight: 500;
}

.preview-card form {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  align-items: flex-end;
}

.preview-card label {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.preview-card label.wide {
  flex-basis: 100%;
}

.preview-card input {
  padding: var(--space-1) var(--space-2);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font: inherit;
}

.form-actions {
  display: flex;
  margin-top: var(--space-2);
}

table {
  width: 100%;
  border-collapse: collapse;
  margin-top: var(--space-3);
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

.badge.ready {
  background: #e6f4ea;
  color: #1e7e34;
}

.badge.failed {
  background: #fdeaea;
  color: #b00020;
}

.badge.building,
.badge.stale {
  background: #fdf3e0;
  color: #a06400;
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}
</style>
