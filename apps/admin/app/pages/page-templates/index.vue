<template>
  <div>
    <PageHeader title="Page Templates" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <p class="hint">
        Presets a merchant can start a new page from. A preset's sections are
        copied into the page once, at creation — editing one here never
        changes a page that was already built from it.
      </p>

      <div v-for="template in templates" :key="template.id" class="template">
        <input v-model="drafts[template.id]!.name" type="text" placeholder="Name" >
        <textarea v-model="drafts[template.id]!.sections" rows="8" spellcheck="false" />
        <div class="actions">
          <button type="button" :disabled="savingId === template.id" @click="handleSave(template.id)">
            {{ savingId === template.id ? 'Saving…' : 'Save' }}
          </button>
          <button type="button" :disabled="deletingId === template.id" @click="handleDelete(template.id)">
            {{ deletingId === template.id ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
        <p v-if="errors[template.id]" class="error">{{ errors[template.id] }}</p>
      </div>
      <p v-if="!templates.length">No page templates yet.</p>

      <h2>Create a page template</h2>
      <form class="grid" @submit.prevent="handleCreate">
        <input v-model="form.name" type="text" placeholder="Name" required >
        <textarea v-model="form.sections" rows="8" spellcheck="false" />
        <button type="submit" :disabled="creating">{{ creating ? 'Creating…' : 'Create template' }}</button>
      </form>
      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { PageTemplate } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const templates = ref<PageTemplate[]>([])
/** Rebuilt wholesale on every load so a deleted template leaves no stale draft. */
const drafts = ref<Record<string, { name: string, sections: string }>>({})
const errors = reactive<Record<string, string | null>>({})
const form = reactive<{ name: string, sections: string }>({ name: '', sections: '[]' })
const creating = ref(false)
const savingId = ref<string | null>(null)
const deletingId = ref<string | null>(null)
const error = ref<string | null>(null)
const activeStore = useActiveStore()

/** Shared by the create form and every inline editor — same JSON contract. */
function parseSections(raw: string): unknown[] | string {
  let parsed: unknown
  try {
    parsed = JSON.parse(raw)
  } catch {
    return 'Not valid JSON — fix the syntax and try again.'
  }

  return Array.isArray(parsed) ? parsed : 'Sections must be a JSON array of section instances.'
}

async function load() {
  if (!activeStore.storeId.value) return
  const response = await useApi().pageTemplates.list()
  templates.value = response.data

  drafts.value = Object.fromEntries(templates.value.map(template => [
    template.id,
    { name: template.name, sections: JSON.stringify(template.sections, null, 2) },
  ]))
  for (const template of templates.value) errors[template.id] = null
}

async function handleCreate() {
  const sections = parseSections(form.sections)
  if (typeof sections === 'string') {
    error.value = sections
    return
  }

  creating.value = true
  error.value = null
  try {
    await useApi().pageTemplates.create({ name: form.name, sections })
    form.name = ''
    form.sections = '[]'
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creating.value = false
  }
}

async function handleSave(templateId: string) {
  const draft = drafts.value[templateId]
  if (!draft) return

  const sections = parseSections(draft.sections)
  if (typeof sections === 'string') {
    errors[templateId] = sections
    return
  }

  savingId.value = templateId
  errors[templateId] = null
  try {
    await useApi().pageTemplates.update(templateId, { name: draft.name, sections })
    await load()
  } catch (e) {
    errors[templateId] = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    savingId.value = null
  }
}

async function handleDelete(templateId: string) {
  deletingId.value = templateId
  errors[templateId] = null
  try {
    await useApi().pageTemplates.remove(templateId)
    await load()
  } catch (e) {
    errors[templateId] = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    deletingId.value = null
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
.grid {
  display: grid;
  gap: 0.5rem;
  max-width: 640px;
}

.template {
  max-width: 640px;
  margin-bottom: 1.5rem;
}

.actions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin: 0 0 1rem;
}

input {
  width: 100%;
  margin-bottom: 0.5rem;
}

textarea {
  width: 100%;
  font-family: monospace;
  font-size: var(--text-sm);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 0.5rem;
  margin-bottom: 0.5rem;
}

.grid input,
.grid textarea {
  margin-bottom: 0;
}
</style>
