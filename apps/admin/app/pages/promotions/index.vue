<template>
  <div>
    <PageHeader title="Promotions" />

    <p v-if="!activeStore.storeId.value" class="error">
      Select an active store first — see <NuxtLink to="/stores">Stores</NuxtLink>.
    </p>

    <template v-else>
      <table v-if="promotions.length">
        <thead>
          <tr>
            <th>Name</th>
            <th>Trigger</th>
            <th>Stacking</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Rules</th>
            <th>Actions</th>
            <th>Codes</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="promotion in promotions" :key="promotion.id">
            <td>{{ promotion.name }}</td>
            <td>{{ promotion.trigger_type }}</td>
            <td>{{ promotion.stacking_mode }}</td>
            <td>{{ promotion.priority }}</td>
            <td>
              <button type="button" class="status-toggle" @click="toggleStatus(promotion)">
                {{ promotion.status }}
              </button>
            </td>
            <td>{{ promotion.rules?.length ?? 0 }}</td>
            <td>{{ promotion.actions?.length ?? 0 }}</td>
            <td>
              <span v-for="code in promotion.discount_codes" :key="code.id" class="code-chip">
                {{ code.code }} ({{ code.usage_count }}<template v-if="code.usage_limit">/{{ code.usage_limit }}</template>)
              </span>
              <template v-if="promotion.trigger_type === 'code'">
                <form class="code-form" @submit.prevent="handleCreateCode(promotion)">
                  <input v-model="codeForms[promotion.id]" type="text" placeholder="NEWCODE" >
                  <button type="submit">Add code</button>
                </form>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else>No promotions yet.</p>

      <h2>Create a promotion</h2>
      <form class="grid" @submit.prevent="handleCreate">
        <input v-model="form.name" type="text" placeholder="Name (e.g. Spring sale)" required >
        <input v-model="form.description" type="text" placeholder="Description (optional)" >

        <label>
          Trigger
          <select v-model="form.trigger_type">
            <option value="automatic">Automatic</option>
            <option value="code">Discount code</option>
          </select>
        </label>

        <label>
          Stacking
          <select v-model="form.stacking_mode">
            <option value="stackable">Stackable</option>
            <option value="exclusive">Exclusive</option>
          </select>
        </label>

        <input v-model.number="form.priority" type="number" placeholder="Priority (lower runs first)" >

        <fieldset>
          <legend>Rules (all must pass)</legend>
          <div v-for="(rule, index) in form.rules" :key="index" class="rule-row">
            <select v-model="rule.type">
              <option v-for="type in ruleTypes" :key="type" :value="type">{{ type }}</option>
            </select>
            <input v-model="rule.parametersJson" type="text" placeholder='{"amount": 5000}'>
            <button type="button" class="remove" @click="form.rules.splice(index, 1)">Remove</button>
          </div>
          <button type="button" @click="form.rules.push({ type: 'min_subtotal', parametersJson: '{}' })">
            Add rule
          </button>
        </fieldset>

        <fieldset>
          <legend>Actions (what the promotion does)</legend>
          <div v-for="(action, index) in form.actions" :key="index" class="rule-row">
            <select v-model="action.type">
              <option v-for="type in actionTypes" :key="type" :value="type">{{ type }}</option>
            </select>
            <input v-model="action.parametersJson" type="text" placeholder='{"percent": 10}'>
            <button type="button" class="remove" @click="form.actions.splice(index, 1)">Remove</button>
          </div>
          <button type="button" @click="form.actions.push({ type: 'percentage_off', parametersJson: '{}' })">
            Add action
          </button>
        </fieldset>

        <button type="submit" :disabled="creating">{{ creating ? 'Creating…' : 'Create promotion' }}</button>
      </form>
      <p v-if="error" class="error">{{ error }}</p>

      <h2>Preview</h2>
      <p class="hint">Check what would apply to a hypothetical cart, without creating anything.</p>
      <form class="grid" @submit.prevent="handlePreview">
        <input v-model="previewForm.productVariantId" type="text" placeholder="Product variant ID" required >
        <input v-model.number="previewForm.quantity" type="number" min="1" placeholder="Quantity" required >
        <input v-model.number="previewForm.shippingAmount" type="number" min="0" placeholder="Shipping amount (minor units)" >
        <input v-model="previewForm.discountCode" type="text" placeholder="Discount code (optional)" >
        <button type="submit" :disabled="previewing">{{ previewing ? 'Checking…' : 'Preview' }}</button>
      </form>
      <div v-if="previewResult">
        <p>Discount amount: {{ previewResult.discount_amount }}</p>
        <ul>
          <li v-for="(applied, index) in previewResult.applied" :key="index">
            {{ applied.promotion_name }} — {{ applied.action_type }} — {{ applied.amount }}
          </li>
        </ul>
        <p v-if="!previewResult.applied.length">No promotions would apply.</p>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { AppliedDiscount, Promotion, PromotionActionType, PromotionRuleType } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const ruleTypes: PromotionRuleType[] = [
  'min_subtotal', 'product', 'collection', 'category', 'customer',
  'country', 'currency', 'order_quantity', 'order_total', 'date_range', 'usage_limit',
]
const actionTypes: PromotionActionType[] = [
  'percentage_off', 'fixed_amount_off', 'free_shipping', 'free_product', 'line_item_discount', 'order_discount',
]

