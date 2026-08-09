<template>
  <div>
    <h1>Checkout</h1>

    <ClientOnly>
      <p v-if="initializing">Loading…</p>

      <template v-else-if="checkout.checkout.value">
        <form @submit.prevent="handlePlaceOrder">
          <section>
            <h2>Contact</h2>
            <label>
              Email
              <input v-model="email" type="email" required autocomplete="email">
            </label>
            <label>
              Phone
              <input v-model="phone" type="tel" autocomplete="tel">
            </label>
          </section>

          <section>
            <h2>Shipping address</h2>
            <label>
              First name
              <input v-model="shipping.first_name" type="text" required autocomplete="given-name">
            </label>
            <label>
              Last name
              <input v-model="shipping.last_name" type="text" required autocomplete="family-name">
            </label>
            <label>
              Country code
              <input v-model="shipping.country_code" type="text" maxlength="2" placeholder="US" required autocomplete="country">
            </label>
            <label>
              Region
              <input v-model="shipping.region" type="text" autocomplete="address-level1">
            </label>
            <label>
              City
              <input v-model="shipping.city" type="text" required autocomplete="address-level2">
            </label>
            <label>
              Postal code
              <input v-model="shipping.postal_code" type="text" autocomplete="postal-code">
            </label>
            <label>
              Address line 1
              <input v-model="shipping.address_line1" type="text" required autocomplete="address-line1">
            </label>
            <label>
              Address line 2
              <input v-model="shipping.address_line2" type="text" autocomplete="address-line2">
            </label>
          </section>

          <section>
            <h2>Shipping method</h2>
            <button type="button" :disabled="loadingRates" @click="handleLoadShippingRates">
              {{ loadingRates ? 'Checking…' : 'Check shipping options' }}
            </button>

            <ul v-if="checkout.shippingRates.value.length" class="rates">
              <li v-for="rate in checkout.shippingRates.value" :key="`${rate.provider}-${rate.service_code}-${rate.shipping_method_id}`">
                <label>
                  <input
                    type="radio"
                    name="shipping-rate"
                    :checked="isRateChecked(rate)"
                    :disabled="selectingRate"
                    @change="handleRateChange(rate)"
                  >
                  {{ rate.name }} — {{ formatMoney({ amount: rate.price_amount, currency: rate.currency }) }}
                  <span v-if="rate.estimated_days_min || rate.estimated_days_max" class="estimate">
                    ({{ rate.estimated_days_min }}–{{ rate.estimated_days_max }} days)
                  </span>
                </label>

                <!-- Pickup point picker: only shown once a Pickup-service rate is chosen (spec sections 5/6/23) -->
                <div v-if="rate.service_code === 'pickup' && pendingPickupRate === rate" class="pickup-points">
                  <p v-if="!rate.pickup_points?.length" class="muted">
                    No pickup points available for this address.
                  </p>
                  <ul v-else>
                    <li v-for="point in rate.pickup_points" :key="point.id">
                      <label>
                        <input
                          type="radio"
                          name="pickup-point"
                          :checked="selectedPickupPointId === point.id"
                          :disabled="selectingRate"
                          @change="handlePickupPointChange(rate, point)"
                        >
                        {{ point.name }} — {{ point.address }}, {{ point.city }}
                        <span v-if="point.opening_hours" class="estimate">({{ point.opening_hours }})</span>
                      </label>
                    </li>
                  </ul>
                </div>
              </li>
            </ul>
            <p v-if="checkout.shippingError.value" class="error">{{ checkout.shippingError.value }}</p>
          </section>

          <section>
            <label class="checkbox">
              <input v-model="billingSameAsShipping" type="checkbox">
              Billing address is the same as shipping
            </label>

            <template v-if="!billingSameAsShipping">
              <h2>Billing address</h2>
              <label>
                First name
                <input v-model="billing.first_name" type="text" required autocomplete="given-name">
              </label>
              <label>
                Last name
                <input v-model="billing.last_name" type="text" required autocomplete="family-name">
              </label>
              <label>
                Country code
                <input v-model="billing.country_code" type="text" maxlength="2" placeholder="US" required autocomplete="country">
              </label>
              <label>
                Region
                <input v-model="billing.region" type="text" autocomplete="address-level1">
              </label>
              <label>
                City
                <input v-model="billing.city" type="text" required autocomplete="address-level2">
              </label>
              <label>
                Postal code
                <input v-model="billing.postal_code" type="text" autocomplete="postal-code">
              </label>
              <label>
                Address line 1
                <input v-model="billing.address_line1" type="text" required autocomplete="address-line1">
              </label>
              <label>
                Address line 2
                <input v-model="billing.address_line2" type="text" autocomplete="address-line2">
              </label>
            </template>
          </section>

          <section>
            <h2>Review</h2>
            <table>
              <tbody>
                <tr v-for="item in cart.cart.value?.items ?? []" :key="item.id">
                  <td>{{ item.title }} &times; {{ item.quantity }}</td>
                  <td>{{ formatMoney(item.line_total) }}</td>
                </tr>
              </tbody>
            </table>
            <p v-if="checkout.checkout.value.selected_shipping_rate" class="totals-line">
              Shipping ({{ checkout.checkout.value.selected_shipping_rate.name }}):
              {{ formatMoney({ amount: checkout.checkout.value.shipping_amount, currency: checkout.checkout.value.currency }) }}
            </p>
            <p v-if="checkout.checkout.value.selected_shipping_rate?.pickup_point" class="totals-line">
              Pickup at: {{ checkout.checkout.value.selected_shipping_rate.pickup_point.name }} —
              {{ checkout.checkout.value.selected_shipping_rate.pickup_point.address }}, {{ checkout.checkout.value.selected_shipping_rate.pickup_point.city }}
            </p>
            <p class="totals">
              <strong>Total: {{ formatMoney({ amount: checkout.checkout.value.total_amount, currency: checkout.checkout.value.currency }) }}</strong>
            </p>
          </section>

          <p v-if="checkout.error.value" class="error">{{ checkout.error.value }}</p>

          <button type="submit" :disabled="placingOrder">
            {{ placingOrder ? 'Placing order…' : 'Place order' }}
          </button>
        </form>
      </template>

      <template #fallback>
        <p>Loading…</p>
      </template>
    </ClientOnly>
  </div>
