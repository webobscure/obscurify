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
  shipping_address: StorefrontAddress | null
  billing_address: StorefrontAddress | null
  expires_at: string | null
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
  shipping_address: StorefrontAddress | null
  billing_address: StorefrontAddress | null
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
  shipping_address?: StorefrontAddress | null
  billing_address?: StorefrontAddress | null
  cancelled_at: string | null
  created_at: string
  updated_at: string
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
