<template>
  <div
    class="media-wrap"
    :class="{ dragging: isDragging }"
    @dragenter.prevent="onDragEnter"
    @dragover.prevent
    @dragleave="onDragLeave"
    @drop.prevent="onDrop"
  >
    <div v-if="!items.length" class="dropzone">
      <Spinner v-if="uploading" size="md" />
      <template v-else>
        <AppIcon name="upload" class="dropzone-icon" />
        <p class="dropzone-title">{{ t('productEditor.empty_media_title') }}</p>
        <p class="dropzone-desc">{{ t('productEditor.empty_media_description') }}</p>
        <p class="dropzone-hint">
          {{ t('productEditor.media_drop_hint') }}
          <label class="dropzone-btn">
            {{ t('productEditor.media_upload') }}
            <input type="file" accept="image/*" class="file-input" data-testid="media-upload-input" :disabled="uploading" @change="onFileChange">
          </label>
        </p>
      </template>
    </div>

    <div v-else class="media-grid">
      <figure v-for="(item, index) in items" :key="item.id" class="tile" data-testid="media-tile">
        <img :src="item.url" :alt="item.alt ?? ''">
        <span v-if="index === 0" class="primary-flag">{{ t('productEditor.media_primary') }}</span>
        <span v-if="!item.alt" class="alt-flag">{{ t('productEditor.media_alt_missing') }}</span>

        <div class="controls">
          <button
            type="button"
            class="ctrl"
            :disabled="index === 0"
            :aria-label="t('productEditor.media_move_left')"
            @click="emit('reorder', item.id, -1)"
          >
            ←
          </button>
          <button
            type="button"
            class="ctrl"
            :disabled="index === items.length - 1"
            :aria-label="t('productEditor.media_move_right')"
            @click="emit('reorder', item.id, 1)"
          >
            →
          </button>
          <button type="button" class="ctrl danger" :aria-label="t('common.delete')" @click="emit('remove', item.id)">
            ✕
          </button>
        </div>

        <input
          class="alt-input"
          type="text"
          :value="item.alt ?? ''"
          :placeholder="t('productEditor.media_alt_placeholder')"
          :aria-label="t('productEditor.media_alt_for', { n: index + 1 })"
          @change="emit('updateAlt', item.id, ($event.target as HTMLInputElement).value)"
        >
      </figure>

      <label class="tile add" :class="{ uploading }">
        <Spinner v-if="uploading" size="md" />
        <template v-else>
          <AppIcon name="upload" aria-hidden="true" />
          <span class="add-label">{{ t('productEditor.media_add') }}</span>
        </template>
        <input type="file" accept="image/*" class="file-input" data-testid="media-upload-input" :disabled="uploading" @change="onFileChange">
      </label>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Media } from '@obscurify/types'

const props = defineProps<{ items: Media[]; uploading?: boolean }>()
const emit = defineEmits<{
  upload: [File]
  remove: [string]
  reorder: [string, -1 | 1]
  updateAlt: [string, string]
}>()
const { t } = useI18n()

const isDragging = ref(false)
// Nested child elements fire their own dragenter/dragleave as the pointer
// crosses them, which would otherwise flicker `isDragging` off mid-drag —
// counting enter/leave pairs (instead of a boolean) keeps the dropzone
// highlighted for the whole time a drag is anywhere over the wrapper.
let dragDepth = 0

function onDragEnter() {
  dragDepth += 1
  isDragging.value = true
}

function onDragLeave() {
  dragDepth = Math.max(0, dragDepth - 1)
  if (dragDepth === 0) isDragging.value = false
}

function onDrop(event: DragEvent) {
  dragDepth = 0
  isDragging.value = false
  if (props.uploading) return
  const file = event.dataTransfer?.files?.[0]
  if (file) emit('upload', file)
}

function onFileChange(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) emit('upload', file)
  ;(event.target as HTMLInputElement).value = ''
}
</script>

<style scoped>
.media-wrap {
  border-radius: var(--radius-md);
  transition: background-color var(--transition-fast);
}

