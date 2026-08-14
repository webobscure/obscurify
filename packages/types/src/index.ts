export type StoreStatus = 'active' | 'suspended'
export type StoreUserRole = 'owner' | 'administrator' | 'manager'
export type StoreUserStatus = 'active' | 'invited' | 'suspended'
export type ProductStatus = 'draft' | 'active' | 'archived'
export type CollectionStatus = 'draft' | 'active' | 'archived'
export type LocationStatus = 'active' | 'inactive'
export type MediaEntityType = 'product' | 'product_variant'
export type MediaType = 'image' | 'video'
export type InventoryMovementReason =
  | 'manual_adjustment'
  | 'initial_stock'
  | 'import'
  | 'return'
  | 'damage'
  | 'correction'
export type ShippingZoneStatus = 'active' | 'inactive'
export type ShippingMethodStatus = 'active' | 'inactive'
export type ShipmentStatus =
  | 'pending'
  | 'ready'
  | 'created'
  | 'accepted'
  | 'in_transit'
  | 'out_for_delivery'
  | 'delivered'
  | 'delivery_exception'
  | 'failed'
  | 'cancelled'
export type TrackingEventStatus =
  | 'created'
  | 'accepted'
  | 'in_transit'
  | 'out_for_delivery'
  | 'delivered'
  | 'delivery_exception'
  | 'failed'
  | 'cancelled'

export interface User {
  id: string
  name: string
  email: string
  created_at: string
}

export interface Store {
  id: string
  owner_id: string
  name: string
  slug: string
  status: StoreStatus
  default_currency: string
  default_locale: string
  timezone: string
  settings: Record<string, unknown> | null
  created_at: string
  updated_at: string
}

export interface ProductOptionValue {
  id: string
  store_id: string
  product_option_id: string
  value: string
  position: number
  created_at: string
  updated_at: string
}

export interface ProductOption {
  id: string
  store_id: string
  product_id: string
  name: string
  position: number
  values?: ProductOptionValue[]
  created_at: string
  updated_at: string
}

export interface ProductVariant {
  id: string
  store_id: string
  product_id: string
  title: string
  sku: string | null
  barcode: string | null
  price_amount: number
  compare_at_price_amount: number | null
  cost_amount: number | null
  currency: string
  weight: string | null
  length: string | null
  width: string | null
  height: string | null
  status: ProductStatus
  option_values?: ProductOptionValue[]
  created_at: string
  updated_at: string
}

export interface Product {
  id: string
  store_id: string
  title: string
  slug: string
  description: string | null
  vendor: string | null
  product_type: string | null
  status: ProductStatus
  seo_title: string | null
  seo_description: string | null
  variants?: ProductVariant[]
  options?: ProductOption[]
  media?: Media[]
  collection_ids?: string[]
  created_at: string
  updated_at: string
}

export interface Media {
  id: string
  store_id: string
  entity_type: MediaEntityType
  entity_id: string
  type: MediaType
  url: string
  alt: string | null
  position: number
  created_at: string
  updated_at: string
}

export interface Collection {
  id: string
  store_id: string
  title: string
  slug: string
  description: string | null
  status: CollectionStatus
  products?: Product[]
  created_at: string
  updated_at: string
}

export interface Category {
  id: string
  store_id: string
  parent_id: string | null
  title: string
  slug: string
  position: number
  children?: Category[]
  created_at: string
  updated_at: string
}

export interface Location {
  id: string
  store_id: string
  name: string
  status: LocationStatus
  country: string | null
  region: string | null
  city: string | null
  address: string | null
  created_at: string
  updated_at: string
}

export interface InventoryLevel {
  id: string
  store_id: string
  inventory_item_id: string
  location_id: string
  on_hand: number
  reserved: number
  available: number
  created_at: string
  updated_at: string
}

export interface InventoryItem {
  id: string
  store_id: string
  product_variant_id: string
  tracked: boolean
  requires_shipping: boolean
  levels?: InventoryLevel[]
  created_at: string
  updated_at: string
}

export type InventoryReservationStatus = 'active' | 'consumed' | 'released' | 'expired'

