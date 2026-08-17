<template>
  <button
    type="button"
    class="save-state"
    :class="state"
    data-testid="save-state"
    :data-save-state="state"
    :disabled="!canSave"
    @click="canSave && emit('save')"
  >
    <Spinner v-if="state === 'saving'" size="sm" />
    <span v-else class="dot" />
    <span class="label" aria-live="polite">{{ label }}</span>
    <span v-if="state === 'dirty'" class="kbd">⌘S</span>
  </button>
</template>

<script setup lang="ts">
export type SaveState = 'idle' | 'dirty' | 'saving' | 'saved' | 'error'

const props = defineProps<{ state: SaveState }>()
const emit = defineEmits<{ save: [] }>()
const { t } = useI18n()

// Only a dirty draft or a failed save is actually clickable — "no
// changes" (idle/saving/saved) must not offer a Save action at all
// (spec: "Save disabled when there are no changes").
const canSave = computed(() => props.state === 'dirty' || props.state === 'error')

const label = computed(() => {
  switch (props.state) {
    case 'dirty': return t('productEditor.save_dirty')
    case 'saving': return t('productEditor.save_saving')
    case 'error': return t('productEditor.save_error')
    default: return t('productEditor.save_saved')
  }
})
</script>

<style scoped>
.save-state {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  font-size: var(--text-xs);
  color: var(--color-text-subtle);
  padding: var(--space-1) var(--space-2);
  border-radius: var(--radius-sm);
}

.save-state:not(:disabled):hover {
  background: var(--color-surface-muted);
  color: var(--color-text);
}

.save-state:disabled {
  cursor: default;
}

.dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--color-text-subtle);
  flex-shrink: 0;
}

.save-state.dirty .dot { background: var(--color-warning); }
.save-state.saved .dot { background: var(--color-success); }
.save-state.error .dot { background: var(--color-danger); }
.save-state.error { color: var(--color-danger); }

.kbd {
  font-family: ui-monospace, 'SF Mono', Menlo, monospace;
  font-size: 10px;
  color: var(--color-text-subtle);
  background: var(--color-surface-muted);
  border: 1px solid var(--color-border);
  border-radius: 4px;
  padding: 0 4px;
}
</style>
