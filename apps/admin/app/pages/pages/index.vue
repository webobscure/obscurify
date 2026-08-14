<template>
  <div>
    <PageHeader title="Pages" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <table v-if="pages.length">
        <thead>
          <tr>
            <th>Title</th>
            <th>Slug</th>
            <th>Status</th>
            <th>Live</th>
            <th>Versions</th>
            <th />
          </tr>
        </thead>
        <tbody>
          <tr v-for="page in pages" :key="page.id">
            <td>{{ page.title }}</td>
            <td>{{ page.slug }}</td>
            <td>{{ page.status }}</td>
            <td>{{ page.is_active ? 'Yes' : '—' }}</td>
            <td>{{ page.versions.length }}</td>
            <td>
              <NuxtLink :to="`/pages/${page.id}`">View</NuxtLink>
              <NuxtLink :to="`/builder/pages/${page.id}`">Builder</NuxtLink>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else>No pages yet.</p>

      <h2>Create a page</h2>
      <p class="hint">
        A new page starts as a draft with one empty version — publish it to
        make it live. Pick a preset to prefill its sections; presets are
        copied in once at creation, never linked, and are managed on the
        <NuxtLink to="/page-templates">Page Templates</NuxtLink> page.
      </p>
      <form class="grid" @submit.prevent="handleCreate">
        <input v-model="form.title" type="text" placeholder="Title" required >
        <input v-model="form.slug" type="text" placeholder="Slug (about-us)" required >
        <select v-model="form.page_template_id">
          <option value="">No preset — start empty</option>
          <option v-for="template in templates" :key="template.id" :value="template.id">{{ template.name }}</option>
        </select>
        <button type="submit" :disabled="creating">{{ creating ? 'Creating…' : 'Create page' }}</button>
      </form>
      <p v-if="error" class="error">{{ error }}</p>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { Page, PageTemplate } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const pages = ref<Page[]>([])
const templates = ref<PageTemplate[]>([])
const form = reactive<{ title: string, slug: string, page_template_id: string }>({ title: '', slug: '', page_template_id: '' })
const creating = ref(false)
const error = ref<string | null>(null)
const activeStore = useActiveStore()

async function load() {
  if (!activeStore.storeId.value) return
  const [pagesResponse, templatesResponse] = await Promise.all([
    useApi().pages.list(),
    useApi().pageTemplates.list(),
  ])
  pages.value = pagesResponse.data
  templates.value = templatesResponse.data
}

async function handleCreate() {
  creating.value = true
  error.value = null
  try {
    await useApi().pages.create({
      title: form.title,
      slug: form.slug,
      // Omitted entirely rather than sent as null — the backend only
      // reads `page_template_id` when it is actually present.
      ...(form.page_template_id ? { page_template_id: form.page_template_id } : {}),
    })
    form.title = ''
    form.slug = ''
    form.page_template_id = ''
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creating.value = false
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
.grid {
  display: grid;
  gap: 0.5rem;
  max-width: 480px;
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin: 0 0 0.5rem;
}
</style>
