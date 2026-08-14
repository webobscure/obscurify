<template>
  <div>
    <PageHeader :title="theme?.name ?? 'Theme'" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else-if="theme">
      <table class="kv">
        <tbody>
          <tr><th>Slug</th><td>{{ theme.slug }}</td></tr>
          <tr><th>Status</th><td>{{ theme.status }}</td></tr>
          <tr><th>Serving the storefront</th><td>{{ theme.is_active ? 'Yes' : 'No' }}</td></tr>
          <tr><th>Draft version</th><td>#{{ draftVersion.version_number }}</td></tr>
        </tbody>
      </table>

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
        <h2>Draft templates</h2>
        <p class="hint">
          Sections are edited as raw JSON — there is no endpoint yet that
          describes which section/block handles a theme defines or what fields
          they take, so the admin cannot generate a form for them.
        </p>

        <div v-for="template in templates" :key="template.id" class="template">
          <h3>{{ template.name }} <code>{{ template.type }}</code></h3>
          <textarea v-model="templateDrafts[template.type]" rows="8" spellcheck="false" />
          <div class="actions">
            <button type="button" :disabled="savingTemplateType === template.type" @click="handleSaveTemplate(template.type)">
              {{ savingTemplateType === template.type ? 'Saving…' : 'Save sections' }}
            </button>
          </div>
          <p v-if="templateErrors[template.type]" class="error">{{ templateErrors[template.type] }}</p>
        </div>
      </section>

      <section>
        <h2>Draft settings</h2>
        <p class="hint">Global theme settings for the draft version, as one flat key → value map.</p>
        <textarea v-model="settingsDraft" rows="8" spellcheck="false" />
        <div class="actions">
          <button type="button" :disabled="savingSettings" @click="handleSaveSettings">
            {{ savingSettings ? 'Saving…' : 'Save settings' }}
          </button>
        </div>
        <p v-if="settingsError" class="error">{{ settingsError }}</p>
      </section>

      <section>
        <h2>Preview</h2>
        <p class="hint">Renders the draft version with schema defaults merged in — never the live theme.</p>
        <div class="actions">
          <select v-model="previewTemplate">
            <option v-for="type in themeTemplateTypes" :key="type" :value="type">{{ type }}</option>
          </select>
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
            This template has no resolvable sections — its section list is empty, or
            every instance references a handle this theme does not define.
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
import type { RenderedPage, Theme, ThemeTemplate, ThemeTemplateType, ThemeVersion } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'
import { themeTemplateTypes } from '@obscurify/types'

const route = useRoute()
/**
 * Reactive, not read once at setup: Duplicate navigates to the copy's own
 * `/themes/{id}`, and vue-router reuses this component across a param-only
 * change without re-running setup — a captured id would go stale there.
 */
const themeId = computed(() => route.params.id as string)
const activeStore = useActiveStore()

const theme = ref<Theme | null>(null)
const versions = ref<ThemeVersion[]>([])
const templates = ref<ThemeTemplate[]>([])
const templateDrafts = reactive<Record<string, string>>({})
const templateErrors = reactive<Record<string, string | null>>({})
const settingsDraft = ref('{}')
const settingsError = ref<string | null>(null)
const preview = ref<RenderedPage | null>(null)
const previewTemplate = ref<ThemeTemplateType>('home')
const previewError = ref<string | null>(null)

const pending = ref(true)
const previewing = ref(false)
const publishing = ref(false)
const duplicating = ref(false)
const rollingBackId = ref<string | null>(null)
const savingTemplateType = ref<ThemeTemplateType | null>(null)
const savingSettings = ref(false)
const error = ref<string | null>(null)

/**
 * A theme always has exactly one draft version — CreateTheme opens one and
 * PublishThemeVersion opens the next as it freezes the current one — so this
 * needs no "no draft yet" branch.
 */
const draftVersion = computed(() => versions.value.find(version => version.status === 'draft')!)

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  try {
    const [themeResponse, versionsResponse] = await Promise.all([
      useApi().themes.get(themeId.value),
      useApi().themes.versions(themeId.value),
    ])
    theme.value = themeResponse.data
    versions.value = versionsResponse.data

    const draftId = draftVersion.value.id
    const [templatesResponse, settingsResponse] = await Promise.all([
      useApi().themeVersions.templates.list(draftId),
      useApi().themeVersions.settings.list(draftId),
    ])

    templates.value = templatesResponse.data
    for (const template of templates.value) {
      templateDrafts[template.type] = JSON.stringify(template.sections, null, 2)
      templateErrors[template.type] = null
    }
    settingsDraft.value = JSON.stringify(settingsResponse.data, null, 2)
    settingsError.value = null
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
    await useApi().themes.publish(themeId.value)
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
    const response = await useApi().themes.duplicate(themeId.value)
    await navigateTo(`/themes/${response.data.id}`)
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
    await useApi().themeVersions.rollback(versionId)
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    rollingBackId.value = null
  }
}

async function handleSaveTemplate(type: ThemeTemplateType) {
  templateErrors[type] = null

  let sections: unknown
  try {
    sections = JSON.parse(templateDrafts[type] ?? '')
  } catch {
    templateErrors[type] = 'Not valid JSON — fix the syntax and try again.'
    return
  }

  if (!Array.isArray(sections)) {
    templateErrors[type] = 'Sections must be a JSON array of section instances.'
    return
  }

  savingTemplateType.value = type
  try {
    const response = await useApi().themeVersions.templates.update(draftVersion.value.id, type, sections)
    templateDrafts[type] = JSON.stringify(response.data.sections, null, 2)
  } catch (e) {
    templateErrors[type] = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    savingTemplateType.value = null
  }
}

async function handleSaveSettings() {
  settingsError.value = null

  let settings: unknown
  try {
    settings = JSON.parse(settingsDraft.value)
  } catch {
    settingsError.value = 'Not valid JSON — fix the syntax and try again.'
    return
  }

  if (typeof settings !== 'object' || settings === null || Array.isArray(settings)) {
    settingsError.value = 'Settings must be a JSON object of key → value pairs.'
    return
  }

  savingSettings.value = true
  try {
    const response = await useApi().themeVersions.settings.update(draftVersion.value.id, settings as Record<string, unknown>)
    settingsDraft.value = JSON.stringify(response.data, null, 2)
  } catch (e) {
    settingsError.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    savingSettings.value = false
  }
}

async function handlePreview() {
  previewing.value = true
  previewError.value = null
  try {
    const response = await useApi().themes.preview(themeId.value, previewTemplate.value)
    preview.value = response.data
  } catch (e) {
    previewError.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    previewing.value = false
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
watch(themeId, load)
</script>

<style scoped>
.actions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  margin-bottom: 0.5rem;
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin: 0 0 0.5rem;
}

.template {
  margin-bottom: 1rem;
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
