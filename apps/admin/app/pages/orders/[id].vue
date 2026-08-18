<template>
  <div v-if="loading" class="loading-shell">
    <Skeleton variant="block" height="120px" />
    <Skeleton variant="text" width="40%" />
    <Skeleton variant="text" width="60%" />
  </div>

  <div v-else-if="!order" class="not-found">
    <EmptyState :title="t('orderEditor.not_found')">
      <template #icon><AppIcon name="orders" /></template>
    </EmptyState>
  </div>

  <div v-else class="editor">
    <header class="header">
      <div>
        <AdminBreadcrumbs :items="[{ label: t('orderEditor.back_to_orders'), to: '/orders' }, { label: `#${order.number}` }]" />
        <div class="title-row">
          <h1>#{{ order.number }}</h1>
          <OrderStatusBadge :status="order.order_status" domain="order" />
          <OrderStatusBadge :status="order.financial_status" domain="financial" />
          <OrderStatusBadge :status="order.fulfillment_status" domain="fulfillment" />
        </div>
        <p class="meta">{{ formatDateTime(order.created_at) }} · {{ customerDisplayName }}</p>
      </div>
      <div class="header-actions">
        <div ref="kebabRoot" class="kebab-wrap">
          <IconButton icon="collections" :ariaLabel="t('common.more_actions')" aria-haspopup="menu" :active="menuOpen" @click="menuOpen = !menuOpen" />
          <div v-if="menuOpen" class="kebab-menu" role="menu">
            <button type="button" role="menuitem" @click="printOrder">{{ t('common.print') }}</button>
          </div>
        </div>
      </div>
    </header>

    <p v-if="error" class="error-banner">{{ error }}</p>

    <div class="body">
      <div class="main">
        <Card class="items-card">
          <template #header>{{ t('orderEditor.items') }} <span class="muted">· {{ t('orderEditor.item_count', { count: order.items?.length ?? 0 }) }}</span></template>
          <DataTable
            :columns="itemColumns"
            :rows="order.items ?? []"
            :row-key="(r) => (r as OrderItem).id"
            density="compact"
            :empty-title="t('orderEditor.items')"
          >
            <template #cell-item="{ row }">
              <div class="item-cell">
                <span class="item-title">{{ (row as OrderItem).product_title }}</span>
                <span v-if="(row as OrderItem).variant_title" class="item-variant">{{ (row as OrderItem).variant_title }}</span>
              </div>
            </template>
            <template #cell-sku="{ row }">{{ (row as OrderItem).sku ?? '—' }}</template>
            <template #cell-price="{ row }">{{ money((row as OrderItem).unit_price_amount, (row as OrderItem).currency) }}</template>
            <template #cell-qty="{ row }">{{ (row as OrderItem).quantity }}</template>
            <template #cell-subtotal="{ row }">{{ money((row as OrderItem).line_total_amount, (row as OrderItem).currency) }}</template>
          </DataTable>
        </Card>

        <Card>
          <template #header>{{ t('orderEditor.activity') }}</template>
          <Timeline v-if="timelineGroups.length" :groups="timelineGroups" :ariaLabel="t('orderEditor.activity')" />
          <p v-else class="empty-hint">{{ t('orderEditor.no_activity') }}</p>
        </Card>

        <Card>
          <div class="section-toolbar">
            <span class="card-title">{{ t('orderEditor.fulfillments') }} <span class="muted">· {{ order.fulfillments?.length ?? 0 }}</span></span>
            <Button v-if="order.financial_status === 'paid' && unfulfilledItems.length" size="sm" variant="secondary" @click="fulfillDrawerOpen = true">+ {{ t('orderEditor.create_fulfillment') }}</Button>
          </div>
          <ul v-if="order.fulfillments?.length" class="record-list">
            <li v-for="fulfillment in order.fulfillments" :key="fulfillment.id">
              <NuxtLink :to="`/fulfillments/${fulfillment.id}`" class="record-row">
                <OrderStatusBadge :status="fulfillment.status" domain="fulfillmentWorkflow" />
                <span class="record-detail">{{ t('orderEditor.item_count', { count: fulfillmentItemCount(fulfillment) }) }}</span>
                <AppIcon name="chevron" size="sm" class="chev" />
              </NuxtLink>
            </li>
          </ul>
          <p v-else class="empty-hint">{{ t('orderEditor.no_fulfillments') }}</p>
        </Card>

        <Card>
          <template #header>{{ t('orderEditor.shipments') }} <span class="muted">· {{ order.shipments?.length ?? 0 }}</span></template>
          <ul v-if="order.shipments?.length" class="record-list">
            <li v-for="shipment in order.shipments" :key="shipment.id">
              <NuxtLink :to="`/shipments/${shipment.id}`" class="record-row">
                <OrderStatusBadge :status="shipment.status" domain="shipment" />
                <span class="record-detail">{{ shipment.provider }}<template v-if="shipment.tracking_number"> · {{ shipment.tracking_number }}</template></span>
                <span class="record-detail">{{ t('orderEditor.item_count', { count: shipmentItemCount(shipment) }) }}</span>
                <AppIcon name="chevron" size="sm" class="chev" />
              </NuxtLink>
            </li>
          </ul>
          <p v-else class="empty-hint">{{ t('orderEditor.no_shipments') }}</p>
        </Card>

        <Card>
          <div class="section-toolbar">
            <span class="card-title">{{ t('orderEditor.returns') }} <span class="muted">· {{ order.returns?.length ?? 0 }}</span></span>
            <Button v-if="returnableItems.length" size="sm" variant="secondary" @click="returnDrawerOpen = true">+ {{ t('orderEditor.request_return') }}</Button>
          </div>
          <ul v-if="order.returns?.length" class="record-list">
            <li v-for="returnRequest in order.returns" :key="returnRequest.id">
              <NuxtLink :to="`/returns/${returnRequest.id}`" class="record-row">
                <span class="record-number">#{{ returnRequest.number }}</span>
                <OrderStatusBadge :status="returnRequest.status" domain="return" />
                <span class="record-detail">{{ t('orderEditor.item_count', { count: returnItemCount(returnRequest) }) }}</span>
                <AppIcon name="chevron" size="sm" class="chev" />
              </NuxtLink>
            </li>
          </ul>
          <p v-else class="empty-hint">{{ t('orderEditor.no_returns') }}</p>
        </Card>

        <Card>
          <template #header>{{ t('orderEditor.payments') }} <span class="muted">· {{ order.payments?.length ?? 0 }}</span></template>
          <ul v-if="order.payments?.length" class="record-list">
            <li v-for="payment in order.payments" :key="payment.id">
              <NuxtLink :to="`/payments/${payment.id}`" class="record-row">
                <span class="record-detail provider">{{ payment.provider }}</span>
                <OrderStatusBadge :status="payment.status" domain="payment" />
                <span class="record-detail num">{{ money(payment.amount, payment.currency) }}</span>
                <span v-if="payment.refunded_amount > 0" class="record-detail muted">{{ t('orderEditor.refunds') }}: {{ money(payment.refunded_amount, payment.currency) }}</span>
                <AppIcon name="chevron" size="sm" class="chev" />
              </NuxtLink>
            </li>
          </ul>
          <p v-else class="empty-hint">{{ t('orderEditor.no_payments') }}</p>
        </Card>

        <Card>
          <div class="section-toolbar">
            <span class="card-title">{{ t('orderEditor.refunds') }} <span class="muted">· {{ order.refunds?.length ?? 0 }}</span></span>
            <Button v-if="(order.payments?.length ?? 0) > 0 && (refundableItems.length || refundableShipping > 0)" size="sm" variant="secondary" @click="refundDrawerOpen = true">+ {{ t('orderEditor.request_refund') }}</Button>
          </div>
          <ul v-if="order.refunds?.length" class="record-list">
            <li v-for="refund in order.refunds" :key="refund.id">
              <NuxtLink :to="`/refunds/${refund.id}`" class="record-row">
                <span class="record-number">#{{ refund.number }}</span>
                <OrderStatusBadge :status="refund.status" domain="refund" />
                <span class="record-detail num">{{ money(refund.amount, refund.currency) }}</span>
                <AppIcon name="chevron" size="sm" class="chev" />
              </NuxtLink>
            </li>
          </ul>
          <p v-else class="empty-hint">{{ t('orderEditor.no_refunds') }}</p>
        </Card>

        <Card v-if="order.fiscal_snapshot">
          <template #header>{{ t('orderEditor.fiscalization') }}</template>
          <KeyValueTable :rows="fiscalRows" />
          <p class="fiscal-receipts-label">{{ t('orderEditor.fiscal_receipts') }}</p>
          <ul v-if="order.fiscal_receipts?.length" class="record-list">
            <li v-for="receipt in order.fiscal_receipts" :key="receipt.id">
              <NuxtLink :to="`/russian-commerce/fiscal-receipts/${receipt.id}`" class="record-row">
                <OrderStatusBadge :status="receipt.status" domain="fiscal" />
                <span class="record-detail">{{ receipt.provider }}</span>
                <span class="record-detail num">{{ money(receipt.total_amount, receipt.currency) }}</span>
                <AppIcon name="chevron" size="sm" class="chev" />
              </NuxtLink>
            </li>
          </ul>
          <p v-else-if="order.fiscal_snapshot.receipt_required" class="empty-hint">{{ t('orderEditor.no_fiscal_receipts') }}</p>
        </Card>
      </div>

      <aside class="sidebar">
        <div class="rail">
          <div class="rail-sec">
            <h3 class="rail-h">{{ t('orderEditor.customer') }}</h3>
            <div class="customer-block">
              <Avatar :name="customerDisplayName" size="md" />
              <div class="customer-info">
                <span class="customer-name">{{ customerDisplayName }}</span>
                <span v-if="order.customer?.email ?? order.email" class="customer-contact">{{ order.customer?.email ?? order.email }}</span>
                <span v-if="order.customer?.phone ?? order.phone" class="customer-contact">{{ order.customer?.phone ?? order.phone }}</span>
              </div>
            </div>
            <template v-if="customerMetrics">
              <div class="kv"><span>{{ t('orderEditor.lifetime_value') }}</span><b>{{ money(customerMetrics.lifetime_value_amount, customerMetrics.currency ?? order.currency) }}</b></div>
              <div class="kv"><span>{{ t('orderEditor.total_spent') }}</span><b>{{ money(customerMetrics.total_spent_amount, customerMetrics.currency ?? order.currency) }}</b></div>
              <div class="kv"><span>{{ t('orderEditor.order_count') }}</span><b>{{ customerMetrics.order_count }}</b></div>
            </template>
            <NuxtLink v-if="order.customer_id" :to="`/customers/${order.customer_id}`" class="link">{{ t('orderEditor.view_customer') }}</NuxtLink>
          </div>

          <div class="rail-sec">
            <h3 class="rail-h">{{ t('orderEditor.shipping_address') }}</h3>
            <p v-if="order.shipping_address" class="address">
              {{ order.shipping_address.first_name }} {{ order.shipping_address.last_name }}<br>
              {{ order.shipping_address.address_line1 }}<br>
              <template v-if="order.shipping_address.address_line2">{{ order.shipping_address.address_line2 }}<br></template>
              {{ order.shipping_address.city }}<span v-if="order.shipping_address.region">, {{ order.shipping_address.region }}</span> {{ order.shipping_address.postal_code }}<br>
              {{ order.shipping_address.country_code }}
            </p>
            <p v-else class="empty-hint">{{ t('orderEditor.no_address') }}</p>
          </div>

          <div class="rail-sec">
            <h3 class="rail-h">{{ t('orderEditor.billing_address') }}</h3>
            <p v-if="billingSameAsShipping" class="empty-hint">{{ t('orderEditor.same_as_shipping') }}</p>
            <p v-else-if="order.billing_address" class="address">
              {{ order.billing_address.first_name }} {{ order.billing_address.last_name }}<br>
              {{ order.billing_address.address_line1 }}<br>
              <template v-if="order.billing_address.address_line2">{{ order.billing_address.address_line2 }}<br></template>
              {{ order.billing_address.city }}<span v-if="order.billing_address.region">, {{ order.billing_address.region }}</span> {{ order.billing_address.postal_code }}<br>
              {{ order.billing_address.country_code }}
            </p>
            <p v-else class="empty-hint">{{ t('orderEditor.no_address') }}</p>
          </div>

          <div class="rail-sec">
            <h3 class="rail-h">{{ t('orderEditor.totals') }}</h3>
            <div class="kv"><span>{{ t('orderEditor.subtotal') }}</span><b>{{ money(order.items_subtotal_amount, order.currency) }}</b></div>
            <div class="kv">
              <span>{{ t('orderEditor.shipping') }}</span>
              <b>{{ money(order.shipping_amount, order.currency) }}</b>
            </div>
            <p v-if="order.shipping_line" class="rail-note">
              {{ order.shipping_line.name }}
              <template v-if="order.shipping_line.pickup_point">
                <br>{{ t('orderEditor.pickup_at') }}: {{ order.shipping_line.pickup_point.name }} — {{ order.shipping_line.pickup_point.address }}, {{ order.shipping_line.pickup_point.city }}
              </template>
            </p>
            <div class="kv"><span>{{ t('orderEditor.discount') }}</span><b>{{ money(order.discount_amount, order.currency) }}</b></div>
            <p v-if="order.discount_applications?.length" class="rail-note">
              <span v-for="application in order.discount_applications" :key="application.id">
                {{ application.promotion_name }}<template v-if="application.code"> ({{ application.code }})</template> — {{ money(application.amount, application.currency) }}<br>
              </span>
            </p>
            <div class="kv"><span>{{ t('orderEditor.tax') }}</span><b>{{ money(order.tax_amount, order.currency) }}</b></div>
            <div class="kv total"><span>{{ t('orderEditor.total') }}</span><b>{{ money(order.total_amount, order.currency) }}</b></div>
          </div>
        </div>
      </aside>
    </div>

    <Drawer v-model:open="fulfillDrawerOpen" :title="t('orderEditor.create_fulfillment')">
      <form class="stack" @submit.prevent="submitCreateFulfillment">
        <p class="drawer-hint">{{ t('orderEditor.select_items') }}</p>
        <div v-for="line in unfulfilledItems" :key="line.item.id" class="pick-row">
          <div class="pick-row-main">
            <Checkbox v-model="line.selected" />
            <div class="pick-info">
              <span>{{ line.item.product_title }}<template v-if="line.item.variant_title"> ({{ line.item.variant_title }})</template></span>
              <span class="muted">{{ t('orderEditor.remaining') }}: {{ line.remaining }}</span>
            </div>
          </div>
          <div class="pick-controls">
            <input v-model.number="line.quantity" type="number" min="1" :max="line.remaining" class="qty-input" :disabled="!line.selected">
          </div>
        </div>
        <Button type="submit" variant="primary" :loading="creatingFulfillment">{{ t('orderEditor.create_fulfillment') }}</Button>
        <Alert v-if="fulfillmentError" variant="danger">{{ fulfillmentError }}</Alert>
      </form>
    </Drawer>

    <Drawer v-model:open="returnDrawerOpen" :title="t('orderEditor.request_return')">
      <form class="stack" @submit.prevent="submitCreateReturn">
        <p class="drawer-hint">{{ t('orderEditor.select_items') }}</p>
        <div v-for="line in returnableItems" :key="line.item.id" class="pick-row">
          <div class="pick-row-main">
            <Checkbox v-model="line.selected" />
            <div class="pick-info">
              <span>{{ line.item.product_title }}<template v-if="line.item.variant_title"> ({{ line.item.variant_title }})</template></span>
              <span class="muted">{{ t('orderEditor.returnable_qty') }}: {{ line.returnable }}</span>
            </div>
          </div>
          <div class="pick-controls">
            <input v-model.number="line.quantity" type="number" min="1" :max="line.returnable" class="qty-input" :disabled="!line.selected">
            <select v-model="line.reason" class="reason-select" :disabled="!line.selected">
              <option v-for="r in RETURN_REASONS" :key="r" :value="r">{{ r }}</option>
            </select>
          </div>
        </div>
        <Button type="submit" variant="primary" :loading="creatingReturn">{{ t('orderEditor.request_return') }}</Button>
        <Alert v-if="returnError" variant="danger">{{ returnError }}</Alert>
      </form>
    </Drawer>

    <Drawer v-model:open="refundDrawerOpen" :title="t('orderEditor.request_refund')">
      <form class="stack" @submit.prevent="submitCreateRefund">
        <template v-if="refundableItems.length">
          <p class="drawer-hint">{{ t('orderEditor.select_items') }}</p>
          <div v-for="line in refundableItems" :key="line.returnItemId" class="pick-row">
            <div class="pick-row-main">
              <Checkbox v-model="line.selected" />
              <div class="pick-info">
                <span>{{ line.productTitle }}</span>
                <span class="muted">{{ t('orderEditor.refundable_qty') }}: {{ line.refundable }}</span>
              </div>
            </div>
            <div class="pick-controls">
              <input v-model.number="line.quantity" type="number" min="1" :max="line.refundable" class="qty-input" :disabled="!line.selected">
              <MoneyInput v-model="line.amount" :currency="order.currency" class="qty-input" :disabled="!line.selected" />
            </div>
          </div>
        </template>

        <label v-if="refundableShipping > 0" class="field">
          <span>{{ t('orderEditor.refund_shipping') }} ({{ t('common.max') }} {{ money(refundableShipping, order.currency) }})</span>
          <MoneyInput v-model="shippingRefundAmount" :currency="order.currency" />
        </label>
        <label class="field">
          <span>{{ t('orderEditor.refund_adjustment') }}</span>
          <MoneyInput v-model="adjustmentRefundAmount" :currency="order.currency" />
        </label>
        <Input v-model="refundReason" :label="t('orderEditor.refund_reason')" />
        <Select v-model="refundProviderModel" :label="t('orderEditor.refund_provider')">
          <option value="">{{ t('orderEditor.refund_provider_manual') }}</option>
          <option value="fake">Fake provider</option>
        </Select>

        <Button type="submit" variant="primary" :loading="creatingRefund">{{ t('orderEditor.request_refund') }}</Button>
        <Alert v-if="refundError" variant="danger">{{ refundError }}</Alert>
      </form>
    </Drawer>
  </div>
