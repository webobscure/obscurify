<template>
  <StatusBadge :label="label" :variant="variant" />
</template>

<script setup lang="ts">
import type { StatusBadgeVariant } from './StatusBadge.vue'

/**
 * The Orders-domain StatusBadge — one component covering every status
 * enum an order touches (order/financial/fulfillment/payment/shipment/
 * return/refund/fiscal), each its own bucket map rather than one shared
 * guess (per docs/design/DESIGN_SYSTEM.md §12: "a per-domain map object
 * consumed by StatusBadge's status prop... adding a new backend status
 * value never requires touching the component itself"). An unmapped
 * value falls back to the neutral bucket and its own humanized text
 * rather than throwing — same resilience `StatusBadge` itself documents.
 */
export type OrderStatusDomain = 'order' | 'financial' | 'fulfillment' | 'fulfillmentWorkflow' | 'payment' | 'shipment' | 'return' | 'refund' | 'fiscal'

const props = defineProps<{ status: string; domain: OrderStatusDomain }>()

const { t, te } = useI18n()

const buckets: Record<OrderStatusDomain, Record<string, StatusBadgeVariant>> = {
  order: { open: 'info', cancelled: 'danger', closed: 'neutral' },
  financial: { pending: 'warning', authorized: 'warning', paid: 'success', partially_refunded: 'warning', refunded: 'neutral', voided: 'danger' },
  fulfillment: { unfulfilled: 'neutral', partial: 'warning', fulfilled: 'success' },
  fulfillmentWorkflow: { pending: 'neutral', allocated: 'info', picking: 'warning', packing: 'warning', ready: 'info', completed: 'success', cancelled: 'neutral' },
  payment: { pending: 'neutral', processing: 'warning', authorized: 'warning', paid: 'success', failed: 'danger', cancelled: 'neutral', expired: 'danger', partially_refunded: 'warning', refunded: 'neutral' },
  shipment: { pending: 'neutral', ready: 'info', created: 'info', accepted: 'info', in_transit: 'warning', out_for_delivery: 'warning', delivered: 'success', delivery_exception: 'danger', failed: 'danger', cancelled: 'neutral' },
  return: { requested: 'warning', approved: 'info', awaiting_return: 'warning', received: 'info', inspection: 'warning', completed: 'success', rejected: 'danger', cancelled: 'neutral' },
  refund: { requested: 'warning', processing: 'warning', completed: 'success', failed: 'danger', cancelled: 'neutral' },
  fiscal: { pending: 'neutral', processing: 'warning', fiscalized: 'success', failed: 'danger', cancelled: 'neutral' },
}

const variant = computed<StatusBadgeVariant>(() => buckets[props.domain][props.status] ?? 'neutral')

const labelKey = computed(() => `orderStatuses.${props.domain}.${props.status}`)
const label = computed(() => (te(labelKey.value) ? t(labelKey.value) : props.status.replaceAll('_', ' ')))
</script>
