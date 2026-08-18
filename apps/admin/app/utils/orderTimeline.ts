import type { Order } from '@obscurify/types'

/**
 * The Order Editor's activity feed (spec: "merge everything into one
 * chronological feed", GitHub/Linear pattern) is built entirely from
 * real data the order response already returns — no synthetic events
 * invented, no new backend endpoint. `financial_events` is documented
 * backend-side (packages/types) as *already* "the unified per-order
 * financial timeline (Payment captured / Refund requested / ... /
 * Ledger created)" — so it's the single source for payment/refund/
 * ledger moments, not re-derived from raw PaymentAttempt/Refund
 * timestamps (which would double-count the same moment two ways).
 * Fulfillment/Shipment/Return each carry their own `.events`/
 * `.tracking_events` sub-timeline; those, plus the order's own
 * created/cancelled timestamps and each fiscal receipt's `fiscalized_at`
 * (the one genuinely separate timestamp with no home in any `.events`
 * array), are the complete real event surface.
 */
export type TimelineSource = 'order' | 'financial' | 'fulfillment' | 'shipment' | 'return' | 'fiscal'

export interface TimelineEntry {
  id: string
  source: TimelineSource
  icon: string
  /** Raw backend type/status string (e.g. "payment_captured", "in_transit") — the caller resolves this to a translated label via a domain-specific i18n lookup, falling back to a humanized version of the string itself for values with no translation yet (never blocks rendering on an unmapped backend value). */
  typeKey: string
  description: string | null
  occurredAt: string
  location: string | null
}

function humanize(value: string): string {
  return value.replaceAll('_', ' ').replace(/^./, c => c.toUpperCase())
}

export function buildOrderTimeline(order: Order): TimelineEntry[] {
  const entries: TimelineEntry[] = []

  entries.push({
    id: `order-created-${order.id}`,
    source: 'order',
    icon: 'orders',
    typeKey: 'order_created',
    description: null,
    occurredAt: order.created_at,
    location: null,
  })

  if (order.cancelled_at) {
    entries.push({
      id: `order-cancelled-${order.id}`,
      source: 'order',
      icon: 'orders',
      typeKey: 'order_cancelled',
      description: null,
      occurredAt: order.cancelled_at,
      location: null,
    })
  }

  for (const event of order.financial_events ?? []) {
    entries.push({
      id: `financial-${event.id}`,
      source: 'financial',
      icon: 'payments',
      typeKey: event.type,
      description: event.description,
      occurredAt: event.occurred_at,
      location: null,
    })
  }

  for (const fulfillment of order.fulfillments ?? []) {
    for (const event of fulfillment.events ?? []) {
      entries.push({
        id: `fulfillment-${event.id}`,
        source: 'fulfillment',
        icon: 'fulfillment',
        typeKey: event.type,
        description: event.description,
        occurredAt: event.occurred_at,
        location: null,
      })
    }
  }

  for (const shipment of order.shipments ?? []) {
    for (const event of shipment.tracking_events ?? []) {
      entries.push({
        id: `shipment-${event.id}`,
        source: 'shipment',
        icon: 'shipping',
        typeKey: event.status,
        description: event.description,
        occurredAt: event.occurred_at,
        location: event.location,
      })
    }
  }

  for (const returnRequest of order.returns ?? []) {
    for (const event of returnRequest.events ?? []) {
      entries.push({
        id: `return-${event.id}`,
        source: 'return',
        icon: 'redirects',
        typeKey: event.type,
        description: event.description,
        occurredAt: event.occurred_at,
        location: null,
      })
    }
  }

  for (const receipt of order.fiscal_receipts ?? []) {
    if (receipt.fiscalized_at) {
      entries.push({
        id: `fiscal-${receipt.id}`,
        source: 'fiscal',
        icon: 'russian-commerce',
        typeKey: 'fiscal_receipt_fiscalized',
        description: null,
        occurredAt: receipt.fiscalized_at,
        location: null,
      })
    }
  }

  return entries.sort((a, b) => new Date(a.occurredAt).getTime() - new Date(b.occurredAt).getTime())
}

/** Groups already-sorted timeline entries by calendar day (spec: "group events by date") — the key is a plain YYYY-MM-DD, formatted for display by the caller (locale-aware). */
export function groupTimelineByDay(entries: TimelineEntry[]): Array<{ day: string; entries: TimelineEntry[] }> {
  const groups: Array<{ day: string; entries: TimelineEntry[] }> = []

  for (const entry of entries) {
    const day = entry.occurredAt.slice(0, 10)
    const lastGroup = groups.at(-1)
    if (lastGroup?.day === day) {
      lastGroup.entries.push(entry)
    } else {
      groups.push({ day, entries: [entry] })
    }
  }

  return groups
}

export { humanize as humanizeTimelineType }