export interface InventoryReservation {
  id: string
  inventory_item_id: string
  location_id: string
  quantity: number
  status: InventoryReservationStatus
  expires_at: string | null
  released_at: string | null
  consumed_at: string | null
}

export interface InventoryMovement {
  id: string
  store_id: string
  inventory_item_id: string
  location_id: string
  quantity_delta: number
  reason: InventoryMovementReason
  reference_type: string | null
  reference_id: string | null
  created_by: string | null
  created_at: string
}

export interface ShippingZoneRegion {
  id: string
  country_code: string
  region: string | null
  postal_code_pattern: string | null
}

export interface ShippingZone {
  id: string
  name: string
  status: ShippingZoneStatus
  regions?: ShippingZoneRegion[]
  created_at: string
  updated_at: string
}

export interface ShippingMethod {
  id: string
  name: string
  code: string
  provider: string
  service_code: string | null
  status: ShippingMethodStatus
  price_amount: number
  currency: string
  estimated_days_min: number | null
  estimated_days_max: number | null
  settings: Record<string, unknown> | null
  zone_ids?: string[]
  created_at: string
  updated_at: string
}

/**
 * Shared shape for a selected/snapshotted shipping choice — what a
 * checkout's selected_shipping_rate and an order's shipping_line both
 * render as (a ShippingQuote and an OrderShippingLine on the backend
 * respectively; see StorefrontShippingLineResource/ShippingLineResource).
 */
export interface ShippingLine {
  provider: string
  service_code: string | null
  name: string
  price_amount: number
  currency: string
  estimated_days_min: number | null
  estimated_days_max: number | null
  pickup_point: StorefrontPickupPoint | null
}

export interface ShipmentItem {
  id: string
  order_item_id: string
  quantity: number
}

export interface TrackingEvent {
  id: string
  status: TrackingEventStatus
  description: string | null
  occurred_at: string
  location: string | null
}

export interface ShipmentDestination {
  city: string | null
  country_code: string | null
  postal_code: string | null
}

export interface Shipment {
  id: string
  order_id: string
  fulfillment_id: string
  provider: string
  status: ShipmentStatus
  tracking_number: string | null
  tracking_url: string | null
  shipped_at: string | null
  delivered_at: string | null
  cancelled_at: string | null
  items?: ShipmentItem[]
  tracking_events?: TrackingEvent[]
  /** Present once the order relation is loaded server-side (ShipmentController::show) — courier/express destination. */
  destination?: ShipmentDestination | null
  /** Present once the order relation is loaded server-side — populated instead of `destination` for the Pickup service. */
  pickup_point?: StorefrontPickupPoint | null
  created_at: string
  updated_at: string
}

/**
 * The Fulfillment entity's own detailed warehouse-workflow state — not to
 * be confused with `FulfillmentStatus` below (Order.fulfillment_status),
 * which is the Order's 3-state rollup across all of its Fulfillments.
 */
export type FulfillmentWorkflowStatus = 'pending' | 'allocated' | 'picking' | 'packing' | 'ready' | 'completed' | 'cancelled'

export interface FulfillmentAllocation {
  id: string
  location_id: string
  inventory_item_id: string
  quantity: number
  consumed_at: string | null
  cancelled_at: string | null
  created_at: string
}

export interface FulfillmentItem {
  id: string
  order_item_id: string
  quantity: number
  picked_quantity: number
  packed_quantity: number
  allocations?: FulfillmentAllocation[]
}

export interface FulfillmentEvent {
  id: string
  type: string
  description: string | null
  occurred_at: string
}

export interface Fulfillment {
  id: string
  order_id: string
  status: FulfillmentWorkflowStatus
  notes: string | null
  created_by: string | null
  completed_at: string | null
  items?: FulfillmentItem[]
  events?: FulfillmentEvent[]
  shipments?: Shipment[]
  created_at: string
  updated_at: string
}

