<template>
  <FormField :id="id" v-slot="{ fieldId, describedby }" :label="label" :required="required" :help="help" :error="error">
    <div class="wrap" :class="size">
      <select
        :id="fieldId"
        class="select"
        :value="modelValue"
        :disabled="disabled"
        :required="required"
        :aria-invalid="!!error"
        :aria-describedby="describedby"
        @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
      >
        <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
        <slot />
      </select>
      <AppIcon name="chevron" size="sm" class="chevron" />
    </div>
  </FormField>
</template>

<script setup lang="ts">
/**
 * Native <select> retained on purpose (see docs/design/
 * ADMIN_DESIGN_SYSTEM.md §Select) — keeps free keyboard/mobile/AT
 * support rather than reimplementing a custom listbox.
 */
withDefaults(defineProps<{
  modelValue: string
  size?: 'sm' | 'md'
  label?: string
  placeholder?: string
  help?: string
  error?: string
  required?: boolean
  disabled?: boolean
  id?: string
}>(), { size: 'md' })

const emit = defineEmits<{ 'update:modelValue': [string] }>()
</script>

<style scoped>
.wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.select {
  width: 100%;
  border: var(--border-width) solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: inherit;
  font-size: var(--text-base);
  appearance: none;
  padding-right: calc(var(--icon-size-sm) + var(--space-5));
  transition: border-color var(--transition-fast);
}

.wrap.md .select { height: 36px; padding-left: var(--space-3); }
.wrap.sm .select { height: 28px; padding-left: var(--space-2); font-size: var(--text-sm); }

.select:focus-visible { border-color: var(--color-accent); }
.select:disabled { background: var(--color-surface-muted); }
.select[aria-invalid='true'] { border-color: var(--color-danger); }

.chevron {
  position: absolute;
  right: var(--space-2);
  color: var(--color-text-subtle);
  pointer-events: none;
}
</style>