.dropzone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  width: 100%;
  min-height: 176px;
  padding: var(--space-6) var(--space-4);
  border: 1px dashed var(--color-border-strong);
  border-radius: var(--radius-md);
  background: var(--color-surface-muted);
  transition: border-color var(--transition-fast), background-color var(--transition-fast);
}

.media-wrap.dragging .dropzone {
  border-color: var(--color-accent);
  background: var(--color-accent-bg);
}

.dropzone-icon {
  width: 28px;
  height: 28px;
  color: var(--color-text-subtle);
  margin-bottom: var(--space-2);
}

.dropzone-title {
  margin: 0;
  font-size: var(--text-base);
  font-weight: var(--font-weight-medium);
  color: var(--color-text);
}

.dropzone-desc {
  margin: 2px 0 0;
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.dropzone-hint {
  margin: var(--space-3) 0 0;
  font-size: var(--text-sm);
  color: var(--color-text-subtle);
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.dropzone-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  padding: var(--space-1) var(--space-3);
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border-strong);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: var(--text-sm);
  font-weight: var(--font-weight-medium);
  cursor: pointer;
}
.dropzone-btn:hover { background: var(--color-surface-muted); border-color: var(--color-accent); }

.media-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
  gap: var(--space-2);
}

.tile {
  position: relative;
  margin: 0;
  aspect-ratio: 1;
  border-radius: var(--radius-md);
  overflow: hidden;
  border: 1px solid var(--color-border);
  background: var(--color-surface-muted);
}

.tile img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

/* This block and every rgba(0,0,0,…)/#fff below it are a deliberate
   exception to "no hardcoded colors" — these chips/controls/scrims sit
   directly on top of an arbitrary merchant-uploaded photo, not on a
   themed app surface. A semantic token (e.g. --color-text, which flips
   between near-black and near-white across themes) would make these
   illegible against unpredictable photo content roughly half the time;
   a fixed dark scrim + white text stays readable regardless of both the
   current theme and the photo underneath. Not an oversight — see
   docs/design/THEMING.md section 10 (uploaded images are never
   theme-filtered; this is the same principle applied to their overlay
   chrome). */
.primary-flag,
.alt-flag {
  position: absolute;
  left: var(--space-1);
  bottom: var(--space-1);
  font-size: 9px;
  padding: 2px 6px;
  border-radius: var(--radius-sm);
  background: rgba(0, 0, 0, 0.6);
  color: #fff;
}
.alt-flag {
  bottom: auto;
  top: var(--space-1);
  background: var(--color-warning);
}

.controls {
  position: absolute;
  top: var(--space-1);
  right: var(--space-1);
  display: flex;
  gap: 2px;
  opacity: 0;
  transition: opacity var(--transition-fast);
}

.tile:hover .controls,
.tile:focus-within .controls {
  opacity: 1;
}

.ctrl {
  width: 20px;
  height: 20px;
  border-radius: var(--radius-sm);
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
  font-size: 11px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.ctrl:disabled { opacity: 0.4; }
.ctrl.danger:hover { background: var(--color-danger); }

.alt-input {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  border: none;
  padding: 2px var(--space-1);
  font-size: 9px;
  background: rgba(0, 0, 0, 0.55);
  color: #fff;
  opacity: 0;
  transition: opacity var(--transition-fast);
}
.tile:hover .alt-input,
.tile:focus-within .alt-input,
.alt-input:focus {
  opacity: 1;
}
.alt-input::placeholder { color: rgba(255, 255, 255, 0.7); }

.tile.add {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  color: var(--color-text-subtle);
  border-style: dashed;
  border-color: var(--color-border-strong);
  cursor: pointer;
  position: relative;
}
.tile.add :deep(svg) { width: 18px; height: 18px; }
.add-label {
  font-size: 10.5px;
  font-weight: var(--font-weight-medium);
  text-align: center;
  padding: 0 var(--space-1);
}
.tile.add:hover { background: var(--color-accent-bg); border-color: var(--color-accent); color: var(--color-accent); }
.tile.add.uploading { cursor: default; color: var(--color-text-muted); }

.file-input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}
</style>
