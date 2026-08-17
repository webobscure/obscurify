<template>
  <FormField :id="id" v-slot="{ fieldId, describedby }" :label="label" :required="required" :help="help" :error="error">
    <div class="wrap" :class="size">
      <AppIcon v-if="icon" :name="icon" size="sm" class="leading-icon" />
      <input
        :id="fieldId"
        ref="inputEl"
        class="input"
        :class="{ 'has-icon': icon }"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :required="required"
        :aria-invalid="!!error"
        :aria-describedby="describedby"
        @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      >
      <button v-if="clearable && modelValue" type="button" class="clear" :aria-label="t('common.clear')" @click="clear">
        <AppIcon name="close" size="sm" />
      </button>
    </div>
  </FormField>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  modelValue: string
  type?: string
  size?: 'sm' | 'md'
  label?: string
  placeholder?: string
  help?: string
  error?: string
  required?: boolean
  disabled?: boolean
  icon?: string
  clearable?: boolean
  id?: string
}>(), { type: 'text', size: 'md' })

const emit = defineEmits<{ 'update:modelValue': [string] }>()
const { t } = useI18n()
const inputEl = ref<HTMLInputElement | null>(null)

function clear() {
  emit('update:modelValue', '')
  inputEl.value?.focus()
}
</script>

<style scoped>
.wrap {
  position: relative;
  display: flex;
  align-items: center;
}

.leading-icon {
  position: absolute;
  left: var(--space-3);
  color: var(--color-text-subtle);
  pointer-events: none;
}

.input {
  width: 100%;
  border: var(--border-width) solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  color: var(--color-text);
  font-family: inherit;
  font-size: var(--text-base);
  transition: border-color var(--transition-fast);
}

.wrap.md .input { height: 36px; padding: 0 var(--space-3); }
.wrap.sm .input { height: 28px; padding: 0 var(--space-2); font-size: var(--text-sm); }

.input.has-icon { padding-left: calc(var(--icon-size-sm) + var(--space-5)); }

.input::placeholder { color: var(--color-text-subtle); }

.input:focus-visible {
  border-color: var(--color-accent);
}

.input:disabled {
  background: var(--color-surface-muted);
}

.wrap:has(.input[aria-invalid='true']) .input {
  border-color: var(--color-danger);
}

.clear {
  position: absolute;
  right: var(--space-2);
  color: var(--color-text-subtle);
  display: flex;
  border-radius: var(--radius-sm);
}
.clear:hover { color: var(--color-text); }
</style>