</template>

<script setup lang="ts">
import type { StorefrontPickupPoint, StorefrontShippingRate } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'

const cart = useCart()
const checkout = useCheckout()

const initializing = ref(true)
const placingOrder = ref(false)
const loadingRates = ref(false)
const selectingRate = ref(false)

/**
 * A Pickup-service rate is never sent to the backend on its own radio
 * click — selection only fires once a pickup point is also chosen
 * (spec section 6). `pendingPickupRate` tracks which rate's point-picker
 * is expanded; it is not necessarily the confirmed selection yet.
 */
const pendingPickupRate = shallowRef<StorefrontShippingRate | null>(null)
const selectedPickupPointId = ref<string | null>(null)

const email = ref('')
const phone = ref('')
const billingSameAsShipping = ref(true)
const shipping = reactive({
  first_name: '', last_name: '', country_code: '', region: '', city: '', postal_code: '', address_line1: '', address_line2: '',
})
const billing = reactive({
  first_name: '', last_name: '', country_code: '', region: '', city: '', postal_code: '', address_line1: '', address_line2: '',
})

onMounted(async () => {
  try {
    await cart.fetch()
    if (!cart.cart.value?.items.length) {
      await navigateTo('/cart')
      return
    }
    await checkout.open()
  } finally {
    initializing.value = false
  }
})

/**
 * Saves the address first (rates are calculated server-side from the
 * checkout's already-persisted address — spec section 9), then fetches
 * rates against it.
 */
