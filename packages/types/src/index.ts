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
