<template>
  <button
    type="button"
    role="switch"
    class="switch"
    :class="{ on: modelValue }"
    :aria-checked="modelValue"
    :aria-label="ariaLabel"
    :disabled="disabled || loading"
    @click="emit('update:modelValue', !modelValue)"
  >
    <span class="thumb">
      <Spinner v-if="loading" size="sm" />
    </span>
  </button>
</template>

<script setup lang="ts">
/**
 * Immediate-effect boolean toggle — distinct from Checkbox, which is part
 * of a form that's explicitly submitted (see docs/design/
 * ADMIN_DESIGN_SYSTEM.md §Switch). `loading` covers the in-flight window
 * of the immediate API call this triggers, since there's no surrounding
 * form-submit spinner to communicate it otherwise.
 */
defineProps<{
  modelValue: boolean
  disabled?: boolean
  loading?: boolean
  ariaLabel: string
}>()
const emit = defineEmits<{ 'update:modelValue': [boolean] }>()
</script>

<style scoped>
.switch {
  width: 36px;
  height: 20px;
  border-radius: var(--radius-full);
  background: var(--color-border-strong);
  padding: 2px;
  display: flex;
  align-items: center;
  cursor: pointer;
  transition: background-color var(--transition-fast);
}

.switch.on {
  background: var(--color-accent);
  justify-content: flex-end;
}

.thumb {
  width: 16px;
  height: 16px;
  border-radius: var(--radius-full);
  background: var(--color-surface);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-accent);
  transition: transform var(--transition-fast);
}
</style>
