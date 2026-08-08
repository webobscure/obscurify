<template>
  <div>
    <h1>Cart</h1>

    <ClientOnly>
      <p v-if="cart.loading.value">Loading…</p>
      <template v-else-if="cart.cart.value && cart.cart.value.items.length">
        <table>
          <thead>
            <tr>
              <th/>
              <th>Item</th>
              <th>Price</th>
              <th>Quantity</th>
              <th>Line total</th>
              <th/>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in cart.cart.value.items" :key="item.id">
              <td><img v-if="item.media[0]" :src="item.media[0].url" :alt="item.media[0].alt ?? item.title" width="60"></td>
              <td>{{ item.title }} <span v-if="item.sku">({{ item.sku }})</span></td>
              <td>{{ formatMoney(item.price) }}</td>
              <td>
                <input
                  type="number"
                  min="1"
                  :value="item.quantity"
                  @change="handleUpdateQuantity(item.id, ($event.target as HTMLInputElement).valueAsNumber)"
                >
              </td>
              <td>{{ formatMoney(item.line_total) }}</td>
              <td><button type="button" @click="cart.removeItem(item.id)">Remove</button></td>
            </tr>
          </tbody>
        </table>

        <p class="totals">
          Subtotal: {{ formatMoney({ amount: cart.cart.value.items_subtotal, currency: cart.cart.value.currency }) }}<br>
          <strong>Total: {{ formatMoney({ amount: cart.cart.value.total, currency: cart.cart.value.currency }) }}</strong>
        </p>

        <NuxtLink to="/checkout" class="checkout-button">
          Proceed to checkout
        </NuxtLink>
      </template>
      <p v-else>Your cart is empty. <NuxtLink to="/products">Continue shopping</NuxtLink>.</p>

      <p v-if="cart.error.value" class="error">{{ cart.error.value }}</p>

      <template #fallback>
        <p>Loading…</p>
      </template>
    </ClientOnly>
  </div>
</template>

<script setup lang="ts">
const cart = useCart()

function handleUpdateQuantity(itemId: string, quantity: number) {
  if (!Number.isFinite(quantity) || quantity < 1) return
  cart.updateItem(itemId, quantity)
}

useSeoMeta({
  title: 'Cart',
})
</script>

<style scoped>
table {
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  text-align: left;
  padding: 0.5rem;
  border-bottom: 1px solid #e0e0e0;
}

input[type='number'] {
  width: 4rem;
}

.totals {
  margin-top: 1.5rem;
  text-align: right;
}

.checkout-button {
  display: inline-block;
  margin-top: 1rem;
  padding: 0.6rem 1.25rem;
  background: #1a1a1a;
  color: white;
  text-decoration: none;
  border-radius: 4px;
}
</style>