const promotions = ref<Promotion[]>([])
const form = reactive<{
  name: string
  description: string
  trigger_type: string
  stacking_mode: string
  priority: number | null
  rules: { type: string, parametersJson: string }[]
  actions: { type: string, parametersJson: string }[]
}>({
  name: '',
  description: '',
  trigger_type: 'automatic',
  stacking_mode: 'stackable',
  priority: 0,
  rules: [],
  actions: [],
})
const creating = ref(false)
const error = ref<string | null>(null)
const codeForms = reactive<Record<string, string>>({})
const activeStore = useActiveStore()

const previewForm = reactive({ productVariantId: '', quantity: 1, shippingAmount: 0, discountCode: '' })
const previewing = ref(false)
const previewResult = ref<{ discount_amount: number, applied: AppliedDiscount[] } | null>(null)

async function load() {
  if (!activeStore.storeId.value) return
  const response = await useApi().promotions.list()
  promotions.value = response.data
}

function parseParameters(json: string): Record<string, unknown> {
  if (!json.trim()) return {}
  try {
    return JSON.parse(json)
  } catch {
    return {}
  }
}

async function handleCreate() {
  creating.value = true
  error.value = null
  try {
    await useApi().promotions.create({
      name: form.name,
      description: form.description || null,
      trigger_type: form.trigger_type,
      stacking_mode: form.stacking_mode,
      priority: form.priority ?? 0,
      rules: form.rules.map(r => ({ type: r.type, parameters: parseParameters(r.parametersJson) })),
      actions: form.actions.map(a => ({ type: a.type, parameters: parseParameters(a.parametersJson) })),
    })
    form.name = ''
    form.description = ''
    form.rules = []
    form.actions = []
    await load()
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    creating.value = false
  }
}

async function toggleStatus(promotion: Promotion) {
  const status = promotion.status === 'active' ? 'inactive' : 'active'
  await useApi().promotions.update(promotion.id, { status })
  await load()
}

async function handleCreateCode(promotion: Promotion) {
  const code = codeForms[promotion.id]
  if (!code) return
  await useApi().discountCodes.create({ promotion_id: promotion.id, code })
  codeForms[promotion.id] = ''
  await load()
}

async function handlePreview() {
  previewing.value = true
  previewResult.value = null
  try {
    previewResult.value = await useApi().promotions.preview({
      items: [{ product_variant_id: previewForm.productVariantId, quantity: previewForm.quantity }],
      shipping_amount: previewForm.shippingAmount || 0,
      discount_code: previewForm.discountCode || null,
    })
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : 'Something went wrong.'
  } finally {
    previewing.value = false
  }
}

onMounted(load)
watch(() => activeStore.storeId.value, load)
</script>

<style scoped>
.grid {
  display: grid;
  gap: 0.5rem;
  max-width: 480px;
}

fieldset {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  padding: 0.5rem 0.75rem;
}

.rule-row {
  display: grid;
  grid-template-columns: 1fr 2fr auto;
  gap: 0.5rem;
  margin-bottom: 0.4rem;
}

.remove {
  background: transparent;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.status-toggle {
  background: none;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-sm);
  cursor: pointer;
  padding: 0.1rem 0.5rem;
}

.code-chip {
  display: inline-block;
  margin-right: 0.4rem;
  padding: 0.1rem 0.4rem;
  border-radius: var(--radius-sm);
  background: var(--color-bg);
  font-size: var(--text-xs);
}

.code-form {
  display: flex;
  gap: 0.3rem;
  margin-top: 0.3rem;
}

.code-form input {
  width: 8rem;
}

.hint {
  color: var(--color-text-muted);
  font-size: var(--text-sm);
  margin: 0 0 0.5rem;
}
</style>