export type ReturnStatus = 'requested' | 'approved' | 'awaiting_return' | 'received' | 'inspection' | 'completed' | 'rejected' | 'cancelled'
export type ReturnReason = 'wrong_size' | 'damaged' | 'not_as_described' | 'ordered_by_mistake' | 'defective' | 'other'
/** Shared by ReturnItem.condition (unverified, claimed at request time) and ReturnInspection.condition (verified after physical examination). */
export type ReturnCondition = 'new' | 'like_new' | 'damaged' | 'defective' | 'missing_parts' | 'other'
export type ReturnDispositionValue = 'restock' | 'damaged' | 'repair' | 'discard' | 'manual_review'

export interface ReturnInspection {
  id: string
  condition: ReturnCondition
  photos: unknown[] | null
  notes: string | null
  inspected_by: string | null
  inspected_at: string
}

export interface ReturnDisposition {
  id: string
  disposition: ReturnDispositionValue
  notes: string | null
  decided_by: string | null
  decided_at: string
  /** Set once CompleteReturn has actually applied the inventory effect — null while still awaiting completion. */
  applied_at: string | null
}

export interface ReturnItem {
  id: string
  order_item_id: string
  quantity: number
  reason: ReturnReason
  condition: ReturnCondition | null
  notes: string | null
  inspection?: ReturnInspection | null
  disposition?: ReturnDisposition | null
}

export interface ReturnEvent {
  id: string
  type: string
  description: string | null
  occurred_at: string
}

export interface ReturnRequest {
  id: string
  order_id: string
  customer_id: string | null
  number: number
  status: ReturnStatus
  requested_at: string
  approved_at: string | null
  received_at: string | null
  closed_at: string | null
  notes: string | null
  items?: ReturnItem[]
  events?: ReturnEvent[]
  created_at: string
  updated_at: string
}

export interface Money {
  amount: number
  currency: string
}

export interface StorefrontStore {
  name: string
  default_currency: string
  default_locale: string
  timezone: string
}

export interface StorefrontAvailability {
  tracked: boolean
  available: number | null
  in_stock: boolean
}

export interface StorefrontMedia {
  url: string
  alt: string | null
  position: number
}

export interface StorefrontVariantOption {
  option: string
  value: string
}

export interface StorefrontProductVariant {
  id: string
  title: string
  sku: string | null
  price: Money
  compare_at_price: Money | null
  options: StorefrontVariantOption[]
  availability: StorefrontAvailability
  media: StorefrontMedia[]
}

export interface StorefrontProduct {
  id: string
  title: string
  slug: string
  description: string | null
  vendor: string | null
  product_type: string | null
  seo: {
    title: string | null
    description: string | null
  }
  price: Money | null
  variants: StorefrontProductVariant[]
  media: StorefrontMedia[]
}

export interface StorefrontCollection {
  id: string
  title: string
  slug: string
  description: string | null
}

export interface StorefrontCategory {
  title: string
  slug: string
  children: StorefrontCategory[]
}

export interface CartItem {
  id: string
  variant_id: string
  title: string
  sku: string | null
  quantity: number
  price: Money
  line_total: Money
  media: StorefrontMedia[]
}

export interface Cart {
  id: string
  items: CartItem[]
  items_subtotal: number
  total: number
  currency: string
}

export type CheckoutStatus = 'open' | 'completed' | 'expired' | 'cancelled'
export type OrderStatus = 'open' | 'cancelled' | 'closed'
export type FinancialStatus = 'pending' | 'authorized' | 'paid' | 'partially_refunded' | 'refunded' | 'voided'
/** Order's own rollup across all its Fulfillments — see FulfillmentWorkflowStatus for the Fulfillment entity's own status. */
export type FulfillmentStatus = 'unfulfilled' | 'partial' | 'fulfilled'

export interface StorefrontAddress {
  first_name: string | null
  last_name: string | null
  phone: string | null
  country_code: string | null
  region: string | null
  city: string | null
  postal_code: string | null
  address_line1: string | null
  address_line2: string | null
}

export interface StorefrontCheckout {
  id: string
  email: string | null
  phone: string | null
  currency: string
  items_subtotal_amount: number
  shipping_amount: number
  discount_amount: number
  tax_amount: number
  total_amount: number
  status: CheckoutStatus
  discount_code: string | null
  shipping_address: StorefrontAddress | null
  billing_address: StorefrontAddress | null
  selected_shipping_rate: ShippingLine | null
  expires_at: string | null
}

