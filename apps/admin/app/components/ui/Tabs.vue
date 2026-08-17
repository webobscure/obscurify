<template>
  <div class="tabs">
    <div role="tablist" class="tablist" :class="variant" @keydown="onKeydown">
      <button
        v-for="tab in tabs"
        :id="`tab-${tab.value}`"
        :key="tab.value"
        role="tab"
        type="button"
        class="tab"
        :aria-selected="modelValue === tab.value"
        :aria-controls="`panel-${tab.value}`"
        :tabindex="modelValue === tab.value ? 0 : -1"
        :disabled="tab.disabled"
        @click="select(tab.value)"
      >
        {{ tab.label }}
      </button>
    </div>
    <div
      v-for="tab in tabs"
      v-show="modelValue === tab.value"
      :id="`panel-${tab.value}`"
      :key="tab.value"
      role="tabpanel"
      :aria-labelledby="`tab-${tab.value}`"
      class="panel"
    >
      <slot :name="tab.value" />
    </div>
  </div>
</template>

<script setup lang="ts">
export interface TabItem {
  value: string
  label: string
  disabled?: boolean
}

const props = withDefaults(defineProps<{
  modelValue: string
  tabs: TabItem[]
  variant?: 'line' | 'segmented'
}>(), { variant: 'line' })

const emit = defineEmits<{ 'update:modelValue': [string] }>()

function select(value: string) {
  emit('update:modelValue', value)
}

function onKeydown(event: KeyboardEvent) {
  if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return
  const enabled = props.tabs.filter(t => !t.disabled)
  const index = enabled.findIndex(t => t.value === props.modelValue)
  if (index === -1) return
  const next = event.key === 'ArrowRight' ? (index + 1) % enabled.length : (index - 1 + enabled.length) % enabled.length
  select(enabled[next]!.value)
  nextTick(() => document.getElementById(`tab-${enabled[next]!.value}`)?.focus())
}
</script>

<style scoped>
.tablist {
  display: flex;
  gap: var(--space-1);
}

.tablist.line { border-bottom: var(--border-width) solid var(--color-border); }
.tablist.segmented {
  background: var(--color-surface-muted);
  border-radius: var(--radius-sm);
  padding: 2px;
  gap: 2px;
  display: inline-flex;
}

.tab {
  height: 40px;
  padding: 0 var(--space-3);
  font-size: var(--text-base);
  color: var(--color-text-muted);
  background: none;
  border: none;
  cursor: pointer;
  transition: color var(--transition-fast);
}
.tab:disabled { cursor: not-allowed; }
.tab:not(:disabled):hover { color: var(--color-text); }

.tablist.line .tab {
  border-bottom: var(--border-width-thick) solid transparent;
  margin-bottom: -1px;
}
.tablist.line .tab[aria-selected='true'] {
  color: var(--color-accent);
  border-bottom-color: var(--color-accent);
  font-weight: var(--font-weight-medium);
}

.tablist.segmented .tab {
  height: 32px;
  border-radius: calc(var(--radius-sm) - 2px);
}
.tablist.segmented .tab[aria-selected='true'] {
  background: var(--color-surface);
  color: var(--color-text);
  font-weight: var(--font-weight-medium);
  box-shadow: var(--shadow-sm);
}

.panel {
  padding-top: var(--space-4);
}
</style>
