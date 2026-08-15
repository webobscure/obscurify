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

export type ThemeStatus = 'draft' | 'published' | 'archived'
export type ThemeVersionStatus = 'draft' | 'published' | 'archived'
export type ThemeTemplateType
  = 'home' | 'collection' | 'product' | 'cart' | 'checkout' | 'search' | 'blog' | '404' | 'page'

/** Every ThemeTemplateType case, in the order the backend enum declares them. */
export const themeTemplateTypes: readonly ThemeTemplateType[] = [
  'home', 'collection', 'product', 'cart', 'checkout', 'search', 'blog', '404', 'page',
]

export interface ThemeVersion {
  id: string
  theme_id: string
  created_from_version_id: string | null
  version_number: number
  status: ThemeVersionStatus
  published_at: string | null
  created_at: string
}

/**
 * `is_active` is derived server-side from the store's single ActiveTheme
 * pointer, not a column — a theme can be `published` (has at least one
 * frozen version) without being the one currently serving the storefront.
 */
export interface Theme {
  id: string
  name: string
  slug: string
  status: ThemeStatus
  is_active: boolean
  versions: ThemeVersion[]
  created_at: string
  updated_at: string
}

export interface ThemeBlockInstance {
  id: string
  block_handle: string
  settings: Record<string, unknown>
}

export interface ThemeSectionInstance {
  id: string
  section_handle: string
  settings: Record<string, unknown>
}

/**
 * A template's stored (unresolved) section list — raw instance overrides,
 * with no schema defaults merged in. Contrast RenderedSection below.
 */
export interface ThemeTemplate {
  id: string
  type: ThemeTemplateType
  name: string
  sections: (ThemeSectionInstance & { blocks?: ThemeBlockInstance[] })[]
}

export interface RenderedBlock {
  id: string | null
  handle: string
  settings: Record<string, unknown>
}

export interface RenderedSection {
  id: string | null
  handle: string
  name: string
  settings: Record<string, unknown>
  blocks: RenderedBlock[]
}

/**
 * The preview endpoint's payload. camelCase — unlike every other type
 * here — because ThemeRenderer returns plain readonly PHP DTOs that are
 * json_encoded directly, not passed through a snake_case JsonResource.
 */
export interface RenderedPage {
  template: ThemeTemplateType
  sections: RenderedSection[]
  globalSettings: Record<string, unknown>
  themeId: string
  themeVersionId: string
  isPreview: boolean
}

export type PageStatus = 'draft' | 'published' | 'archived'
export type PageVersionStatus = 'draft' | 'published' | 'archived'

export interface PageVersion {
  id: string
  page_id: string
  created_from_version_id: string | null
  version_number: number
  status: PageVersionStatus
  published_at: string | null
  /** Raw section instances — same unresolved shape as ThemeTemplate.sections. */
  sections: (ThemeSectionInstance & { blocks?: ThemeBlockInstance[] })[]
  created_at: string
}

/**
 * `is_active` means "an ActivePageVersion pointer exists for this page",
 * i.e. it has been published at least once and is being served. It is
 * deliberately independent of `status`: PublishPageVersion never writes
 * the page's own `status` column, so a page can read `draft` while still
 * being `is_active` — the same split Theme has between `status` and the
 * store's ActiveTheme pointer.
 */
export interface Page {
  id: string
  title: string
  slug: string
  status: PageStatus
  is_active: boolean
  versions: PageVersion[]
  created_at: string
  updated_at: string
}

/**
 * A reusable preset of section instances. Not versioned and not scoped to
 * a theme version — `page_template_id` on page creation copies `sections`
 * in once, it is never stored as a live reference.
 */
export interface PageTemplate {
  id: string
  name: string
  sections: (ThemeSectionInstance & { blocks?: ThemeBlockInstance[] })[]
  created_at: string
}

/** Shared by page versions and blog posts — the same endpoint shape for both. */
export interface SeoMetadata {
  meta_title: string | null
  meta_description: string | null
  canonical_url: string | null
  og_image: string | null
}

