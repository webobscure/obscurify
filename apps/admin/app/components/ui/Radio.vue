<template>
  <label class="radio" :class="[{ disabled }, variant]">
    <input
      type="radio"
      :name="name"
      :value="value"
      :checked="modelValue === value"
      :disabled="disabled"
      @change="emit('update:modelValue', value)"
    >
    <span class="dot" aria-hidden="true" />
    <span class="label"><slot /></span>
  </label>
</template>

<script setup lang="ts">
defineProps<{
  modelValue: string
  value: string
  name: string
  disabled?: boolean
  /** `card` wraps the radio+label in a bordered, selectable card — for higher-stakes choices (e.g. shipping method) that need more context than a bare label. */
  variant?: 'default' | 'card'
}>()
const emit = defineEmits<{ 'update:modelValue': [string] }>()
</script>

<style scoped>
.radio {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  cursor: pointer;
}
.radio.disabled { cursor: not-allowed; }

.radio.card {
  display: flex;
  padding: var(--space-3) var(--space-4);
  border: var(--border-width) solid var(--color-border-strong);
  border-radius: var(--radius-md);
  transition: border-color var(--transition-fast), background-color var(--transition-fast);
}
.radio.card:has(input:checked) {
  border-color: var(--color-accent);
  background: var(--color-accent-bg);
}

input[type='radio'] {
  position: absolute;
  width: 18px;
  height: 18px;
  margin: 0;
  opacity: 0;
}

.dot {
  flex-shrink: 0;
  width: var(--icon-size-md);
  height: var(--icon-size-md);
  border-radius: var(--radius-full);
  border: var(--border-width) solid var(--color-border-strong);
  background: var(--color-surface);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: border-color var(--transition-fast);
}
.dot::after {
  content: '';
  width: 8px;
  height: 8px;
  border-radius: var(--radius-full);
  background: var(--color-accent);
  transform: scale(0);
  transition: transform var(--transition-fast);
}

input:checked + .dot { border-color: var(--color-accent); }
input:checked + .dot::after { transform: scale(1); }

input:focus-visible + .dot {
  outline: var(--focus-ring-width) solid var(--focus-ring-color);
  outline-offset: var(--focus-ring-offset);
}

.label {
  font-size: var(--text-base);
  color: var(--color-text);
}
</style>
