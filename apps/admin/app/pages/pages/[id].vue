<template>
  <div>
    <PageHeader
      :title="page?.title ?? 'Page'"
      :breadcrumbs="[{ label: 'Pages', to: '/pages' }, { label: page?.title ?? 'Page' }]"
    />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else-if="page">
      <table class="kv">
        <tbody>
          <tr><th>Slug</th><td>{{ page.slug }}</td></tr>
          <tr><th>Status</th><td>{{ page.status }}</td></tr>
          <tr><th>Live on the storefront</th><td>{{ page.is_active ? 'Yes' : 'No' }}</td></tr>
          <tr><th>Draft version</th><td>#{{ draftVersion.version_number }}</td></tr>
        </tbody>
      </table>
      <p class="hint">
        Status and Live are independent: publishing a version points the
        storefront at it without touching the page's own status label, so a
        page can read "draft" while still serving.
      </p>

      <div class="actions">
        <button type="button" :disabled="publishing" @click="handlePublish">
          {{ publishing ? 'Publishing…' : 'Publish draft' }}
        </button>
        <button type="button" :disabled="duplicating" @click="handleDuplicate">
          {{ duplicating ? 'Duplicating…' : 'Duplicate' }}
        </button>
      </div>

      <p v-if="error" class="error">{{ error }}</p>

      <section>
        <h2>Versions</h2>
        <table>
          <thead>
            <tr><th>Version</th><th>Status</th><th>Published</th><th>Created</th><th /></tr>
          </thead>
          <tbody>
            <tr v-for="version in versions" :key="version.id">
              <td>#{{ version.version_number }}</td>
              <td>{{ version.status }}</td>
              <td>{{ version.published_at ?? '—' }}</td>
              <td>{{ version.created_at }}</td>
              <td>
                <button
                  v-if="version.status === 'published'"
                  type="button"
                  :disabled="rollingBackId === version.id"
                  @click="handleRollback(version.id)"
                >
                  {{ rollingBackId === version.id ? 'Rolling back…' : 'Roll back to this' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <section>
        <h2>Draft sections</h2>
        <p class="hint">
          Sections are edited as raw JSON — there is no endpoint yet that
          describes which section/block handles the active theme defines or
          what fields they take, so the admin cannot generate a form for them.
        </p>
        <textarea v-model="sectionsDraft" rows="14" spellcheck="false" />
        <div class="actions">
          <button type="button" :disabled="savingSections" @click="handleSaveSections">
            {{ savingSections ? 'Saving…' : 'Save sections' }}
          </button>
        </div>
        <p v-if="sectionsError" class="error">{{ sectionsError }}</p>
      </section>

      <section>
        <h2>SEO</h2>
        <p class="hint">Belongs to the draft version — published versions are frozen, SEO included.</p>
        <form class="grid" @submit.prevent="handleSaveSeo">
          <input v-model="seoForm.meta_title" type="text" placeholder="Meta title" maxlength="255" >
          <textarea v-model="seoForm.meta_description" rows="3" placeholder="Meta description" maxlength="500" />
          <input v-model="seoForm.canonical_url" type="text" placeholder="Canonical URL" >
          <input v-model="seoForm.og_image" type="text" placeholder="OG image path" >
          <button type="submit" :disabled="savingSeo">{{ savingSeo ? 'Saving…' : 'Save SEO' }}</button>
        </form>
        <p v-if="seoError" class="error">{{ seoError }}</p>
      </section>

      <section>
        <h2>Preview</h2>
        <p class="hint">
          Renders the draft's sections against the store's active theme, with
          schema defaults merged in — nothing is persisted.
        </p>
        <div class="actions">
          <button type="button" :disabled="previewing" @click="handlePreview">
            {{ previewing ? 'Rendering…' : 'Render preview' }}
          </button>
        </div>
        <p v-if="previewError" class="error">{{ previewError }}</p>

        <template v-if="preview">
          <h3>Global settings</h3>
          <pre>{{ JSON.stringify(preview.globalSettings, null, 2) }}</pre>

          <h3>Sections</h3>
          <p v-if="!preview.sections.length">
            This page has no resolvable sections — its section list is empty, or
            every instance references a handle the active theme does not define.
          </p>
          <div v-for="(section, index) in preview.sections" :key="section.id ?? index" class="rendered">
            <h4>{{ section.name }} <code>{{ section.handle }}</code></h4>
            <pre>{{ JSON.stringify({ settings: section.settings, blocks: section.blocks }, null, 2) }}</pre>
          </div>
        </template>
      </section>
    </template>
    <p v-else-if="pending">Loading…</p>
  </div>
</template>

<script setup lang="ts">
import type { Page, PageVersion, RenderedPage, SeoMetadata } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const route = useRoute()
/**
 * Reactive, not read once at setup: Duplicate navigates to the copy's own
 * `/pages/{id}`, and vue-router reuses this component across a param-only
 * change without re-running setup — a captured id would go stale there.
 */
const pageId = computed(() => route.params.id as string)
const activeStore = useActiveStore()

const page = ref<Page | null>(null)
const versions = ref<PageVersion[]>([])
const sectionsDraft = ref('[]')
const sectionsError = ref<string | null>(null)
const seoForm = reactive<Record<keyof SeoMetadata, string>>({
  meta_title: '',
  meta_description: '',
  canonical_url: '',
  og_image: '',
})
const seoError = ref<string | null>(null)
const preview = ref<RenderedPage | null>(null)
const previewError = ref<string | null>(null)

const pending = ref(true)
const previewing = ref(false)
const publishing = ref(false)
const duplicating = ref(false)
const rollingBackId = ref<string | null>(null)
const savingSections = ref(false)
const savingSeo = ref(false)
const error = ref<string | null>(null)

/**
 * A page always has exactly one draft version — CreatePage opens one and
 * PublishPageVersion opens the next as it freezes the current one — so this
 * needs no "no draft yet" branch.
 */
const draftVersion = computed(() => versions.value.find(version => version.status === 'draft')!)

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  try {
    const [pageResponse, versionsResponse] = await Promise.all([
      useApi().pages.get(pageId.value),
      useApi().pages.versions(pageId.value),
    ])
    page.value = pageResponse.data
    versions.value = versionsResponse.data

    const draft = draftVersion.value
    sectionsDraft.value = JSON.stringify(draft.sections, null, 2)
    sectionsError.value = null

    const seoResponse = await useApi().pageVersions.seo.get(draft.id)
    for (const key of Object.keys(seoForm) as (keyof SeoMetadata)[]) {
      seoForm[key] = seoResponse.data[key] ?? ''
    }
    seoError.value = null
    preview.value = null
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

async function handlePublish() {
  publishing.value = true
  error.value = null
  try {
    await useApi().pages.publish(pageId.value)
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    publishing.value = false
  }
}

async function handleDuplicate() {
  duplicating.value = true
  error.value = null
  try {
    const response = await useApi().pages.duplicate(pageId.value)
    await navigateTo(`/pages/${response.data.id}`)
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    duplicating.value = false
  }
}

async function handleRollback(versionId: string) {
  rollingBackId.value = versionId
  error.value = null
  try {
    await useApi().pageVersions.rollback(versionId)
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    rollingBackId.value = null
  }
}

async function handleSaveSections() {
  sectionsError.value = null

  let sections: unknown
  try {
    sections = JSON.parse(sectionsDraft.value)
  } catch {
    sectionsError.value = 'Not valid JSON — fix the syntax and try again.'
    return
  }

  if (!Array.isArray(sections)) {
    sectionsError.value = 'Sections must be a JSON array of section instances.'
    return
  }

  savingSections.value = true
  try {
    const response = await useApi().pageVersions.updateSections(draftVersion.value.id, sections)
    sectionsDraft.value = JSON.stringify(response.data.sections, null, 2)
  } catch (e) {
    sectionsError.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    savingSections.value = false
  }
}

async function handleSaveSeo() {
  savingSeo.value = true
  seoError.value = null
  try {
    // Empty inputs clear the field rather than leave the old value —
    // the endpoint is a full upsert of all four columns, not a patch of
    // whichever keys happen to be non-empty.
    const response = await useApi().pageVersions.seo.update(draftVersion.value.id, {
      meta_title: seoForm.meta_title || null,
      meta_description: seoForm.meta_description || null,
      canonical_url: seoForm.canonical_url || null,
      og_image: seoForm.og_image || null,
    })
    for (const key of Object.keys(seoForm) as (keyof SeoMetadata)[]) {
      seoForm[key] = response.data[key] ?? ''
    }
  } catch (e) {
    seoError.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    savingSeo.value = false
  }
}

async function handlePreview() {
  previewing.value = true
  previewError.value = null
  try {
    const response = await useApi().pages.preview(pageId.value)
    preview.value = response.data
  } catch (e) {
    previewError.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    previewing.value = false
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
watch(pageId, load)
</script>

<style scoped>
.actions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  margin-bottom: 0.5rem;
}

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

textarea {
  width: 100%;
  font-family: monospace;
  font-size: var(--text-sm);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 0.5rem;
  margin-bottom: 0.5rem;
}

.grid textarea {
  font-family: inherit;
  margin-bottom: 0;
}

pre {
  overflow-x: auto;
  background: var(--color-bg);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 0.5rem;
  font-size: var(--text-sm);
}

.rendered h4 {
  margin-bottom: 0.25rem;
}
</style>
