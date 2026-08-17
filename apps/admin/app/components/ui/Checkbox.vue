<template>
  <label class="checkbox" :class="{ disabled }">
    <input
      ref="inputEl"
      type="checkbox"
      :checked="modelValue"
      :disabled="disabled"
      :aria-label="ariaLabel"
      @change="emit('update:modelValue', ($event.target as HTMLInputElement).checked)"
    >
    <span class="box" aria-hidden="true">
      <AppIcon v-if="modelValue && !indeterminate" name="check" size="sm" />
      <span v-else-if="indeterminate" class="dash" />
    </span>
    <span v-if="$slots.default" class="label"><slot /></span>
  </label>
</template>

<script setup lang="ts">
const props = defineProps<{
  modelValue: boolean
  indeterminate?: boolean
  disabled?: boolean
  /** Required when there's no visible label — e.g. a DataTable row-select checkbox. */
  ariaLabel?: string
}>()
const emit = defineEmits<{ 'update:modelValue': [boolean] }>()

const inputEl = ref<HTMLInputElement | null>(null)

watch(() => props.indeterminate, (value) => {
  if (inputEl.value) inputEl.value.indeterminate = !!value
}, { immediate: true })
</script>

<style scoped>
.checkbox {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  cursor: pointer;
}
.checkbox.disabled { cursor: not-allowed; }

input[type='checkbox'] {
  position: absolute;
  width: 18px;
  height: 18px;
  margin: 0;
  opacity: 0;
}

.box {
  flex-shrink: 0;
  width: var(--icon-size-md);
  height: var(--icon-size-md);
  border-radius: var(--radius-sm);
  border: var(--border-width) solid var(--color-border-strong);
  background: var(--color-surface);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-text-on-accent);
  transition: background-color var(--transition-fast), border-color var(--transition-fast);
}

input:checked + .box,
input:indeterminate + .box {
  background: var(--color-accent);
  border-color: var(--color-accent);
}

input:focus-visible + .box {
  outline: var(--focus-ring-width) solid var(--focus-ring-color);
  outline-offset: var(--focus-ring-offset);
}

.dash {
  width: 8px;
  height: 2px;
  background: currentColor;
  border-radius: 1px;
}

.label {
  font-size: var(--text-base);
  color: var(--color-text);
}
</style>
