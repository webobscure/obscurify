<template>
  <div>
    <PageHeader title="Theme Customizer" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else-if="state">
      <p class="hint">
        Every field here is a plain theme setting on the active theme's
        current draft — the same mechanism the storefront already reads as
        <code>globalSettings</code>. Saving publishes nothing by itself;
        publish the theme separately to make changes live.
      </p>
      <p v-if="error" class="error">{{ error }}</p>

      <form class="groups" @submit.prevent="handleSave">
        <fieldset v-for="group in groups" :key="group.name">
          <legend>{{ group.name }}</legend>

          <div v-for="field in group.fields" :key="field.key" class="field">
            <label :for="field.key">{{ field.label }}</label>

            <input
              v-if="field.type === 'color'"
              :id="field.key"
              type="color"
              :value="stringValue(field.key) || '#000000'"
              @input="setValue(field.key, ($event.target as HTMLInputElement).value)"
            >

            <div v-else-if="field.type === 'range'" class="range">
              <input
                :id="field.key"
                type="range"
                min="0"
                max="100"
                :value="stringValue(field.key) || '0'"
                @input="setValue(field.key, ($event.target as HTMLInputElement).value)"
              >
              <span>{{ stringValue(field.key) || '0' }}</span>
            </div>

            <input
              v-else-if="field.type === 'boolean'"
              :id="field.key"
              type="checkbox"
              :checked="boolValue(field.key)"
              @change="setValue(field.key, ($event.target as HTMLInputElement).checked)"
            >

            <textarea
              v-else-if="field.type === 'richtext'"
              :id="field.key"
              rows="4"
              :value="stringValue(field.key)"
              @input="setValue(field.key, ($event.target as HTMLTextAreaElement).value)"
            />

            <select
              v-else-if="field.type === 'select'"
              :id="field.key"
              :value="stringValue(field.key)"
              @change="setValue(field.key, ($event.target as HTMLSelectElement).value)"
            >
              <option value="">—</option>
              <option v-for="option in selectOptions(field.key)" :key="option" :value="option">{{ option }}</option>
            </select>

            <div v-else-if="field.type === 'image'" class="image-field">
              <input
                type="text"
                placeholder="Asset URL"
                :value="stringValue(field.key)"
                @input="setValue(field.key, ($event.target as HTMLInputElement).value)"
              >
              <img v-if="stringValue(field.key)" :src="stringValue(field.key)" alt="" class="thumb" >
              <button type="button" @click="pickerForKey = field.key">Choose asset</button>
            </div>

            <!-- font / text / url -->
            <input
              v-else
              :id="field.key"
              type="text"
              :value="stringValue(field.key)"
              @input="setValue(field.key, ($event.target as HTMLInputElement).value)"
            >
          </div>
        </fieldset>

        <button type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save' }}</button>
      </form>

      <div v-if="pickerForKey" class="picker">
        <div class="head">
          <strong>Choose an asset</strong>
          <button type="button" @click="pickerForKey = null">Close</button>
        </div>

        <ul v-if="assets.length">
          <li v-for="asset in assets" :key="asset.id">
            <button type="button" class="asset" @click="selectAsset(asset)">
              <img :src="asset.url" alt="" class="thumb" >
              <span>{{ asset.url.split('/').pop() }}</span>
            </button>
          </li>
        </ul>
        <p v-else class="hint">No assets uploaded yet.</p>

        <form class="upload" @submit.prevent="handleUpload">
          <input type="file" accept="image/*" @change="onFileChange">
          <button type="submit" :disabled="uploading || !pendingFile">{{ uploading ? 'Uploading…' : 'Upload' }}</button>
        </form>
        <p v-if="uploadError" class="error">{{ uploadError }}</p>
      </div>
    </template>
    <p v-else-if="pending">Loading…</p>
  </div>
</template>

<script setup lang="ts">
import type { ThemeAsset, ThemeCustomizerState } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const activeStore = useActiveStore()