async function handleLoadShippingRates() {
  loadingRates.value = true
  try {
    await checkout.update({
      email: email.value,
      phone: phone.value || null,
      shipping_address: { ...shipping },
      billing_same_as_shipping: billingSameAsShipping.value,
      billing_address: billingSameAsShipping.value ? undefined : { ...billing },
    })
    await checkout.fetchShippingRates()
  } catch (e) {
    if (!(e instanceof ApiClientError)) throw e
  } finally {
    loadingRates.value = false
  }
}

function isSelectedRate(rate: StorefrontShippingRate): boolean {
  const selected = checkout.checkout.value?.selected_shipping_rate
  return !!selected && selected.provider === rate.provider && selected.service_code === rate.service_code
}

/** Radio should also read as checked while its pickup-point picker is expanded but not yet confirmed. */
function isRateChecked(rate: StorefrontShippingRate): boolean {
  return isSelectedRate(rate) || pendingPickupRate.value === rate
}

function handleRateChange(rate: StorefrontShippingRate) {
  if (rate.service_code === 'pickup') {
    pendingPickupRate.value = rate
    selectedPickupPointId.value = isSelectedRate(rate) ? checkout.checkout.value?.selected_shipping_rate?.pickup_point?.id ?? null : null
    return
  }

  pendingPickupRate.value = null
  selectedPickupPointId.value = null
  void handleSelectShippingRate(rate)
}

function handlePickupPointChange(rate: StorefrontShippingRate, point: StorefrontPickupPoint) {
  selectedPickupPointId.value = point.id
  void handleSelectShippingRate(rate, point.id)
}

async function handleSelectShippingRate(rate: StorefrontShippingRate, pickupPointId?: string | null) {
  selectingRate.value = true
  try {
    await checkout.selectShipping(rate, pickupPointId)
  } catch (e) {
    if (!(e instanceof ApiClientError)) throw e
  } finally {
    selectingRate.value = false
  }
}

async function handlePlaceOrder() {
  placingOrder.value = true
  try {
    await checkout.update({
      email: email.value,
      phone: phone.value || null,
      shipping_address: { ...shipping },
      billing_same_as_shipping: billingSameAsShipping.value,
      billing_address: billingSameAsShipping.value ? undefined : { ...billing },
    })

    const order = await checkout.complete()
    await navigateTo(`/order-confirmation/${order.id}`)
  } catch (e) {
    if (!(e instanceof ApiClientError)) throw e
  } finally {
    placingOrder.value = false
  }
}

useSeoMeta({
  title: 'Checkout',
})
</script>

<style scoped>
section {
  margin-bottom: 1.5rem;
}

label {
  display: block;
  margin-bottom: 0.75rem;
  font-size: 0.9rem;
}

label input {
  display: block;
  width: 100%;
  max-width: 360px;
  margin-top: 0.25rem;
  padding: 0.4rem;
  box-sizing: border-box;
}

.checkbox {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.checkbox input {
  width: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

td {
  padding: 0.35rem 0;
  border-bottom: 1px solid #e0e0e0;
}

.totals-line {
  margin: 0.5rem 0 0;
  text-align: right;
  color: #555;
}

.totals {
  margin-top: 0.75rem;
  text-align: right;
}

.rates {
  list-style: none;
  margin: 0.75rem 0 0;
  padding: 0;
}

.rates li {
  margin-bottom: 0.5rem;
}

.rates label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0;
}

.rates label input[type='radio'] {
  display: inline;
  width: auto;
}

.estimate {
  color: #777;
  font-size: 0.85em;
}

.pickup-points {
  margin: 0.5rem 0 0.75rem 1.75rem;
}

.pickup-points ul {
  list-style: none;
  margin: 0;
  padding: 0;
}

.pickup-points li {
  margin-bottom: 0.4rem;
}

.pickup-points label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0;
  font-size: 0.85rem;
}

.pickup-points label input[type='radio'] {
  display: inline;
  width: auto;
}

.muted {
  color: #777;
  font-size: 0.85rem;
}

button[type='submit'] {
  padding: 0.6rem 1.25rem;
  background: #1a1a1a;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}

button[type='submit']:disabled {
  opacity: 0.6;
  cursor: default;
}
</style>