/**
 * Provider-neutral pickup point (spec section 5) — see PickupPoint on the
 * backend.
 */
export interface StorefrontPickupPoint {
  id: string
  name: string
  address: string
  city: string
  country_code: string
  postal_code: string | null
  opening_hours: string | null
  latitude: number | null
  longitude: number | null
}

/**
 * A calculated, not-yet-selected shipping option
 * (GET /storefront/checkout/shipping-rates) — see ShippingRate on the
 * backend. Deliberately no raw metadata field: provider-internal detail
 * never crosses this boundary — `pickup_points` is the one curated
 * exception (spec section 5/17), populated only for the pickup service.
 */
export interface StorefrontShippingRate {
  provider: string
  service_code: string | null
  shipping_method_id: string | null
  name: string
  price_amount: number
  currency: string
  estimated_days_min: number | null
  estimated_days_max: number | null
  pickup_points: StorefrontPickupPoint[] | null
}

export interface StorefrontOrderItem {
  product_title: string
  variant_title: string | null
  sku: string | null
  unit_price_amount: number
  quantity: number
  line_total_amount: number
  currency: string
}

/**
 * The storefront-safe order representation returned by both checkout
 * completion and GET /storefront/orders/{order} — no store_id,
 * customer_id, or checkout_id. `id` doubles as the confirmation token
 * (see OrderConfirmationResource on the backend): it's a ULID, not the
 * sequential `number`, specifically so it can't be guessed.
 */
export interface StorefrontOrderConfirmation {
  id: string
  number: number
  email: string | null
  phone: string | null
  currency: string
  items_subtotal_amount: number
  shipping_amount: number
  discount_amount: number
  tax_amount: number
  total_amount: number
  order_status: OrderStatus
  financial_status: FinancialStatus
  fulfillment_status: FulfillmentStatus
  items: StorefrontOrderItem[]
  discount_applications?: DiscountApplication[]
  shipping_address: StorefrontAddress | null
  billing_address: StorefrontAddress | null
  shipping_line: ShippingLine | null
  created_at: string
}

export interface OrderCustomer {
  id: string
  email: string | null
  phone: string | null
  first_name: string | null
  last_name: string | null
}

export interface OrderItem {
  id: string
  product_id: string | null
  product_variant_id: string | null
  product_title: string
  variant_title: string | null
  sku: string | null
  unit_price_amount: number
  quantity: number
  line_total_amount: number
  currency: string
}

/**
 * Admin-facing order representation (GET /orders, GET /orders/{order}).
 * Read-only this milestone — no payment/refund/fulfillment action fields,
 * since no PaymentGateway or shipping provider exists yet.
 */
export interface Order {
  id: string
  store_id: string
  number: number
  customer_id: string | null
  checkout_id: string | null
  email: string | null
  phone: string | null
  currency: string
  items_subtotal_amount: number
  shipping_amount: number
  discount_amount: number
  tax_amount: number
  total_amount: number
  order_status: OrderStatus
  financial_status: FinancialStatus
  fulfillment_status: FulfillmentStatus
  customer?: OrderCustomer
  items?: OrderItem[]
  discount_applications?: DiscountApplication[]
  shipping_address?: StorefrontAddress | null
  billing_address?: StorefrontAddress | null
  shipping_line?: ShippingLine | null
  reservations?: InventoryReservation[]
  fulfillments?: Fulfillment[]
  shipments?: Shipment[]
  returns?: ReturnRequest[]
  payments?: Payment[]
  refunds?: Refund[]
  ledger_transactions?: LedgerTransaction[]
  financial_events?: FinancialEvent[]
  cancelled_at: string | null
  created_at: string
  updated_at: string
}

export type PaymentStatus =
  | 'pending'
  | 'processing'
  | 'authorized'
  | 'paid'
  | 'failed'
  | 'cancelled'
  | 'expired'
  | 'partially_refunded'
  | 'refunded'