export type MenuItemTargetType = 'url' | 'page' | 'collection' | 'product' | 'blog' | 'blog_post'

/** Every MenuItemTargetType case, in the order the backend enum declares them. */
export const menuItemTargetTypes: readonly MenuItemTargetType[] = [
  'url', 'page', 'collection', 'product', 'blog', 'blog_post',
]

/**
 * `url` carries the destination only for `target_type: 'url'`; every
 * other target type resolves `target_id` against its own table instead.
 */
export interface MenuItem {
  id: string
  menu_id: string
  parent_id: string | null
  label: string
  target_type: MenuItemTargetType
  target_id: string | null
  url: string | null
  position: number
  /** Absent (not empty) on the create/update responses, which return a bare item. */
  children?: MenuItem[]
}

/**
 * `items` is the fully nested top-level tree, present only on the
 * show/create/update responses — MenuController::index deliberately does
 * not build a tree per menu, so a listed menu carries no `items` at all.
 */
export interface Menu {
  id: string
  name: string
  handle: string
  items?: MenuItem[]
  created_at: string
  updated_at: string
}

export interface Author {
  id: string
  name: string
  bio: string | null
  avatar_path: string | null
  created_at: string
}

export interface Blog {
  id: string
  title: string
  slug: string
  posts_count: number
  created_at: string
}

export type BlogPostStatus = 'draft' | 'published' | 'scheduled' | 'archived'

/**
 * `status` is server-derived on create — a post created with a future
 * `scheduled_at` comes back `scheduled`, otherwise `draft`, so the create
 * request never sends one.
 */
export interface BlogPost {
  id: string
  blog_id: string
  author: Author | null
  title: string
  slug: string
  excerpt: string | null
  body: string
  status: BlogPostStatus
  published_at: string | null
  scheduled_at: string | null
  featured_image_path: string | null
  created_at: string
  updated_at: string
}

export interface Redirect {
  id: string
  from_path: string
  to_path: string
  status_code: number
  created_at: string
}

/**
 * Visual Builder (Milestone 15). `BlockInstance`/`SectionInstance` are the
 * *editable* shape the builder mutates client-side and PATCHes back
 * wholesale — richer than Milestone 13's `ThemeBlockInstance`, which has no
 * `blocks` at all, because Builder blocks nest recursively (see
 * `App\Domain\Builder\Support\SerializeSectionInstances`). Both always carry
 * `blocks`, never optional: the backend serializer emits an empty array
 * rather than omitting the key.
 */
export interface BlockInstance {
  id: string
  block_handle: string
  settings: Record<string, unknown>
  blocks: BlockInstance[]
}

export interface SectionInstance {
  id: string
  section_handle: string
  settings: Record<string, unknown>
  blocks: BlockInstance[]
}

/**
 * The canonical editor state, returned identically by builder page
 * show/update/undo/redo. Treat every response as authoritative and replace
 * local state with it — the backend re-derives `sections` from the
 * relational round-trip and silently drops handles the active theme does
 * not define, so what was sent is not always what is stored.
 */
export interface BuilderPageState {
  id: string
  title: string
  slug: string
  status: PageStatus
  draft_version_id: string
  sections: SectionInstance[]
  can_undo: boolean
  can_redo: boolean
}

/**
 * Restoring a revision returns a *narrower* payload than show/update do —
 * BuilderRevisionController::restore emits only these two keys, with no
 * `can_undo`/`can_redo`, so the caller has to re-read the page state to
 * refresh the undo/redo affordances.
 */
export interface BuilderRevisionRestoreResult {
  draft_version_id: string
  sections: SectionInstance[]
}

export interface BuilderRevisionSummary {
  id: string
  sequence: number
  is_current: boolean
  created_at: string
}

export type BuilderPresetType = 'section' | 'block'

/** `settings` is the default settings object a new instance is seeded with. */
export interface BuilderPreset {
  id: string
  type: BuilderPresetType
  handle: string
  name: string
  settings: Record<string, unknown>
}

export type ThemeCustomizerFieldType
  = 'image' | 'font' | 'color' | 'select' | 'range' | 'boolean' | 'richtext' | 'text' | 'url'

