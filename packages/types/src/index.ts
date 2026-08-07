export type StoreStatus = 'active' | 'suspended'
export type StoreUserRole = 'owner' | 'administrator' | 'manager'
export type StoreUserStatus = 'active' | 'invited' | 'suspended'
export type ProductStatus = 'draft' | 'active' | 'archived'

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

export interface ProductVariant {
  id: string
  store_id: string
  product_id: string
  title: string
  sku: string | null
  price_amount: number
  currency: string
  status: ProductStatus
  created_at: string
  updated_at: string
}

export interface Product {
  id: string
  store_id: string
  title: string
  slug: string
  description: string | null
  status: ProductStatus
  variants?: ProductVariant[]
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
