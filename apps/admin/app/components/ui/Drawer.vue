<template>
  <Teleport to="body">
    <div v-if="open" class="scrim" @click.self="close">
      <div ref="panel" class="drawer" role="dialog" aria-modal="true" :aria-labelledby="titleId" :data-testid="testid">
        <header class="head">
          <div>
            <p v-if="eyebrow" class="eyebrow">{{ eyebrow }}</p>
            <h2 :id="titleId">{{ title }}</h2>
          </div>
          <button type="button" class="close" :aria-label="t('common.close')" @click="close">✕</button>
        </header>
        <div class="body"><slot /></div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
const props = defineProps<{ open: boolean; title: string; eyebrow?: string; testid?: string }>()
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
  justify-content: flex-end;
}

.drawer {
  width: 100%;
  max-width: 360px;
  height: 100%;
  background: var(--color-surface);
  box-shadow: var(--shadow-lg);
  display: flex;
  flex-direction: column;
  overflow-y: auto;
}

.head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-5);
  border-bottom: 1px solid var(--color-border);
  flex-shrink: 0;
}

.eyebrow {
  margin: 0 0 2px;
  font-size: var(--text-xs);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-subtle);
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
  flex-shrink: 0;
}
.close:hover { background: var(--color-surface-muted); }

.body {
  padding: var(--space-5);
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}
</style>
