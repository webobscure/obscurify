<template>
  <div>
    <PageHeader
      :title="state ? `Builder — ${state.title}` : 'Builder'"
      :breadcrumbs="[{ label: 'Pages', to: '/pages' }, { label: state?.title ?? 'Page', to: `/pages/${pageId}` }, { label: 'Builder' }]"
    />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else-if="state">
      <div class="toolbar">
        <button type="button" :disabled="!state.can_undo || busy" @click="handleUndo">Undo</button>
        <button type="button" :disabled="!state.can_redo || busy" @click="handleRedo">Redo</button>

        <span class="spacer" />

        <div class="breakpoints" role="group" aria-label="Preview width">
          <button
            v-for="option in breakpoints"
            :key="option.key"
            type="button"
            :class="{ active: activeBreakpoint === option.key }"
            @click="activeBreakpoint = option.key"
          >
            {{ option.label }}
          </button>
        </div>

        <span class="spacer" />

        <span class="save-state">{{ saveState }}</span>
        <button type="button" :disabled="saving" @click="saveNow">Save</button>
        <button type="button" :disabled="publishing" @click="handlePublish">
          {{ publishing ? 'Publishing…' : 'Publish' }}
        </button>
        <button type="button" :disabled="duplicating" @click="handleDuplicate">
          {{ duplicating ? 'Duplicating…' : 'Duplicate' }}
        </button>
      </div>

      <p v-if="error" class="error">{{ error }}</p>

      <div class="columns">
        <section class="canvas">
          <h2>Sections</h2>
          <p class="hint">
            Drag a card to reorder. Blocks reorder among their own siblings
            only — moving a block to a different nesting level is not
            supported yet.
          </p>

          <BuilderPresetPicker
            v-if="picker?.kind === 'section'"
            title="Add a section"
            :presets="sectionPresets"
            @pick="handlePick"
            @close="picker = null"
          />
          <button v-else type="button" @click="picker = { kind: 'section' }">Add section</button>

          <p v-if="!sections.length" class="hint">This page has no sections yet.</p>

          <article
            v-for="(section, index) in sections"
            :key="section.id"
            class="section-card"
            :class="{ selected: isSectionSelected(index) }"
            draggable="true"
            @dragstart.stop="sectionDragIndex = index"
            @dragend.stop="sectionDragIndex = null"
            @dragover.prevent.stop
            @drop.prevent.stop="handleSectionDrop(index)"
          >
            <header>
              <span class="grip" aria-hidden="true">⠿</span>
              <button type="button" class="select" @click="selectedPath = [index]">
                {{ titleCase(section.section_handle) }}
              </button>
              <code>{{ section.section_handle }}</code>
              <span class="spacer" />
              <button type="button" @click="duplicateSection(index)">Duplicate</button>
              <button type="button" @click="removeSection(index)">Delete</button>
            </header>

            <BuilderBlockTree
              v-if="section.blocks.length"
              :blocks="section.blocks"
              :path="[index]"
              :selected-path="selectedPath"
              @select="selectedPath = $event"
              @add-nested="picker = { kind: 'block', into: $event }"
              @duplicate="duplicateBlock"
              @remove="removeBlock"
              @reorder="reorderBlocks"
            />

            <BuilderPresetPicker
              v-if="picker?.kind === 'block' && picker.into === section.blocks"
              title="Add a block"
              :presets="blockPresets"
              @pick="handlePick"
              @close="picker = null"
            />
            <button v-else type="button" class="add-block" @click="picker = { kind: 'block', into: section.blocks }">
              Add block
            </button>
          </article>
        </section>

        <aside class="details">
          <h2>Settings</h2>
          <p v-if="!selectedItem" class="hint">Select a section or block to edit its settings.</p>
          <template v-else>
            <h3><code>{{ selectedHandle }}</code></h3>
            <p class="hint">
              Settings are edited as raw JSON — there is no endpoint that
              describes which fields a section or block handle takes, so the
              admin cannot generate a form for them.
            </p>
            <textarea v-model="settingsDraft" rows="16" spellcheck="false" />
            <div class="actions">
              <button type="button" @click="applySettings">Apply</button>
            </div>
            <p v-if="settingsError" class="error">{{ settingsError }}</p>
          </template>
        </aside>
      </div>

      <section>
        <h2>Preview</h2>
        <p class="hint">
          Renders the saved draft against the store's active theme. This is a
          best-effort mock of each section, not real theme markup.
        </p>
        <p v-if="previewError" class="error">{{ previewError }}</p>
        <div class="viewport" :style="{ maxWidth: previewWidth }">
          <p v-if="!preview">Nothing rendered yet.</p>
          <p v-else-if="!preview.sections.length">
            No resolvable sections — every instance references a handle the
            active theme does not define.
          </p>
          <BuilderSectionPreview
            v-for="(rendered, index) in preview?.sections ?? []"
            :key="rendered.id ?? index"
            :label="rendered.name"
            :handle="rendered.handle"
            :settings="rendered.settings"
            :blocks="rendered.blocks"
          />
        </div>
      </section>

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
        <h2>Revisions</h2>
        <p class="hint">Every save — autosave included — appends one revision.</p>
        <table v-if="revisions.length">
          <thead>
            <tr><th>Step</th><th>Saved</th><th /></tr>
          </thead>
          <tbody>
            <tr v-for="revision in revisions" :key="revision.id">
              <td>#{{ revision.sequence }} <span v-if="revision.is_current" class="badge">current</span></td>
              <td>{{ revision.created_at }}</td>
              <td>
                <button type="button" :disabled="restoringId === revision.id" @click="handleRestore(revision.id)">
                  {{ restoringId === revision.id ? 'Restoring…' : 'Restore' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="hint">No revisions yet.</p>
      </section>
    </template>
    <p v-else-if="pending">Loading…</p>
  </div>
</template>

<script setup lang="ts">
import type { BlockInstance, BuilderPageState, BuilderPreset, BuilderRevisionSummary, PageVersion, RenderedPage, SectionInstance } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

/**
 * The visual page builder. All structural editing happens client-side
 * against the one reactive `sections` array — there is deliberately no
 * per-operation endpoint — and the whole document is PATCHed back on an
 * autosave debounce (see SaveBuilderLayout's docblock).
 *
 * Every server response is treated as canonical and replaces local state
 * wholesale: the backend re-derives the array from a relational
 * round-trip that silently drops handles the active theme does not
 * define, so what was sent is not always what is stored.
 */
const route = useRoute()
/** Reactive: Duplicate navigates to the copy's own builder route, which vue-router serves by reusing this component. */
const pageId = computed(() => route.params.id as string)
const activeStore = useActiveStore()

const breakpoints = [
  { key: 'desktop', label: 'Desktop', width: '100%' },
  { key: 'tablet', label: 'Tablet', width: '768px' },
  { key: 'mobile', label: 'Mobile', width: '375px' },
] as const

type BreakpointKey = typeof breakpoints[number]['key']

const state = ref<BuilderPageState | null>(null)
const sections = ref<SectionInstance[]>([])
const versions = ref<PageVersion[]>([])
const revisions = ref<BuilderRevisionSummary[]>([])
const sectionPresets = ref<BuilderPreset[]>([])
const blockPresets = ref<BuilderPreset[]>([])
const preview = ref<RenderedPage | null>(null)

const activeBreakpoint = ref<BreakpointKey>('desktop')
const previewWidth = computed(() => breakpoints.find(option => option.key === activeBreakpoint.value)!.width)

/**
 * Selection is an index path — `[sectionIndex]` for a section,
 * `[sectionIndex, ...blockIndices]` for a block — not an id.
 * ReplaceSectionInstancesFromArray deletes and recreates every row on
 * save, so ids change on every round-trip while positions survive
 * exactly; an id-based selection would be lost on each autosave.
 */
const selectedPath = ref<number[] | null>(null)
const settingsDraft = ref('{}')
const settingsError = ref<string | null>(null)

const picker = ref<{ kind: 'section' } | { kind: 'block', into: BlockInstance[] } | null>(null)

const pending = ref(true)
const saving = ref(false)
const publishing = ref(false)
const duplicating = ref(false)
const rollingBackId = ref<string | null>(null)
const restoringId = ref<string | null>(null)
const savedAt = ref<string | null>(null)
const error = ref<string | null>(null)
const previewError = ref<string | null>(null)
const sectionDragIndex = ref<number | null>(null)

const busy = computed(() => saving.value || publishing.value || duplicating.value || restoringId.value !== null)
const saveState = computed(() => {
  if (saving.value) return 'Saving…'
  return savedAt.value ? `Saved ${savedAt.value}` : 'Not saved yet'
})

const selectedItem = computed<SectionInstance | BlockInstance | null>(() => {
  const path = selectedPath.value
  if (!path?.length) return null

  const section = sections.value[path[0]!]
  if (!section) return null

  let current: SectionInstance | BlockInstance = section
  for (const index of path.slice(1)) {
    const next: BlockInstance | undefined = current.blocks[index]
    if (!next) return null
    current = next
  }

  return current
})

const selectedHandle = computed(() => {
  const item = selectedItem.value
  if (!item) return ''

  return 'section_handle' in item ? item.section_handle : item.block_handle
})

function titleCase(handle: string): string {
  return handle.split('-').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')
}

function isSectionSelected(index: number): boolean {
  return selectedPath.value?.length === 1 && selectedPath.value[0] === index
}

/**
 * Deep-clones an instance subtree with fresh ids so a duplicate is a
 * genuinely separate node. The ids are throwaway — the backend assigns
 * its own on save — but Vue needs them unique as `:key` until then.
 */
function cloneBlock(block: BlockInstance): BlockInstance {
  return {
    id: crypto.randomUUID(),
    block_handle: block.block_handle,
    settings: structuredClone(toRaw(block.settings)),
    blocks: block.blocks.map(cloneBlock),
  }
}

function moveInList<T>(list: T[], from: number, to: number) {
  const [moved] = list.splice(from, 1)
  if (moved !== undefined) list.splice(to, 0, moved)
}

/* ── Local mutations ─────────────────────────────────────────────── */

function handlePick(preset: BuilderPreset) {
  const target = picker.value
  if (!target) return

  // preset comes from a reactive ref's items — structuredClone throws
  // DataCloneError on a Vue reactive Proxy, same reason cloneBlock/
  // duplicateSection below unwrap with toRaw() first.
  const settings = structuredClone(toRaw(preset.settings))

  if (target.kind === 'section') {
    sections.value.push({ id: crypto.randomUUID(), section_handle: preset.handle, settings, blocks: [] })
  } else {
    target.into.push({ id: crypto.randomUUID(), block_handle: preset.handle, settings, blocks: [] })
  }

  picker.value = null
}

function duplicateSection(index: number) {
  const section = sections.value[index]
  if (!section) return

  sections.value.splice(index + 1, 0, {
    id: crypto.randomUUID(),
    section_handle: section.section_handle,
    settings: structuredClone(toRaw(section.settings)),
    blocks: section.blocks.map(cloneBlock),
  })
}

function removeSection(index: number) {
  sections.value.splice(index, 1)
  selectedPath.value = null
}

function handleSectionDrop(index: number) {
  const from = sectionDragIndex.value
  sectionDragIndex.value = null

  if (from === null || from === index) return

  moveInList(sections.value, from, index)
  selectedPath.value = null
}

function duplicateBlock({ blocks, index }: { blocks: BlockInstance[], index: number }) {
  const block = blocks[index]
  if (block) blocks.splice(index + 1, 0, cloneBlock(block))
}

function removeBlock({ blocks, index }: { blocks: BlockInstance[], index: number }) {
  blocks.splice(index, 1)
  selectedPath.value = null
}

function reorderBlocks({ blocks, from, to }: { blocks: BlockInstance[], from: number, to: number }) {
  moveInList(blocks, from, to)
  selectedPath.value = null
}

function applySettings() {
  const item = selectedItem.value
  if (!item) return

  let parsed: unknown
  try {
    parsed = JSON.parse(settingsDraft.value)
  } catch {
    settingsError.value = 'Not valid JSON — fix the syntax and try again.'
    return
  }

  if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
    settingsError.value = 'Settings must be a JSON object.'
    return
  }

  settingsError.value = null
  item.settings = parsed as Record<string, unknown>
}

/* ── Server round-trip ───────────────────────────────────────────── */

/**
 * Set while local state is being replaced from a server response, so the
 * autosave watcher does not treat a hydration as a user edit and save it
 * straight back — that would loop forever.
 */
let hydrating = false

function hydrate(next: BuilderPageState) {
  hydrating = true
  state.value = next
  sections.value = next.sections
  nextTick(() => {
    hydrating = false
  })
}

/**
 * A local two-line debounce rather than a dependency — this app has no
 * lodash and one timer does not justify adding it. `cancel` matters:
 * an autosave still pending when undo/redo/restore lands would re-save
 * the state the user just moved away from.
 */
function debounce(fn: () => void, delay: number) {
  let timer: ReturnType<typeof setTimeout> | undefined

  return Object.assign(() => {
    clearTimeout(timer)
    timer = setTimeout(fn, delay)
  }, { cancel: () => clearTimeout(timer) })
}

async function save() {
  if (saving.value) return

  saving.value = true
  error.value = null
  try {
    const response = await useApi().builder.pages.update(pageId.value, toRaw(sections.value))
    hydrate(response.data)
    savedAt.value = new Date().toLocaleTimeString()
    await Promise.all([loadRevisions(), loadPreview()])
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    saving.value = false
  }
}

const scheduleSave = debounce(() => void save(), 2000)

function saveNow() {
  scheduleSave.cancel()
  void save()
}

async function loadRevisions() {
  const response = await useApi().builder.revisions.list(pageId.value)
  revisions.value = response.data
}

async function loadPreview() {
  previewError.value = null
  try {
    const response = await useApi().pages.preview(pageId.value)
    preview.value = response.data
  } catch (e) {
    previewError.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

async function load() {
  if (!activeStore.storeId.value) return

  pending.value = true
  error.value = null
  scheduleSave.cancel()
  selectedPath.value = null
  picker.value = null
  try {
    const [pageResponse, versionsResponse, sectionPresetResponse, blockPresetResponse] = await Promise.all([
      useApi().builder.pages.get(pageId.value),
      useApi().pages.versions(pageId.value),
      useApi().builder.presets.list('section'),
      useApi().builder.presets.list('block'),
    ])

    hydrate(pageResponse.data)
    versions.value = versionsResponse.data
    sectionPresets.value = sectionPresetResponse.data
    blockPresets.value = blockPresetResponse.data

    await Promise.all([loadRevisions(), loadPreview()])
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    pending.value = false
  }
}

async function handleUndo() {
  scheduleSave.cancel()
  error.value = null
  try {
    const response = await useApi().builder.pages.undo(pageId.value)
    hydrate(response.data)
    selectedPath.value = null
    await Promise.all([loadRevisions(), loadPreview()])
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

async function handleRedo() {
  scheduleSave.cancel()
  error.value = null
  try {
    const response = await useApi().builder.pages.redo(pageId.value)
    hydrate(response.data)
    selectedPath.value = null
    await Promise.all([loadRevisions(), loadPreview()])
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  }
}

/**
 * Restore answers with only `{draft_version_id, sections}` — no
 * `can_undo`/`can_redo` — so the page state is re-read rather than
 * hydrated from the restore response, which would leave the undo/redo
 * buttons stale.
 */
async function handleRestore(revisionId: string) {
  restoringId.value = revisionId
  scheduleSave.cancel()
  error.value = null
  try {
    await useApi().builder.revisions.restore(pageId.value, revisionId)
    const response = await useApi().builder.pages.get(pageId.value)
    hydrate(response.data)
    selectedPath.value = null
    await Promise.all([loadRevisions(), loadPreview()])
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    restoringId.value = null
  }
}

async function handlePublish() {
  publishing.value = true
  scheduleSave.cancel()
  error.value = null
  try {
    // Publishing freezes the current draft and opens the next one, so the
    // whole page — new draft id, versions, revisions — has to be re-read.
    await useApi().builder.pages.publish(pageId.value)
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
    const response = await useApi().builder.pages.duplicate(pageId.value)
    await navigateTo(`/builder/pages/${response.data.id}`)
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    duplicating.value = false
  }
}

async function handleRollback(versionId: string) {
  rollingBackId.value = versionId
  scheduleSave.cancel()
  error.value = null
  try {
    await useApi().builder.pages.rollback(pageId.value, versionId)
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    rollingBackId.value = null
  }
}

/** Autosave: any structural or settings edit schedules one PATCH 2s later. */
watch(sections, () => {
  if (hydrating || pending.value) return
  scheduleSave()
}, { deep: true })

/**
 * Re-reads the textarea only when the *selection* moves, never when the
 * array is rehydrated under an unchanged path — otherwise an autosave
 * landing mid-edit would wipe what the merchant is typing.
 */
watch(() => selectedPath.value?.join('.') ?? null, () => {
  settingsDraft.value = JSON.stringify(selectedItem.value?.settings ?? {}, null, 2)
  settingsError.value = null
})

onBeforeUnmount(() => scheduleSave.cancel())
onMounted(load)
watch(() => activeStore.storeId.value, load)
watch(pageId, load)
</script>

<style scoped>
.toolbar {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex-wrap: wrap;
  padding: var(--space-3);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  margin-bottom: var(--space-4);
}

.spacer {
  flex: 1;
}

.breakpoints {
  display: flex;
  gap: var(--space-1);
}

.breakpoints .active {
  background: var(--color-accent-bg);
  border-color: var(--color-accent);
}

.save-state {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
}

.columns {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
  gap: var(--space-4);
  align-items: start;
  margin-bottom: var(--space-6);
}

.section-card {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  padding: var(--space-3);
  margin-top: var(--space-3);
}

.section-card.selected {
  border-color: var(--color-accent);
}

.section-card header {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin-bottom: var(--space-2);
}

.grip {
  cursor: grab;
  color: var(--color-text-subtle);
}

.select {
  background: none;
  border: 0;
  padding: 0;
  cursor: pointer;
  color: inherit;
  font-weight: var(--font-weight-semibold);
}

.add-block {
  margin-top: var(--space-2);
}

.details {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  padding: var(--space-3);
  position: sticky;
  top: var(--space-4);
}

.details h2,
.details h3 {
  margin-top: 0;
}

.viewport {
  margin: 0 auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  background: var(--color-surface);
  padding: var(--space-3);
}

.actions {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin: 0 0 0.5rem;
}

.badge {
  font-size: var(--text-xs);
  background: var(--color-success-bg);
  color: var(--color-success);
  border-radius: var(--radius-full);
  padding: 0 var(--space-2);
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
</style>