</template>

<script setup lang="ts">
import type { CustomerMetric, Fulfillment, Order, OrderItem, Refund, ReturnReason, ReturnRequest, Shipment } from '@obscurify/types'
import { ApiClientError } from '@obscurify/api-client'
import { buildOrderTimeline, groupTimelineByDay } from '~/utils/orderTimeline'

const RETURN_REASONS: ReturnReason[] = ['wrong_size', 'damaged', 'not_as_described', 'ordered_by_mistake', 'defective', 'other']

const route = useRoute()
const { t, te, locale } = useI18n()
const api = useApi()
const orderId = route.params.id as string

const order = ref<Order | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const menuOpen = ref(false)
const kebabRoot = ref<HTMLElement | null>(null)
const customerMetrics = ref<CustomerMetric | null>(null)

const fulfillDrawerOpen = ref(false)
const returnDrawerOpen = ref(false)
const refundDrawerOpen = ref(false)

const creatingFulfillment = ref(false)
const fulfillmentError = ref<string | null>(null)
const creatingReturn = ref(false)
const returnError = ref<string | null>(null)
const creatingRefund = ref(false)
const refundError = ref<string | null>(null)
const shippingRefundAmount = ref<number | null>(0)
const adjustmentRefundAmount = ref<number | null>(0)
const refundReason = ref('')
const refundProviderModel = ref('')

