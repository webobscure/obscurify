<template>
  <Teleport to="body">
    <div v-if="open" class="scrim" @click.self="close">
      <div ref="panel" class="modal" :class="size" role="dialog" aria-modal="true" :aria-labelledby="titleId">
        <header class="head">
          <h2 :id="titleId">{{ title }}</h2>
          <button type="button" class="close" :aria-label="t('common.close')" @click="close">✕</button>
        </header>
        <div class="body"><slot /></div>
        <footer v-if="$slots.actions" class="foot"><slot name="actions" /></footer>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{ open: boolean; title: string; size?: 'sm' | 'md' | 'lg' }>(), { size: 'md' })
const emit = defineEmits<{ 'update:open': [boolean] }>()
const { t } = useI18n()

const panel = ref<HTMLElement | null>(null)
const isOpen = computed(() => props.open)
const titleId = useId()

function close() {
  emit('update:open', false)
}

useDismissable(panel, isOpen, close)
</script>

<style scoped>
.scrim {
  position: fixed;
  inset: 0;
  z-index: 100;
  background: var(--color-overlay);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
}

.modal {
  width: 100%;
  background: var(--color-surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  display: flex;
  flex-direction: column;
  max-height: 90vh;
}

.modal.sm { max-width: 400px; }
.modal.md { max-width: 560px; }
.modal.lg { max-width: 800px; }

.head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-5);
  border-bottom: 1px solid var(--color-border);
}

.head h2 {
  margin: 0;
  font-size: var(--text-lg);
  font-weight: var(--font-weight-semibold);
}

.close {
  width: 28px;
  height: 28px;
  border-radius: var(--radius-sm);
  color: var(--color-text-muted);
}
.close:hover { background: var(--color-surface-muted); }

.body {
  padding: var(--space-5);
  overflow-y: auto;
}

.foot {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-2);
  padding: var(--space-4) var(--space-5);
  border-top: 1px solid var(--color-border);
}
</style>
