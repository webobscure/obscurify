<template>
  <FormField :id="id" v-slot="{ fieldId, describedby }" :label="label" :required="required" :help="help" :error="error">
    <textarea
      :id="fieldId"
      class="textarea"
      :rows="rows"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :required="required"
      :aria-invalid="!!error"
      :aria-describedby="describedby"
      @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    />
  </FormField>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  modelValue: string
  rows?: number
  label?: string
  placeholder?: string
  help?: string
  error?: string
  required?: boolean
  disabled?: boolean
  id?: string
}>(), { rows: 3 })

const emit = defineEmits<{ 'update:modelValue': [string] }>()
</script>

<style scoped>
.textarea {
  width: 100%;
  padding: var(--space-2) var(--space-3);
  border: var(--border-width) solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: inherit;
  font-size: var(--text-base);
  line-height: var(--leading-normal);
  resize: vertical;
  transition: border-color var(--transition-fast);
}

.textarea::placeholder { color: var(--color-text-subtle); }
.textarea:focus-visible { border-color: var(--color-accent); }
.textarea:disabled { background: var(--color-surface-muted); }
.textarea[aria-invalid='true'] { border-color: var(--color-danger); }
</style>