async function load() {
  loading.value = true
  error.value = null
  try {
    const response = await api.orders.get(orderId)
    order.value = response.data
    if (response.data.customer_id) {
      const metricsResponse = await api.customers.metrics(response.data.customer_id).catch(() => null)
      const metricsData = metricsResponse?.data
      // The metrics endpoint serializes an absent CustomerMetric as `[]`
      // rather than `null` (Laravel casts a null JsonResource resource to
      // an empty array) — treat anything that isn't a real metric object
      // as "not computed yet" so the sidebar doesn't render NaN amounts.
      customerMetrics.value = metricsData && !Array.isArray(metricsData) ? metricsData : null
    }
  } catch (e) {
    error.value = e instanceof ApiClientError ? e.message : t('common.something_went_wrong')
  } finally {
    loading.value = false
  }
}

const itemColumns = [
  { key: 'item', label: t('orderEditor.col_item') },
  { key: 'sku', label: t('orderEditor.col_sku') },
  { key: 'price', label: t('orderEditor.col_price'), align: 'right' as const },
  { key: 'qty', label: t('orderEditor.col_qty'), align: 'right' as const },
  { key: 'subtotal', label: t('orderEditor.col_subtotal'), align: 'right' as const },
]

const intlLocale = computed(() => (locale.value === 'ru' ? 'ru-RU' : locale.value))
function money(amount: number, currency: string) {
  return formatMoney({ amount, currency }, intlLocale.value)
}
function formatDateTime(value: string) {
  return new Intl.DateTimeFormat(intlLocale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

const customerDisplayName = computed(() => {
  if (!order.value) return ''
  const c = order.value.customer
  const name = c ? `${c.first_name ?? ''} ${c.last_name ?? ''}`.trim() : ''
  return name || order.value.email || t('orderEditor.no_customer')
})

const billingSameAsShipping = computed(() => {
  if (!order.value?.billing_address || !order.value?.shipping_address) return false
  return JSON.stringify(order.value.billing_address) === JSON.stringify(order.value.shipping_address)
})

const fiscalRows = computed(() => {
  if (!order.value?.fiscal_snapshot) return []
  const snapshot = order.value.fiscal_snapshot
  return [
    { label: t('orderEditor.seller'), value: `${snapshot.seller_legal_name} (INN ${snapshot.seller_inn}${snapshot.seller_kpp ? `, KPP ${snapshot.seller_kpp}` : ''})` },
    { label: t('orderEditor.vat'), value: `${snapshot.vat_rate} (${money(snapshot.vat_amount, order.value.currency)})` },
    { label: t('orderEditor.receipt_required'), value: snapshot.receipt_required ? t('common.yes') : t('common.no') },
  ]
})

/**
 * The activity feed's chronological entries are built entirely from real
 * data already on the order (see app/utils/orderTimeline.ts) — each
 * entry's title resolves through `orderTimeline.{source}.{typeKey}` when
 * translated, falling back to a humanized raw string for any backend
 * value not yet in the map (never blocks rendering on an unmapped type).
 */
const timelineGroups = computed(() => {
  if (!order.value) return []
  const entries = buildOrderTimeline(order.value)
  const displayEntries = entries.map(entry => ({
    id: entry.id,
    icon: entry.icon,
    title: resolveTimelineTitle(entry.source, entry.typeKey),
    description: entry.location ? `${entry.description ? `${entry.description} — ` : ''}${entry.location}` : entry.description,
    occurredAt: entry.occurredAt,
  }))
  return groupTimelineByDay(entries.map((e, i) => ({ ...e, title: displayEntries[i]!.title }))).map(group => ({
    day: group.day,
    entries: group.entries.map(e => displayEntries.find(d => d.id === e.id)!),
  }))
})

function resolveTimelineTitle(source: string, typeKey: string): string {
  const key = `orderTimeline.${source}.${typeKey}`
  return te(key) ? t(key) : typeKey.replaceAll('_', ' ').replace(/^./, c => c.toUpperCase())
}

function fulfillmentItemCount(fulfillment: Fulfillment) {
  return fulfillment.items?.reduce((sum, i) => sum + i.quantity, 0) ?? 0
}
function shipmentItemCount(shipment: Shipment) {
  return shipment.items?.reduce((sum, i) => sum + i.quantity, 0) ?? 0
}
function returnItemCount(returnRequest: ReturnRequest) {
  return returnRequest.items?.reduce((sum, i) => sum + i.quantity, 0) ?? 0
}

function printOrder() {
  menuOpen.value = false
  window.print()
}

function handleClickOutsideKebab(event: MouseEvent) {
  if (menuOpen.value && kebabRoot.value && !kebabRoot.value.contains(event.target as Node)) {
    menuOpen.value = false
  }
}
onMounted(() => document.addEventListener('mousedown', handleClickOutsideKebab))
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutsideKebab))

