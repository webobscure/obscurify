<template>
  <div class="search-field" role="search">
    <AppIcon name="search" size="sm" class="icon" />
    <input
      type="search"
      class="input"
      role="searchbox"
      :value="modelValue"
      :placeholder="placeholder"
      :aria-label="ariaLabel ?? placeholder"
      @input="onInput(($event.target as HTMLInputElement).value)"
    >
    <Spinner v-if="searching" size="sm" class="trailing" />
    <IconButton v-else-if="modelValue" icon="close" size="sm" :ariaLabel="t('common.clear')" class="trailing" @click="clear" />
  </div>
</template>

<script setup lang="ts">
/**
 * Input specialized for search-as-you-type — debounces the emitted
 * update (300ms default) rather than firing per keystroke. See
 * docs/design/ADMIN_DESIGN_SYSTEM.md §SearchInput.
 */
const props = withDefaults(defineProps<{
  modelValue: string
  placeholder?: string
  ariaLabel?: string
  debounceMs?: number
  searching?: boolean
}>(), { debounceMs: 300 })

const emit = defineEmits<{ 'update:modelValue': [string] }>()
const { t } = useI18n()

let timer: ReturnType<typeof setTimeout> | undefined

function onInput(value: string) {
  clearTimeout(timer)
  timer = setTimeout(() => emit('update:modelValue', value), props.debounceMs)
}

function clear() {
  clearTimeout(timer)
  emit('update:modelValue', '')
}
</script>

<style scoped>
.search-field {
  position: relative;
  display: flex;
  align-items: center;
}

.icon {
  position: absolute;
  left: var(--space-3);
  color: var(--color-text-subtle);
  pointer-events: none;
}

.input {
  width: 100%;
  height: 36px;
  padding: 0 var(--space-5) 0 calc(var(--icon-size-sm) + var(--space-5));
  border: var(--border-width) solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: inherit;
  font-size: var(--text-base);
}

.input::placeholder { color: var(--color-text-subtle); }
.input:focus-visible { border-color: var(--color-accent); }

.trailing {
  position: absolute;
  right: var(--space-2);
}
</style>