/**
 * The storefront-visible payment representation — what
 * POST /storefront/orders/{order}/payments returns. No store_id/order
 * internals beyond what the visitor needs to complete or check their own
 * payment.
 */
export interface StorefrontPayment {
  id: string
  provider: string
  status: PaymentStatus
  amount: number
  currency: string
  redirect_url: string | null
}

/**
 * Dev/test-only fake payment page display data
 * (GET /fake-payments/{externalPaymentId}).
 */
export interface FakePaymentInfo {
  payment_id: string
  order_number: number | null
  amount: number
  currency: string
  status: PaymentStatus
}

export type PaymentTransactionType = 'authorization' | 'capture' | 'payment' | 'cancel' | 'refund' | 'webhook'
export type PaymentTransactionStatus = 'pending' | 'succeeded' | 'failed'
export type PaymentAttemptStatus = 'pending' | 'succeeded' | 'failed'

export interface PaymentTransaction {
  id: string
  type: PaymentTransactionType
  status: PaymentTransactionStatus
  amount: number
  currency: string
  external_transaction_id: string | null
  created_at: string
}

export interface PaymentAttempt {
  id: string
  status: PaymentAttemptStatus
  external_attempt_id: string | null
  error_code: string | null
  error_message: string | null
  created_at: string
}

/**
 * Admin-facing payment representation (GET /payments, GET
 * /payments/{payment}). Read-only this milestone — no cancel/refund
 * action fields.
 */
export interface Payment {
  id: string
  store_id: string
  order_id: string
  order_number?: number
  provider: string
  status: PaymentStatus
  currency: string
  amount: number
  authorized_amount: number
  captured_amount: number
  refunded_amount: number
  external_payment_id: string | null
  attempts?: PaymentAttempt[]
  transactions?: PaymentTransaction[]
  created_at: string
  updated_at: string
}

export type RefundStatus = 'requested' | 'processing' | 'completed' | 'failed' | 'cancelled'

export interface RefundItem {
  id: string
  return_item_id: string
  quantity: number
  amount: number
}

/**
 * Admin-facing refund representation (GET /refunds, GET /refunds/{refund}).
 * `provider` null means a manual refund (no provider call) — see
 * docs/architecture/financial.md.
 */
export interface Refund {
  id: string
  order_id: string
  payment_id: string
  number: number
  status: RefundStatus
  currency: string
  amount: number
  shipping_amount: number
  adjustment_amount: number
  reason: string | null
  provider: string | null
  provider_reference: string | null
  items?: RefundItem[]
  requested_at: string
  processed_at: string | null
  created_at: string
  updated_at: string
}

export type LedgerAccount = 'cash' | 'revenue'
export type LedgerDirection = 'debit' | 'credit'

export interface LedgerEntry {
  id: string
  account: LedgerAccount
  direction: LedgerDirection
  currency: string
  amount: number
  created_at: string
}

/** `reference_type` is the referenced model's short class name ("Payment" or "Refund"), not the FQCN. */
export interface LedgerTransaction {
  id: string
  reference_type: string
  reference_id: string
  description: string | null
  entries?: LedgerEntry[]
  occurred_at: string
}

/** Unified per-order financial timeline (spec: "Payment captured" / "Refund requested" / .../ "Ledger created"). */
export interface FinancialEvent {
  id: string
  type: string
  description: string | null
  occurred_at: string
}

export type PromotionTriggerType = 'automatic' | 'code'
export type PromotionStackingMode = 'stackable' | 'exclusive'
export type PromotionStatus = 'active' | 'inactive'
export type DiscountCodeStatus = 'active' | 'inactive'

export type PromotionRuleType =
  | 'min_subtotal'
  | 'product'
  | 'collection'
  | 'category'
  | 'customer'
  | 'country'
  | 'currency'
  | 'order_quantity'
  | 'order_total'
  | 'date_range'
  | 'usage_limit'

export type PromotionActionType =
  | 'percentage_off'
  | 'fixed_amount_off'
  | 'free_shipping'
  | 'free_product'
  | 'line_item_discount'
  | 'order_discount'

