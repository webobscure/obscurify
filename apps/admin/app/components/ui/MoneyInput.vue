<template>
  <div class="money-input" :class="{ invalid }">
    <input
      :id="id"
      type="text"
      inputmode="decimal"
      class="field"
      :data-testid="testid"
      :value="displayValue"
      :aria-invalid="invalid || undefined"
      :placeholder="placeholder"
      @focus="onFocus"
      @blur="onBlur"
      @input="onInput"
    >
    <!-- formatMoney() already embeds the currency symbol ("999,00 ₽") once
         unfocused — showing this too produced a redundant "999,00 ₽ RUB".
         Only useful while editing, when the field shows a bare decimal
         with no symbol at all. -->
    <span v-if="editing" class="currency">{{ currency }}</span>
  </div>
</template>

<script setup lang="ts">
/**
 * Currency-formatted input that only ever stores/emits integer minor
 * units — matches utils/money.ts's exact divide-by-100 convention
 * (ADR-010: "never calculate/store monetary values with floats").
 * While unfocused it shows formatMoney()'s own output; while focused it
 * shows a plain decimal for easy editing, parsed back to an integer on
 * blur — money.ts only ever went one direction (format for display), so
 * this component is the first place that round-trip exists.
 */
const props = withDefaults(defineProps<{
  modelValue: number | null
  currency: string
  id?: string
  testid?: string
  placeholder?: string
  invalid?: boolean
}>(), {
  // "0.00" as a placeholder for an empty optional money field (e.g.
  // compare-at, cost) is visually indistinguishable from a real zero
  // value at table density — a merchant scanning the Variants table
  // can't tell "no compare-at price set" from "compare-at price is
  // literally ₽0.00" at a glance. "—" matches the convention already
  // used for other empty numeric cells in this app (e.g. VariantTable's
  // Available column).
  placeholder: '—',
})

const emit = defineEmits<{ 'update:modelValue': [number | null] }>()

const editing = ref(false)
const draft = ref('')

const displayValue = computed(() => {
  if (editing.value) return draft.value
  if (props.modelValue === null) return ''
  return formatMoney({ amount: props.modelValue, currency: props.currency })
})

function onFocus() {
  editing.value = true
  draft.value = props.modelValue === null ? '' : (props.modelValue / 100).toFixed(2)
}

function onInput(event: Event) {
  draft.value = (event.target as HTMLInputElement).value
}

function onBlur() {
  editing.value = false
  if (draft.value.trim() === '') {
    emit('update:modelValue', null)
    return
  }

  const parsed = parseMoney(draft.value)
  emit('update:modelValue', parsed ?? props.modelValue)
}
</script>

<style scoped>
.money-input {
  display: flex;
  align-items: center;
  /* Same fix as VariantDetailDrawer's .field: without this, MoneyInput
     (itself a flex row) refuses to shrink below its own content's
     intrinsic width when it's the flex child of a narrower parent
     (e.g. the drawer's side-by-side Цена/Сравнительная row), and
     overflows the container instead of sharing the available space. */
  width: 100%;
  min-width: 0;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  background: var(--color-surface);
  overflow: hidden;
}

.money-input.invalid {
  border-color: var(--color-danger);
}

.field {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  padding: var(--space-2) var(--space-3);
  font-size: var(--text-base);
  color: var(--color-text);
  font-variant-numeric: tabular-nums;
}

.field:focus-visible {
  outline: none;
}

.currency {
  padding: 0 var(--space-3) 0 0;
  font-size: var(--text-sm);
  color: var(--color-text-subtle);
}
</style>