/**
 * Remaining-to-fulfill quantity per OrderItem — ordered quantity minus
 * what's already on any (non-cancelled) fulfillment for this order. The
 * backend is still the real guard against over-fulfilling
 * (CreateFulfillment locks the OrderItem row); this is purely a UI
 * convenience so the merchant isn't offered a quantity the request would
 * reject anyway.
 */
const unfulfilledItems = computed(() => {
  if (!order.value) return []

  const fulfilledByItem = new Map<string, number>()
  for (const fulfillment of order.value.fulfillments ?? []) {
    if (fulfillment.status === 'cancelled') continue
    for (const fulfillmentItem of fulfillment.items ?? []) {
      fulfilledByItem.set(fulfillmentItem.order_item_id, (fulfilledByItem.get(fulfillmentItem.order_item_id) ?? 0) + fulfillmentItem.quantity)
    }
  }

  return (order.value.items ?? [])
    .map(item => ({
      item,
      remaining: item.quantity - (fulfilledByItem.get(item.id) ?? 0),
      selected: false,
      quantity: item.quantity - (fulfilledByItem.get(item.id) ?? 0),
    }))
    .filter(line => line.remaining > 0)
})

/**
 * Returnable quantity per OrderItem — shipped quantity (across non-
 * cancelled Shipments) minus what's already claimed by any non-rejected,
 * non-cancelled ReturnRequest. The backend is still the real guard
 * (RequestReturn locks the OrderItem row and re-derives this exact
 * number) — this is purely a UI convenience, same reasoning as
 * unfulfilledItems above.
 */
