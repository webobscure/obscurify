<template>
  <div>
    <PageHeader title="Block Library" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p class="hint">
        Every built-in block type — usable inside any section. Insert one
        from a section's own Builder on the
        <NuxtLink to="/pages">Pages</NuxtLink> list.
      </p>
      <p v-if="error" class="error">{{ error }}</p>

      <div v-if="presets.length" class="grid">
        <article v-for="preset in presets" :key="preset.id" class="card">
          <h2>{{ preset.name }}</h2>
          <code>{{ preset.handle }}</code>
          <pre>{{ JSON.stringify(preset.settings, null, 2) }}</pre>
        </article>
      </div>
      <p v-else-if="!pending">
        No presets yet — open the Builder on any page once to seed them.
      </p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { BuilderPreset } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const activeStore = useActiveStore()
const presets = ref<BuilderPreset[]>([])
const pending = ref(true)
const error = ref<string | null>(null)

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    const response = await useApi().builder.presets.list('block')
    presets.value = response.data
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
.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin: 0 0 0.75rem;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: var(--space-3);
}

.card {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: var(--space-3);
}

.card h2 {
  margin: 0 0 var(--space-1);
  font-size: var(--text-md);
}

pre {
  overflow-x: auto;
  background: var(--color-bg);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: var(--space-2);
  font-size: var(--text-xs);
  margin-top: var(--space-2);
}
</style>
