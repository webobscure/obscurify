<template>
  <div class="field">
    <label v-if="label" :for="fieldId" class="label">
      {{ label }}<span v-if="required" class="required" aria-hidden="true">*</span>
    </label>
    <slot :field-id="fieldId" :describedby="describedby" />
    <p v-if="error" :id="errorId" class="error" role="alert">{{ error }}</p>
    <p v-else-if="help" :id="helpId" class="help">{{ help }}</p>
  </div>
</template>

<script setup lang="ts">
/**
 * Standardizes field spacing/label/required-marker/help/error across
 * Input/Textarea/Select/Checkbox — see docs/design/ADMIN_DESIGN_SYSTEM.md
 * §Forms. Slot exposes `fieldId`/`describedby` so the wrapped control can
 * bind them (label `for`, `aria-describedby`) without each consumer
 * re-deriving ids itself.
 */
const props = defineProps<{
  label?: string
  required?: boolean
  help?: string
  error?: string
  id?: string
}>()

const autoId = useId()
const fieldId = computed(() => props.id ?? autoId)
const helpId = computed(() => `${fieldId.value}-help`)
const errorId = computed(() => `${fieldId.value}-error`)
const describedby = computed(() => (props.error ? errorId.value : props.help ? helpId.value : undefined))
</script>

<style scoped>
.field {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.label {
  font-size: var(--text-sm);
  font-weight: var(--font-weight-medium);
  color: var(--color-text);
}

.required {
  color: var(--color-danger);
  margin-left: 2px;
}

.help {
  margin: 0;
  font-size: var(--text-xs);
  color: var(--color-text-muted);
}

.error {
  margin: 0;
  font-size: var(--text-xs);
  color: var(--color-danger);
}
</style>