const returnableItems = computed(() => {
  if (!order.value) return []

  const shippedByItem = new Map<string, number>()
  for (const shipment of order.value.shipments ?? []) {
    if (shipment.status === 'cancelled') continue
    for (const shipmentItem of shipment.items ?? []) {
      shippedByItem.set(shipmentItem.order_item_id, (shippedByItem.get(shipmentItem.order_item_id) ?? 0) + shipmentItem.quantity)
    }
  }

  const returnedByItem = new Map<string, number>()
  for (const returnRequest of order.value.returns ?? []) {
    if (returnRequest.status === 'rejected' || returnRequest.status === 'cancelled') continue
    for (const returnItem of returnRequest.items ?? []) {
      returnedByItem.set(returnItem.order_item_id, (returnedByItem.get(returnItem.order_item_id) ?? 0) + returnItem.quantity)
    }
  }

  return (order.value.items ?? [])
    .map(item => ({
      item,
      returnable: (shippedByItem.get(item.id) ?? 0) - (returnedByItem.get(item.id) ?? 0),
      selected: false,
      quantity: (shippedByItem.get(item.id) ?? 0) - (returnedByItem.get(item.id) ?? 0),
      reason: 'other' as ReturnReason,
    }))
    .filter(line => line.returnable > 0)
})