export type DiscountApplicationTarget = 'order' | 'shipping' | 'line_item'

export interface PromotionRule {
  id: string
  type: PromotionRuleType
  parameters: Record<string, unknown>
}

export interface PromotionAction {
  id: string
  type: PromotionActionType
  parameters: Record<string, unknown>
}

export interface DiscountCode {
  id: string
  promotion_id: string
  code: string
  usage_limit: number | null
  per_customer_limit: number | null
  usage_count: number
  expires_at: string | null
  status: DiscountCodeStatus
  created_at: string
}

/**
 * Admin-facing promotion representation (GET/POST/PATCH /promotions). See
 * docs/architecture/promotions.md — trigger_type separates "how it's
 * activated" from "what it does" (rules/actions carry the latter).
 */
export interface Promotion {
  id: string
  name: string
  description: string | null
  trigger_type: PromotionTriggerType
  stacking_mode: PromotionStackingMode
  priority: number
  status: PromotionStatus
  starts_at: string | null
  ends_at: string | null
  rules?: PromotionRule[]
  actions?: PromotionAction[]
  discount_codes?: DiscountCode[]
  created_at: string
  updated_at: string
}

export interface PromotionUsage {
  id: string
  promotion_id: string
  discount_code_id: string | null
  customer_id: string | null
  order_id: string
  amount: number
  created_at: string
}

/** One PromotionAction's computed effect — returned by POST /promotions/preview. */
export interface AppliedDiscount {
  promotion_id: string
  promotion_name: string
  discount_code: string | null
  action_type: PromotionActionType
  target: DiscountApplicationTarget
  amount: number
  product_variant_id: string | null
}

/** The Order's immutable discount snapshot (spec section 8) — see DiscountApplicationResource on the backend. */
export interface DiscountApplication {
  id: string
  promotion_name: string
  code: string | null
  action_type: PromotionActionType
  target: DiscountApplicationTarget
  order_item_id: string | null
  amount: number
  currency: string
}

export type AppType = 'private' | 'public'
export type AppRegistrationStatus = 'active' | 'inactive'
export type InstalledAppStatus = 'active' | 'uninstalled'
export type AppTokenType = 'access' | 'refresh'
export type ExtensionPoint = 'checkout' | 'order' | 'product' | 'customer' | 'admin_navigation' | 'admin_widget' | 'dashboard_card'

/**
 * Admin-facing App representation (GET/POST /apps). `client_id` is
 * present once the app's OAuthClient is loaded; `client_secret` is only
 * ever present in the POST /apps response itself (see AppController) —
 * shown once, never re-derivable afterward.
 */
export interface App {
  id: string
  store_id: string | null
  type: AppType
  name: string
  slug: string
  developer: string | null
  description: string | null
  redirect_urls: string[]
  requested_scopes: string[]
  status: AppRegistrationStatus
  client_id?: string
  client_secret?: string
  created_at: string
}

export interface InstalledAppSummary {
  id: string
  name: string
  slug: string
  developer: string | null
  type: AppType
}

export interface InstalledApp {
  id: string
  app_id: string
  app?: InstalledAppSummary
  status: InstalledAppStatus
  scopes?: string[]
  installed_at: string
  uninstalled_at: string | null
}

/** Admin visibility only — never includes the token value itself. */
export interface AppToken {
  id: string
  type: AppTokenType
  scope: string[]
  rotated_from_id: string | null
  expires_at: string
  revoked_at: string | null
  created_at: string
}

export interface AppExtension {
  id: string
  installed_app_id: string
  extension_point: ExtensionPoint
  config: Record<string, unknown>
  status: AppRegistrationStatus
  created_at: string
}

export interface ApiResource<T> {
  data: T
}

export interface ApiCollection<T> {
  data: T[]
  links?: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }
  meta?: {
    current_page: number
    from: number | null
    last_page: number
    per_page: number
    to: number | null
    total: number
  }
}

export interface AuthResponse {
  data: User
  token: string
}

export interface ApiErrorBody {
  message: string
  error?: string
  errors?: Record<string, string[]>
}