const state = ref<ThemeCustomizerState | null>(null)
const values = reactive<Record<string, unknown>>({})
const assets = ref<ThemeAsset[]>([])
const pickerForKey = ref<string | null>(null)
const pendingFile = ref<File | null>(null)

const pending = ref(true)
const saving = ref(false)
const uploading = ref(false)
const error = ref<string | null>(null)
const uploadError = ref<string | null>(null)

const groups = computed(() => {
  const byName = new Map<string, typeof state.value extends null ? never : ThemeCustomizerState['schema']>()
  for (const field of state.value?.schema ?? []) {
    if (!byName.has(field.group)) byName.set(field.group, [])
    byName.get(field.group)!.push(field)
  }

  return Array.from(byName, ([name, fields]) => ({ name, fields }))
})

function stringValue(key: string): string {
  const value = values[key]
  if (typeof value === 'string') return value
  return value == null ? '' : String(value)
}

function boolValue(key: string): boolean {
  return values[key] === true || values[key] === 'true' || values[key] === '1'
}

function setValue(key: string, value: unknown) {
  values[key] = value
}

/**
 * There is no options list in the schema (`ThemeCustomizerSchema` only
 * carries field metadata, not per-select choices) — these are sensible
 * hardcoded defaults per known key, a judgment call, not something the
 * backend dictates. An unrecognized `select` field just gets an empty
 * option list plus its raw text value preserved.
 */
function selectOptions(key: string): string[] {
  const known: Record<string, string[]> = {
    button_style: ['solid', 'outline', 'ghost'],
    header_layout: ['logo-left', 'logo-center', 'logo-right'],
  }

  return known[key] ?? []
}

async function load() {
  if (!activeStore.storeId.value) return
  pending.value = true
  error.value = null
  try {
    const response = await useApi().builder.themeCustomizer()
    state.value = response.data

    for (const key of Object.keys(values)) Reflect.deleteProperty(values, key)
    Object.assign(values, response.data.values)

    const assetsResponse = await useApi().themeAssets.list(response.data.theme_version_id)
    assets.value = assetsResponse.data
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

async function handleSave() {
  if (!state.value) return

  saving.value = true
  error.value = null
  try {
    await useApi().themeVersions.settings.update(state.value.theme_version_id, { ...values })
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    saving.value = false
  }
}

function selectAsset(asset: ThemeAsset) {
  if (pickerForKey.value) setValue(pickerForKey.value, asset.url)
  pickerForKey.value = null
}

function onFileChange(event: Event) {
  pendingFile.value = (event.target as HTMLInputElement).files?.[0] ?? null
}

async function handleUpload() {
  if (!pendingFile.value || !state.value) return

  uploading.value = true
  uploadError.value = null
  try {
    const response = await useApi().themeAssets.upload(state.value.theme_version_id, pendingFile.value, 'image')
    assets.value.unshift(response.data)
    pendingFile.value = null
  } catch (e) {
    uploadError.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    uploading.value = false
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

.groups {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  max-width: 640px;
}

fieldset {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  padding: var(--space-3);
}

legend {
  font-weight: var(--font-weight-semibold);
  padding: 0 var(--space-2);
}

.field {
  display: grid;
  grid-template-columns: 160px 1fr;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-2);
}

.field label {
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.range {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.image-field {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.thumb {
  width: 32px;
  height: 32px;
  object-fit: cover;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
}

.picker {
  margin-top: var(--space-4);
  max-width: 480px;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-md);
  padding: var(--space-3);
  background: var(--color-surface);
}

.head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-2);
}

.picker ul {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: var(--space-2);
}

.asset {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-1);
  width: 100%;
}

.asset span {
  font-size: var(--text-xs);
  overflow-wrap: anywhere;
}

.upload {
  display: flex;
  gap: var(--space-2);
  margin-top: var(--space-3);
}
</style>