async function submitCreateReturn() {
  const items = returnableItems.value
    .filter(line => line.selected && line.quantity > 0)
    .map(line => ({ order_item_id: line.item.id, quantity: line.quantity, reason: line.reason }))

  if (items.length === 0) {
    returnError.value = 'Select at least one item to return.'
    return
  }

  creatingReturn.value = true
  returnError.value = null
  try {
    await api.returns.create(orderId, { items })
    returnDrawerOpen.value = false
    await load()
  } catch (e) {
    returnError.value = e instanceof ApiClientError ? e.message : t('common.something_went_wrong')
  } finally {
    creatingReturn.value = false
  }
}

/**
 * Refundable quantity per completed ReturnItem — its own quantity minus
 * whatever is already claimed by any non-failed, non-cancelled Refund
 * (RequestRefund locks the ReturnItem row and re-derives this exact
 * number server-side; this is purely a UI convenience). Only ReturnItems
 * belonging to a `completed` ReturnRequest are offered (a refund only
 * makes sense once inspection/disposition finished). `amount` defaults
 * to the OrderItem's own per-unit price × quantity — a starting point
 * the merchant can still adjust, never trusted as-is by the backend.
 */
const refundableItems = computed(() => {
  if (!order.value) return []

  const orderItemById = new Map((order.value.items ?? []).map(item => [item.id, item]))

  const refundedByReturnItem = new Map<string, number>()
  for (const refund of order.value.refunds ?? []) {
    if (refund.status === 'failed' || refund.status === 'cancelled') continue
    for (const refundItem of refund.items ?? []) {
      refundedByReturnItem.set(refundItem.return_item_id, (refundedByReturnItem.get(refundItem.return_item_id) ?? 0) + refundItem.quantity)
    }
  }

  const lines: { returnItemId: string; productTitle: string; refundable: number; selected: boolean; quantity: number; amount: number | null }[] = []

  for (const returnRequest of order.value.returns ?? []) {
    if (returnRequest.status !== 'completed') continue

    for (const returnItem of returnRequest.items ?? []) {
      const refundable = returnItem.quantity - (refundedByReturnItem.get(returnItem.id) ?? 0)
      if (refundable <= 0) continue

      const orderItem = orderItemById.get(returnItem.order_item_id)
      const unitPrice = orderItem ? Math.round(orderItem.line_total_amount / orderItem.quantity) : 0

      lines.push({
        returnItemId: returnItem.id,
        productTitle: orderItem ? `${orderItem.product_title}${orderItem.variant_title ? ` (${orderItem.variant_title})` : ''}` : returnItem.order_item_id,
        refundable,
        selected: false,
        quantity: refundable,
        amount: unitPrice * refundable,
      })
    }
  }

  return lines
})