/**
 * Field metadata only — there is no options list for `select`, and no
 * min/max for `range`; the admin picks sensible ones per key.
 */
export interface ThemeCustomizerField {
  key: string
  label: string
  type: ThemeCustomizerFieldType
  group: string
}

/**
 * `theme_version_id` is the active theme's current *draft* version — both
 * the settings PATCH and the asset library are scoped to it.
 */
export interface ThemeCustomizerState {
  theme_version_id: string
  schema: ThemeCustomizerField[]
  values: Record<string, unknown>
}

export type ThemeAssetType
  = 'html_template' | 'vue_component' | 'css' | 'javascript' | 'image' | 'font' | 'svg' | 'json_config'

export interface ThemeAsset {
  id: string
  theme_version_id: string
  type: ThemeAssetType
  url: string
  mime_type: string | null
  size: number | null
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

// ---------------------------------------------------------------------------
// Customer Accounts + Customer Portal (Milestone 16)
// ---------------------------------------------------------------------------

export type CustomerStatus = 'active' | 'disabled'

/**
 * The customer-portal/admin customer representation. Distinct from the
 * admin Order's embedded `OrderCustomer` (a minimal read-only snapshot) —
 * this is the full profile, used by both GET /storefront/account (the
 * customer viewing themselves) and GET /customers/{customer} (admin
 * management). Never carries a password/credential — that lives on the
 * backend-only CustomerIdentity, never serialized.
 */
export interface Customer {
  id: string
  email: string | null
  first_name: string | null
  last_name: string | null
  phone: string | null
  date_of_birth: string | null
  status: CustomerStatus
  verified_at: string | null
  addresses?: CustomerAddress[]
  created_at: string
}

export interface CustomerAddress {
  id: string
  first_name: string | null
  last_name: string | null
  phone: string | null
  country_code: string | null
  region: string | null
  city: string | null
  postal_code: string | null
  address_line1: string | null
  address_line2: string | null
  is_default_billing: boolean
  is_default_shipping: boolean
  created_at: string
  updated_at: string
}

/** One row per logged-in device/browser — see GET/DELETE /account/sessions. */
export interface CustomerSession {
  id: string
  ip_address: string | null
  user_agent: string | null
  is_current: boolean
  last_used_at: string
  expires_at: string
  created_at: string
}

/**
 * What POST /account/register and POST /account/login return alongside
 * the Customer — a CustomerAccessToken bearer pair, entirely separate
 * from the merchant admin's Sanctum `AuthResponse.token`.
 */
export interface CustomerAuthResponse {
  data: Customer
  access_token: string
  refresh_token: string
}

export interface CustomerTokenPair {
  access_token: string
  refresh_token: string
}

/** Same field set as the admin's OrderItem, plus `id`/`product_variant_id` — the portal needs both to reference a line for reorder/return. */
export interface CustomerOrderItem {
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
 * The order-history list row (GET /account/orders) — deliberately
 * lighter than CustomerOrder (no items/payments/shipments/returns/
 * refunds), matching the backend's CustomerOrderSummaryResource.
 */
export interface CustomerOrderSummary {
  id: string
  number: number
  currency: string
  total_amount: number
  order_status: OrderStatus
  financial_status: FinancialStatus
  fulfillment_status: FulfillmentStatus
  created_at: string
}

/**
 * The full order detail (GET /account/orders/{order}) — spec section 7:
 * order status, payment status, shipment tracking, return status, and
 * refund status all in one place. `payments` reuses the storefront's own
 * StorefrontPayment shape (no authorized/captured internals), while
 * shipments/returns/refunds reuse the admin-facing Shipment/ReturnRequest/
 * Refund shapes directly — see CustomerOrderResource's docblock on the
 * backend for why that's a deliberate simplification, not a leak.
 */
export interface CustomerOrder {
  id: string
  number: number
  currency: string
  items_subtotal_amount: number
  shipping_amount: number
  discount_amount: number
  tax_amount: number
  total_amount: number
  order_status: OrderStatus
  financial_status: FinancialStatus
  fulfillment_status: FulfillmentStatus
  items: CustomerOrderItem[]
  shipping_address: StorefrontAddress | null
  billing_address: StorefrontAddress | null
  shipping_line: ShippingLine | null
  payments: StorefrontPayment[]
  shipments: Shipment[]
  returns: ReturnRequest[]
  refunds: Refund[]
  created_at: string
}

/** POST /account/orders/{order}/reorder ("buy again") response. */
export interface ReorderResult {
  cart_token: string
  skipped: Array<{
    order_item_id: string
    product_title: string
    reason: 'no_longer_available' | 'unavailable'
  }>
}

/** GET /customers/{customer}/activity — the admin's activity timeline (Milestone 16 spec section 12), backed by the existing OutboxEvent log. */
export interface CustomerActivityEvent {
  id: string
  event_type: string
  payload: Record<string, unknown>
  occurred_at: string | null
}

// ---------------------------------------------------------------------------
// Customer Intelligence (Milestone 18) — Groups, Segments, Tags, Metrics.
// See docs/architecture/customer-intelligence.md.
// ---------------------------------------------------------------------------

export type CustomerGroupType = 'manual' | 'dynamic' | 'protected'
export type CustomerGroupStatus = 'active' | 'archived'
export type CustomerSegmentStatus = 'active' | 'archived'
export type SegmentRuleBoolean = 'and' | 'or'

/** Mirrors the backend's SegmentRuleField enum exactly — see that enum's docblock for each field's definition/source. */
export type SegmentRuleField =
  | 'total_spent'
  | 'average_order_value'
  | 'order_count'
  | 'refund_count'
  | 'return_count'
  | 'return_rate'
  | 'lifetime_value'
  | 'days_since_last_order'
  | 'days_since_registration'
  | 'country_code'
  | 'email_verified'
  | 'date_of_birth'
  | 'has_tag'
  | 'in_group'

export type SegmentRuleOperator =
  | 'equals'
  | 'not_equals'
  | 'greater_than'
  | 'greater_than_or_equal'
  | 'less_than'
  | 'less_than_or_equal'
  | 'contains'
  | 'starts_with'
  | 'ends_with'
  | 'is_true'
  | 'is_false'
  | 'in_set'
  | 'not_in_set'
  | 'this_month'

/**
 * One node in a rule tree — either a *group* node (`boolean_operator` +
 * `children`) or a *condition* node (`field` + `operator`, `value`
 * optional). Recursive: a group node's own children can themselves be
 * group nodes, to any depth. See SegmentRuleEngine on the backend.
 */
export interface SegmentRule {
  id: string
  boolean_operator: SegmentRuleBoolean | null
  field: SegmentRuleField | null
  operator: SegmentRuleOperator | null
  value: unknown
  position: number
  children: SegmentRule[]
}

/**
 * The write shape for POST/PATCH `rules` — a plain object tree (no `id`,
 * since the whole tree is replaced atomically server-side on every
 * write; see ReplaceSegmentRules on the backend).
 */
export interface SegmentRuleInput {
  boolean_operator?: SegmentRuleBoolean
  field?: SegmentRuleField
  operator?: SegmentRuleOperator
  value?: unknown
  children?: SegmentRuleInput[]
}

export interface CustomerGroup {
  id: string
  name: string
  description: string | null
  type: CustomerGroupType
  status: CustomerGroupStatus
  member_count?: number
  rules?: SegmentRule[]
  created_at: string
  updated_at: string
}

export interface CustomerSegment {
  id: string
  name: string
  description: string | null
  status: CustomerSegmentStatus
  member_count?: number
  rules?: SegmentRule[]
  created_at: string
  updated_at: string
}

export interface CustomerTag {
  id: string
  name: string
  slug: string
  is_system: boolean
  assignment_count?: number
  created_at: string
}

/** GET /customers/{customer}/metrics — null when RecomputeCustomerMetrics has never run for this customer (no order/registration/update yet). */
export interface CustomerMetric {
  total_spent_amount: number
  average_order_value_amount: number
  order_count: number
  refund_count: number
  return_count: number
  /** Percentage points (already converted from the backend's stored basis points), e.g. 12.34. */
  return_rate: number
  lifetime_value_amount: number
  currency: string | null
  first_order_at: string | null
  last_order_at: string | null
  days_since_last_order: number | null
  computed_at: string
}

/** GET /customers/{customer}/metrics/history — the "Customer Timeline" trend data, one row per background-recomputation snapshot. */
export interface CustomerSnapshot {
  id: string
  metrics: {
    total_spent_amount: number
    average_order_value_amount: number
    order_count: number
    refund_count: number
    return_count: number
    return_rate_bps: number
    lifetime_value_amount: number
    currency: string | null
  }
  captured_at: string
}

// --- Automation Engine (Milestone 19) ---
// See docs/architecture/automation.md and WorkflowRunner on the backend.

export type WorkflowStatus = 'draft' | 'published' | 'disabled' | 'archived'
export type WorkflowVersionStatus = 'draft' | 'published' | 'archived'
export type WorkflowConditionBoolean = 'and' | 'or'

export type WorkflowConditionOperator =
  | 'equals' | 'not_equals'
  | 'greater_than' | 'greater_than_or_equal' | 'less_than' | 'less_than_or_equal'
  | 'contains' | 'starts_with' | 'ends_with'
  | 'is_true' | 'is_false'
  | 'in_set' | 'not_in_set'
  | 'in_segment' | 'not_in_segment'
  | 'in_group' | 'not_in_group'
  | 'has_tag' | 'not_has_tag'

export type WorkflowActionType =
  | 'add_customer_tag' | 'remove_customer_tag'
  | 'add_customer_to_group' | 'remove_customer_from_group'
  | 'create_discount_code' | 'expire_discount'
  | 'publish_event' | 'call_app_webhook'
  | 'send_email_notification' | 'send_sms_notification' | 'send_push_notification'
  | 'send_in_app_notification' | 'send_webhook_notification'
  | 'update_customer_metadata' | 'update_order_metadata'
  | 'create_task' | 'delay' | 'app_action'

export type DelayType = 'minutes' | 'hours' | 'until_date' | 'until_event'

export type WorkflowExecutionStatus = 'pending' | 'running' | 'waiting' | 'completed' | 'failed' | 'dead_letter'
export type WorkflowExecutionStepType = 'condition' | 'action'
export type WorkflowExecutionStepStatus = 'pending' | 'succeeded' | 'failed' | 'skipped' | 'waiting'

/** The write shape for a condition tree node — see ReplaceWorkflowConditions on the backend. */
export interface WorkflowConditionInput {
  boolean_operator?: WorkflowConditionBoolean
  variable_key?: string
  operator?: WorkflowConditionOperator
  value?: unknown
  children?: WorkflowConditionInput[]
}

/** Recursive read shape — a group node (`boolean_operator` + `children`) or a condition node (`variable_key` + `operator`). */
export interface WorkflowCondition {
  id: string
  boolean_operator: WorkflowConditionBoolean | null
  variable_key: string | null
  operator: WorkflowConditionOperator | null
  value: unknown
  position: number
  children: WorkflowCondition[]
}

export interface WorkflowActionInput {
  type: WorkflowActionType
  config?: Record<string, unknown>
}

export interface WorkflowAction {
  id: string
  type: WorkflowActionType
  config: Record<string, unknown>
  position: number
}

export interface WorkflowVersion {
  id: string
  version_number: number
  status: WorkflowVersionStatus
  trigger: { event_type: string } | null
  conditions: WorkflowCondition[]
  actions: WorkflowAction[]
  created_at: string
}

export interface Workflow {
  id: string
  name: string
  description: string | null
  status: WorkflowStatus
  published_version_id: string | null
  /** The version relevant to the current view — the editable draft on show/update, the published version after publish/rollback. */
  version?: WorkflowVersion | null
  created_at: string
  updated_at: string
}

export interface WorkflowExecutionStep {
  id: string
  workflow_action_id: string | null
  step_type: WorkflowExecutionStepType
  status: WorkflowExecutionStepStatus
  input: Record<string, unknown> | null
  output: Record<string, unknown> | null
  error_message: string | null
  attempts: number
  position: number
  started_at: string | null
  completed_at: string | null
}

export interface WorkflowExecution {
  id: string
  workflow_id: string
  workflow_version_id: string
  outbox_event_id: string
  status: WorkflowExecutionStatus
  context: Record<string, unknown>
  depth: number
  root_execution_id: string | null
  caused_by_execution_id: string | null
  attempts: number
  next_retry_at: string | null
  next_resume_at: string | null
  wait_until_event_type: string | null
  started_at: string | null
  completed_at: string | null
  error_message: string | null
  steps?: WorkflowExecutionStep[]
  created_at: string
}

export interface WorkflowTemplateDefinition {
  trigger?: { event_type: string }
  conditions?: WorkflowConditionInput[]
  actions?: WorkflowActionInput[]
}

export interface WorkflowTemplate {
  id: string
  key: string
  name: string
  description: string | null
  category: string | null
  definition: WorkflowTemplateDefinition
}

/** GET /automation/variables — the variable-picker catalog (built-ins merged with app-contributed entries). */
export interface WorkflowVariableCatalogEntry {
  source: string
  key: string
  label: string
  type: string
  event_types: string[] | null
  origin: 'built_in' | 'app'
  installed_app_id?: string
}

/** GET /automation/triggers — the trigger-picker catalog. */
export interface WorkflowTriggerCatalogEntry {
  event_type: string
  label: string
  origin: 'platform' | 'app'
  installed_app_id?: string
}

// --- Analytics Platform (Milestone 20) ---
// See docs/architecture/analytics.md and AnalyticsAggregator on the backend.

export type DashboardWidgetType = 'line_chart' | 'bar_chart' | 'pie_chart' | 'metric_card' | 'table' | 'leaderboard'

export type ReportType =
  | 'orders' | 'products' | 'customers' | 'inventory' | 'shipping'
  | 'payments' | 'returns' | 'promotions' | 'automation_executions'

export type ReportStatus = 'pending' | 'completed' | 'failed'
export type ExportFormat = 'csv' | 'xlsx' | 'pdf'
export type ExportStatus = 'pending' | 'completed' | 'failed'
export type ExportRecurrence = 'daily' | 'weekly' | 'monthly'
export type TimeDimension = 'today' | 'yesterday' | 'last_7_days' | 'last_30_days' | 'month' | 'quarter' | 'year' | 'custom'
export type MetricCategory = 'revenue' | 'orders' | 'customers' | 'inventory' | 'leaderboard'
export type MetricUnit = 'currency' | 'count' | 'percentage' | 'ratio'
export type MetricCalculation = 'sum' | 'count' | 'average' | 'derived' | 'leaderboard' | 'gauge' | 'placeholder'

/** GET /analytics/metrics — the widget/report builder's metric picker. */
export interface MetricDefinition {
  key: string
  label: string
  description: string | null
  category: MetricCategory
  unit: MetricUnit
  calculation: MetricCalculation
}

export interface DashboardWidget {
  id: string
  dashboard_id: string
  type: DashboardWidgetType
  title: string
  /** Always includes `metric_key`; `time_dimension` optionally overrides the dashboard's own default range for this widget. */
  config: { metric_key?: string, time_dimension?: TimeDimension } & Record<string, unknown>
  position: number
}

export interface Dashboard {
  id: string
  name: string
  is_default: boolean
  widgets?: DashboardWidget[]
  created_at: string
  updated_at: string
}

export interface WidgetDataSeriesPoint {
  date: string
  value: number | null
  count: number | null
}

export interface WidgetBreakdownEntry {
  label: string
  value: number
}

/** GET /analytics/widgets/{widget}/data — the computed data behind one widget. */
export interface WidgetData {
  metric_key: string
  from: string
  to: string
  /** For a gauge metric (e.g. inventory_value) this is the latest day's reading, not a sum across the range. */
  total: number | null
  series: WidgetDataSeriesPoint[]
  breakdown: Record<string, WidgetBreakdownEntry> | null
}

export interface Report {
  id: string
  saved_report_id: string | null
  report_type: ReportType
  filters: Record<string, unknown>
  columns: string[]
  status: ReportStatus
  result: Array<Record<string, unknown>> | null
  row_count: number | null
  error_message: string | null
  generated_at: string | null
  created_at: string
}

export interface SavedReport {
  id: string
  name: string
  report_type: ReportType
  filters: Record<string, unknown>
  columns: string[]
  created_at: string
  updated_at: string
}

export interface ReportExport {
  id: string
  report_id: string
  format: ExportFormat
  status: ExportStatus
  file_size: number | null
  scheduled_at: string | null
  recurrence: ExportRecurrence | null
  completed_at: string | null
  download_url: string | null
}

// --- Notification Center + Omnichannel Messaging (Milestone 21) ---
// See docs/architecture/notifications.md and NotificationDispatcher on the backend.

export type NotificationChannelType = 'email' | 'sms' | 'push' | 'in_app' | 'webhook'
export type NotificationStatus = 'pending' | 'delivered' | 'failed' | 'partially_delivered'
// 'sending' is a transient claim marker (SendNotificationDeliveryJob's guarded
// UPDATE) — never a status a caller sets, but it can appear briefly in a read.
export type NotificationDeliveryStatus = 'pending' | 'sending' | 'succeeded' | 'failed' | 'exhausted' | 'suppressed'
export type NotificationRecipientType = 'customer' | 'ad_hoc'
export type NotificationTriggerSource = 'platform_event' | 'automation' | 'admin' | 'apps_sdk' | 'scheduled'

export interface NotificationTemplate {
  id: string
  key: string | null
  name: string
  channel: NotificationChannelType
  locale: string
  subject: string | null
  body_text: string
  body_html: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface NotificationProvider {
  id: string
  code: string
  name: string
  is_enabled: boolean
  config: Record<string, unknown> | null
  created_at: string
  updated_at: string
}

export interface NotificationChannel {
  id: string
  channel: NotificationChannelType
  provider_id: string | null
  provider: NotificationProvider | null
  is_enabled: boolean
}

export interface NotificationEvent {
  id: string
  event_type: string
  channel: NotificationChannelType
  template_id: string
  template: NotificationTemplate | null
  is_enabled: boolean
}

/** GET /notifications (list view) — omits body/recipients/deliveries. */
export interface NotificationSummary {
  id: string
  channel: NotificationChannelType
  event_type: string | null
  subject: string | null
  triggered_by: NotificationTriggerSource
  status: NotificationStatus
  created_at: string
}

export interface NotificationRecipient {
  id: string
  notification_id: string
  recipient_type: NotificationRecipientType
  customer_id: string | null
  address: string | null
  read_at: string | null
  notification: NotificationSummary | null
}

export interface NotificationDelivery {
  id: string
  notification_id: string
  recipient_id: string
  channel: NotificationChannelType
  provider_id: string | null
  status: NotificationDeliveryStatus
  attempt_count: number
  last_attempted_at: string | null
  next_retry_at: string | null
  delivered_at: string | null
  error_message: string | null
  created_at: string
}

/** GET /notifications/{notification} (detail view). */
export interface Notification {
  id: string
  template_id: string | null
  channel: NotificationChannelType
  event_type: string | null
  subject: string | null
  body_text: string
  body_html: string | null
  related_type: string | null
  related_id: string | null
  workflow_execution_id: string | null
  triggered_by: NotificationTriggerSource
  status: NotificationStatus
  recipients: NotificationRecipient[]
  deliveries: NotificationDelivery[]
  created_at: string
}

export interface NotificationPreference {
  id: string
  customer_id: string
  email_enabled: boolean
  sms_enabled: boolean
  push_enabled: boolean
  marketing_opt_in: boolean
  transactional_only: boolean
  quiet_hours_start: string | null
  quiet_hours_end: string | null
  quiet_hours_timezone: string | null
}
