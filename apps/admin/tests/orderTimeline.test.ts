import { describe, expect, it } from 'vitest'
import type { Order } from '@obscurify/types'
import { buildOrderTimeline, groupTimelineByDay } from '../app/utils/orderTimeline'

function baseOrder(overrides: Partial<Order> = {}): Order {
  return {
    id: 'order-1',
    store_id: 'store-1',
    number: 1001,
    customer_id: null,
    checkout_id: null,
    email: 'buyer@example.com',
    phone: null,
    currency: 'RUB',
    items_subtotal_amount: 10000,
    shipping_amount: 500,
    discount_amount: 0,
    tax_amount: 0,
    total_amount: 10500,
    order_status: 'open',
    financial_status: 'paid',
    fulfillment_status: 'unfulfilled',
    cancelled_at: null,
    created_at: '2026-08-01T10:00:00Z',
    updated_at: '2026-08-01T10:00:00Z',
    ...overrides,
  }
}

describe('buildOrderTimeline', () => {
  it('always includes an order_created entry at the order source', () => {
    const timeline = buildOrderTimeline(baseOrder())
    expect(timeline).toHaveLength(1)
    expect(timeline[0]).toMatchObject({ source: 'order', typeKey: 'order_created', occurredAt: '2026-08-01T10:00:00Z' })
  })

  it('includes an order_cancelled entry only when cancelled_at is set', () => {
    const timeline = buildOrderTimeline(baseOrder({ cancelled_at: '2026-08-02T10:00:00Z' }))
    expect(timeline.some(e => e.typeKey === 'order_cancelled')).toBe(true)

    const notCancelled = buildOrderTimeline(baseOrder())
    expect(notCancelled.some(e => e.typeKey === 'order_cancelled')).toBe(false)
  })

  it('merges financial_events, fulfillment events, shipment tracking events, return events, and fiscal receipt fiscalization into one list', () => {
    const order = baseOrder({
      financial_events: [{ id: 'fe1', type: 'payment_captured', description: null, occurred_at: '2026-08-01T11:00:00Z' }],
      fulfillments: [{
        id: 'f1', order_id: 'order-1', status: 'completed', notes: null, created_by: null, completed_at: null,
        events: [{ id: 'fev1', type: 'created', description: null, occurred_at: '2026-08-01T12:00:00Z' }],
        created_at: '2026-08-01T12:00:00Z', updated_at: '2026-08-01T12:00:00Z',
      }],
      shipments: [{
        id: 's1', order_id: 'order-1', fulfillment_id: 'f1', provider: 'cdek', status: 'in_transit',
        tracking_number: 'TRACK1', tracking_url: null, shipped_at: null, delivered_at: null, cancelled_at: null,
        tracking_events: [{ id: 'te1', status: 'in_transit', description: 'Left warehouse', occurred_at: '2026-08-01T13:00:00Z', location: 'Moscow' }],
        created_at: '2026-08-01T13:00:00Z', updated_at: '2026-08-01T13:00:00Z',
      }],
      returns: [{
        id: 'r1', order_id: 'order-1', customer_id: null, number: 1, status: 'requested',
        requested_at: '2026-08-01T14:00:00Z', approved_at: null, received_at: null, closed_at: null, notes: null,
        events: [{ id: 'rev1', type: 'requested', description: null, occurred_at: '2026-08-01T14:00:00Z' }],
        created_at: '2026-08-01T14:00:00Z', updated_at: '2026-08-01T14:00:00Z',
      }],
      fiscal_receipts: [{
        id: 'fr1', order_id: 'order-1', payment_id: null, correction_of_id: null, operation: 'sell', status: 'fiscalized',
        provider: 'atol', external_receipt_id: null, seller_inn: '1234567890', seller_kpp: null,
        customer_email: null, customer_phone: null, currency: 'RUB', total_amount: 10500,
        fiscalized_at: '2026-08-01T15:00:00Z',
      } as Order['fiscal_receipts'] extends (infer T)[] | undefined ? T : never],
    })

    const timeline = buildOrderTimeline(order)
    const sources = timeline.map(e => e.source)
    expect(sources).toEqual(['order', 'financial', 'fulfillment', 'shipment', 'return', 'fiscal'])
  })

  it('does not include a fiscal entry when fiscalized_at is null (receipt not yet fiscalized)', () => {
    const order = baseOrder({
      fiscal_receipts: [{
        id: 'fr1', order_id: 'order-1', payment_id: null, correction_of_id: null, operation: 'sell', status: 'pending',
        provider: 'atol', external_receipt_id: null, seller_inn: '1234567890', seller_kpp: null,
        customer_email: null, customer_phone: null, currency: 'RUB', total_amount: 10500,
        fiscalized_at: null,
      } as Order['fiscal_receipts'] extends (infer T)[] | undefined ? T : never],
    })
    expect(buildOrderTimeline(order).some(e => e.source === 'fiscal')).toBe(false)
  })

  it('sorts every entry chronologically regardless of source order in the input', () => {
    const order = baseOrder({
      created_at: '2026-08-01T12:00:00Z',
      financial_events: [{ id: 'fe1', type: 'payment_captured', description: null, occurred_at: '2026-08-01T09:00:00Z' }],
    })
    const timeline = buildOrderTimeline(order)
    expect(timeline.map(e => e.occurredAt)).toEqual(['2026-08-01T09:00:00Z', '2026-08-01T12:00:00Z'])
  })

  it('carries shipment tracking event location through for display', () => {
    const order = baseOrder({
      shipments: [{
        id: 's1', order_id: 'order-1', fulfillment_id: 'f1', provider: 'cdek', status: 'in_transit',
        tracking_number: null, tracking_url: null, shipped_at: null, delivered_at: null, cancelled_at: null,
        tracking_events: [{ id: 'te1', status: 'in_transit', description: null, occurred_at: '2026-08-01T13:00:00Z', location: 'Moscow' }],
        created_at: '2026-08-01T13:00:00Z', updated_at: '2026-08-01T13:00:00Z',
      }],
    })
    const entry = buildOrderTimeline(order).find(e => e.source === 'shipment')
    expect(entry?.location).toBe('Moscow')
  })
})

describe('groupTimelineByDay', () => {
  it('groups consecutive same-day entries into one group', () => {
    const entries = buildOrderTimeline(baseOrder({
      created_at: '2026-08-01T09:00:00Z',
      financial_events: [
        { id: 'fe1', type: 'payment_captured', description: null, occurred_at: '2026-08-01T11:00:00Z' },
        { id: 'fe2', type: 'refund_requested', description: null, occurred_at: '2026-08-02T09:00:00Z' },
      ],
    }))

    const groups = groupTimelineByDay(entries)
    expect(groups.map(g => g.day)).toEqual(['2026-08-01', '2026-08-02'])
    expect(groups[0]!.entries).toHaveLength(2)
    expect(groups[1]!.entries).toHaveLength(1)
  })

  it('produces a single one-entry group for a fresh order (order_created is the only event)', () => {
    const groups = groupTimelineByDay(buildOrderTimeline(baseOrder()))
    expect(groups).toHaveLength(1)
    expect(groups[0]!.entries).toHaveLength(1)
  })
})
