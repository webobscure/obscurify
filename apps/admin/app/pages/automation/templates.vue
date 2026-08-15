<template>
  <div>
    <PageHeader title="Automation Templates" :breadcrumbs="[{ label: 'Automation', to: '/automation' }, { label: 'Templates' }]" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <div v-if="templates.length" class="grid">
        <article v-for="template in templates" :key="template.id" class="card">
          <span class="category">{{ template.category }}</span>
          <h3>{{ template.name }}</h3>
          <p>{{ template.description }}</p>
          <button type="button" :disabled="instantiating === template.id" @click="handleUse(template.id)">
            {{ instantiating === template.id ? 'Creating…' : 'Use this template' }}
          </button>
        </article>
      </div>
      <p v-else>No templates available.</p>

      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { WorkflowTemplate } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const templates = ref<WorkflowTemplate[]>([])
const error = ref<string | null>(null)
const instantiating = ref<string | null>(null)
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  error.value = null
  try {
    templates.value = (await useApi().automation.templates.list()).data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

async function handleUse(templateId: string) {
  instantiating.value = templateId
  error.value = null
  try {
    const response = await useApi().automation.templates.instantiate(templateId)
    await navigateTo(`/automation/${response.data.id}`)
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
    instantiating.value = null
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr));
  gap: var(--space-4);
}

.card {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: var(--space-4);
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.category {
  text-transform: uppercase;
  font-size: var(--text-xs);
  color: var(--color-text-muted);
  letter-spacing: 0.04em;
}

.card h3 {
  margin: 0;
}

.card p {
  color: var(--color-text-muted);
  flex-grow: 1;
}

.card button {
  padding: var(--space-2) var(--space-3);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: transparent;
  cursor: pointer;
}
</style>