/** Remaining shipping refund capacity — order.shipping_amount minus what's already claimed by any non-failed, non-cancelled Refund. */
const refundableShipping = computed(() => {
  if (!order.value) return 0

  const alreadyRefunded = (order.value.refunds ?? [])
    .filter((refund: Refund) => refund.status !== 'failed' && refund.status !== 'cancelled')
    .reduce((sum, refund) => sum + refund.shipping_amount, 0)

  return Math.max(0, order.value.shipping_amount - alreadyRefunded)
})

async function submitCreateRefund() {
  const items = refundableItems.value
    .filter(line => line.selected && line.quantity > 0 && (line.amount ?? 0) > 0)
    .map(line => ({ return_item_id: line.returnItemId, quantity: line.quantity, amount: line.amount ?? 0 }))

  if (items.length === 0 && (shippingRefundAmount.value ?? 0) <= 0 && (adjustmentRefundAmount.value ?? 0) <= 0) {
    refundError.value = 'Select at least one item, or a shipping/adjustment amount, to refund.'
    return
  }

  creatingRefund.value = true
  refundError.value = null
  try {
    await api.refunds.create(orderId, {
      items,
      shipping_amount: shippingRefundAmount.value || undefined,
      adjustment_amount: adjustmentRefundAmount.value || undefined,
      reason: refundReason.value || null,
      provider: refundProviderModel.value || null,
    }, crypto.randomUUID())
    shippingRefundAmount.value = 0
    adjustmentRefundAmount.value = 0
    refundReason.value = ''
    refundDrawerOpen.value = false
    await load()
  } catch (e) {
    refundError.value = e instanceof ApiClientError ? e.message : t('common.something_went_wrong')
  } finally {
    creatingRefund.value = false
  }
}

async function submitCreateFulfillment() {
  const items = unfulfilledItems.value
    .filter(line => line.selected && line.quantity > 0)
    .map(line => ({ order_item_id: line.item.id, quantity: line.quantity }))

  if (items.length === 0) {
    fulfillmentError.value = 'Select at least one item to fulfill.'
    return
  }

  creatingFulfillment.value = true
  fulfillmentError.value = null
  try {
    await api.fulfillments.create(orderId, { items })
    fulfillDrawerOpen.value = false
    await load()
  } catch (e) {
    fulfillmentError.value = e instanceof ApiClientError ? e.message : t('common.something_went_wrong')
  } finally {
    creatingFulfillment.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.loading-shell {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  max-width: 900px;
}

.not-found { max-width: 480px; margin: var(--space-8) auto; }

.editor { --sidebar-rail-width: 300px; }

.header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-4);
  padding-bottom: var(--space-3);
  border-bottom: var(--border-width) solid var(--color-border);
  margin-bottom: var(--space-4);
}

.title-row {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin-top: var(--space-1);
}

.title-row h1 {
  font-size: var(--text-2xl);
  margin: 0;
}

.meta {
  margin: var(--space-1) 0 0;
  font-size: var(--text-sm);
  color: var(--color-text-muted);
}

.header-actions { display: flex; align-items: center; gap: var(--space-2); flex-shrink: 0; }

.kebab-wrap { position: relative; }

.kebab-menu {
  position: absolute;
  top: calc(100% + var(--space-1));
  right: 0;
  min-width: 160px;
  background: var(--color-surface-raised);
  border: var(--border-width) solid var(--color-border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-sm);
  padding: var(--space-1);
  z-index: 20;
}
.kebab-menu button {
  display: block;
  width: 100%;
  text-align: left;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-sm);
  font-size: var(--text-sm);
  color: var(--color-text);
}
.kebab-menu button:hover { background: var(--color-surface-muted); }

.error-banner {
  color: var(--color-danger);
  background: var(--color-danger-bg);
  border: var(--border-width) solid var(--color-danger-border);
  border-radius: var(--radius-md);
  padding: var(--space-3);
  font-size: var(--text-sm);
  margin-bottom: var(--space-4);
}

.body {
  display: grid;
  grid-template-columns: 1fr var(--sidebar-rail-width);
  gap: var(--space-6);
  align-items: start;
}

@media (max-width: 900px) {
  .body { grid-template-columns: 1fr; }
}

.main {
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  min-width: 0;
}

.muted { color: var(--color-text-subtle); font-weight: normal; }

.card-title { font-size: var(--text-lg); font-weight: var(--font-weight-semibold); }

.section-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}

.item-cell { display: flex; flex-direction: column; }
.item-title { color: var(--color-text); }
.item-variant { font-size: var(--text-xs); color: var(--color-text-muted); }

.empty-hint {
  margin: 0;
  font-size: var(--text-sm);
  color: var(--color-text-subtle);
  padding: var(--space-3) 0;
}

.record-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 1px; }

.record-row {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) var(--space-2);
  border-radius: var(--radius-sm);
  color: var(--color-text);
  text-decoration: none;
  font-size: var(--text-sm);
}
.record-row:hover { background: var(--color-surface-muted); }

.record-number { font-weight: var(--font-weight-medium); font-variant-numeric: tabular-nums; }
.record-detail { color: var(--color-text-muted); }
.record-detail.provider { color: var(--color-text); font-weight: var(--font-weight-medium); }
.record-detail.num { font-variant-numeric: tabular-nums; margin-left: auto; }
.chev { color: var(--color-text-subtle); margin-left: auto; }
.record-detail.num + .chev { margin-left: var(--space-2); }

.fiscal-receipts-label {
  margin: var(--space-4) 0 var(--space-2);
  font-size: var(--text-xs);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-subtle);
  font-weight: var(--font-weight-semibold);
}

/* Sidebar rail */
.sidebar { min-width: 0; }

.rail {
  position: sticky;
  top: calc(var(--topbar-height) + var(--space-4));
  background: var(--color-surface-muted);
  border-radius: var(--radius-lg);
  padding: 0 var(--space-4);
}

.rail-sec { padding: var(--space-4) 0; border-bottom: var(--border-width) solid var(--color-border); }
.rail-sec:first-child { padding-top: var(--space-3); }
.rail-sec:last-child { padding-bottom: var(--space-3); border-bottom: none; }

.rail-h {
  margin: 0 0 var(--space-3);
  font-size: 10.5px;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--color-text-subtle);
  font-weight: var(--font-weight-semibold);
}

.customer-block { display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-3); }
.customer-info { display: flex; flex-direction: column; min-width: 0; }
.customer-name { font-size: var(--text-sm); font-weight: var(--font-weight-medium); color: var(--color-text); }
.customer-contact { font-size: var(--text-xs); color: var(--color-text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.address { margin: 0; font-size: var(--text-sm); color: var(--color-text); line-height: var(--leading-normal); }

.kv {
  display: flex;
  justify-content: space-between;
  gap: var(--space-2);
  font-size: var(--text-sm);
  color: var(--color-text-muted);
  padding: 3px 0;
}
.kv b { color: var(--color-text); font-weight: var(--font-weight-medium); font-variant-numeric: tabular-nums; }
.kv.total { border-top: var(--border-width) solid var(--color-border); margin-top: var(--space-1); padding-top: var(--space-2); }
.kv.total b { font-size: var(--text-base); }

.rail-note { margin: 0 0 var(--space-1); font-size: var(--text-xs); color: var(--color-text-subtle); }

.link { display: inline-block; margin-top: var(--space-1); font-size: var(--text-sm); color: var(--color-accent); }
.link:hover { text-decoration: underline; }

/* Drawer forms */
.stack { display: flex; flex-direction: column; gap: var(--space-3); }
.drawer-hint { margin: 0; font-size: var(--text-sm); color: var(--color-text-muted); }

.pick-row {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-2) 0;
  border-bottom: var(--border-width) solid var(--color-border);
}
.pick-row-main { display: flex; align-items: center; gap: var(--space-2); }
.pick-controls { display: flex; align-items: center; gap: var(--space-2); padding-left: calc(20px + var(--space-2)); }
.pick-info { flex: 1; display: flex; flex-direction: column; gap: 2px; font-size: var(--text-sm); }
.qty-input { width: 90px; flex-shrink: 0; }
.reason-select { width: 160px; flex-shrink: 0; }

@media print {
  .header-actions, .sidebar .rail-sec:last-child .link, aside.sidebar :is(.section-toolbar button) { display: none; }
}
</style>
