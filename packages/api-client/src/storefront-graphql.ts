import type {
  ApiCollection,
  ApiResource,
  Cart,
  SearchResponse,
  SearchSuggestionsResponse,
  StorefrontCategory,
  StorefrontCollection,
  StorefrontProduct,
  StorefrontStore,
} from '@obscurify/types'
import { ApiClientError } from './index'
import type { StorefrontCollectionShowResponse } from './storefront'

export interface StorefrontGraphQLClientOptions {
  /** Same meaning as StorefrontApiClientOptions.baseUrl — see that class's own docs. */
  baseUrl: string
}

interface GraphQLEnvelope<T> {
  data: T | null
  errors?: Array<{ message: string }>
}

/**
 * Spec section 9: "Nuxt storefront should be able to switch from REST
 * to GraphQL without business logic changes. Keep both clients
 * available." This class is the concrete proof of that claim — it
 * exposes the *exact same* method names and return shapes as
 * StorefrontApiClient's `store`/`products`/`collections`/`categories`/
 * `search`/`cart` namespaces (ApiResource<T>/ApiCollection<T>/Cart/
 * SearchResponse, all from @obscurify/types), so a page written against
 * one can be pointed at the other by changing only which composable it
 * calls — never how it reads the response. Every GraphQL response is
 * reshaped into that identical REST contract here, once, so the
 * transport difference never leaks into a calling component.
 *
 * Deliberately covers the core storefront browsing + cart flow (spec
 * section 3's Store/Products/Product/Collections/Collection/Categories/
 * Category/Search/SearchSuggestions queries, section 4's Cart mutations)
 * rather than every single REST endpoint — see
 * docs/architecture/graphql.md §9 for the scope boundary.
 */
export class StorefrontGraphQLClient {
  constructor(private readonly options: StorefrontGraphQLClientOptions) {}

  private async request<T>(query: string, variables?: Record<string, unknown>): Promise<T> {
    const response = await fetch(`${this.options.baseUrl}/api/graphql`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(variables ? { query, variables } : { query }),
    })

    const body = (await response.json()) as GraphQLEnvelope<T>

    if (!response.ok || body.errors?.length) {
      throw new ApiClientError(response.status, {
        message: body.errors?.[0]?.message ?? 'GraphQL request failed.',
      })
    }

    if (body.data === null) {
      throw new ApiClientError(response.status, { message: 'GraphQL response had no data.' })
    }

    return body.data
  }

  readonly store = {
    get: async (): Promise<ApiResource<StorefrontStore>> => {
      const { store } = await this.request<{ store: { name: string, defaultCurrency: string, defaultLocale: string, timezone: string } }>(
        'query { store { name defaultCurrency defaultLocale timezone } }',
      )

      return { data: { name: store.name, default_currency: store.defaultCurrency, default_locale: store.defaultLocale, timezone: store.timezone } }
    },
  }

  readonly products = {
    list: async (params: { collection?: string, category?: string, sort?: 'newest' | 'price_asc' | 'price_desc', page?: number } = {}): Promise<ApiCollection<StorefrontProduct>> => {
      const { products } = await this.request<{ products: { data: GraphQLProduct[], pageInfo: GraphQLPageInfo } }>(
        `query($collection: String, $category: String, $sort: String, $page: Int) {
           products(collection: $collection, category: $category, sort: $sort, page: $page) {
             data { ${PRODUCT_FIELDS} }
             pageInfo { currentPage lastPage perPage total }
           }
         }`,
        { collection: params.collection, category: params.category, sort: params.sort, page: params.page },
      )

      return {
        data: products.data.map(toStorefrontProduct),
        meta: pageInfoToMeta(products.pageInfo),
      }
    },

    get: async (slug: string): Promise<ApiResource<StorefrontProduct>> => {
      const { product } = await this.request<{ product: GraphQLProduct | null }>(
        `query($slug: String!) { product(slug: $slug) { ${PRODUCT_FIELDS} } }`,
        { slug },
      )

      if (product === null) throw new ApiClientError(404, { message: 'Product not found.' })

      return { data: toStorefrontProduct(product) }
    },
  }

  readonly collections = {
    list: async (): Promise<ApiCollection<StorefrontCollection>> => {
      const { collections } = await this.request<{ collections: { data: StorefrontCollection[] } }>(
        'query { collections { data { id title slug description } } }',
      )

      return { data: collections.data }
    },

    get: async (slug: string, page?: number): Promise<StorefrontCollectionShowResponse> => {
      const { collection, products } = await this.request<{ collection: StorefrontCollection | null, products: { data: GraphQLProduct[], pageInfo: GraphQLPageInfo } }>(
        `query($slug: String!, $page: Int) {
           collection(slug: $slug) { id title slug description }
           products(collection: $slug, page: $page) { data { ${PRODUCT_FIELDS} } pageInfo { currentPage lastPage perPage total } }
         }`,
        { slug, page },
      )

      if (collection === null) throw new ApiClientError(404, { message: 'Collection not found.' })

      return { data: collection, products: { data: products.data.map(toStorefrontProduct), meta: pageInfoToMeta(products.pageInfo) } }
    },
  }

  readonly categories = {
    list: async (): Promise<ApiCollection<StorefrontCategory>> => {
      const { categories } = await this.request<{ categories: Array<{ title: string, slug: string, children: Array<{ title: string, slug: string, children: unknown[] }> }> }>(
        'query { categories { title slug children { title slug children { title slug children { title slug } } } } }',
      )

      return { data: categories as StorefrontCategory[] }
    },

    get: async (slug: string): Promise<ApiResource<StorefrontCategory>> => {
      const { category } = await this.request<{ category: StorefrontCategory | null }>(
        `query($slug: String!) { category(slug: $slug) { title slug children { title slug children { title slug } } } }`,
        { slug },
      )

      if (category === null) throw new ApiClientError(404, { message: 'Category not found.' })

      return { data: category }
    },
  }

  readonly search = {
    index: async (params: { q?: string, filters?: Record<string, unknown>, sort?: string, page?: number, perPage?: number } = {}): Promise<SearchResponse> =>
      this.request<SearchResponse>(
        `query($query: String, $filters: JSON, $sort: String, $page: Int, $perPage: Int) {
           search(query: $query, filters: $filters, sort: $sort, page: $page, perPage: $perPage) {
             items { productId title slug description vendor productType price { min max currency } thumbnailUrl availability score isPinned }
             total page perPage facets
             searchQueryId
           }
         }`,
        { query: params.q, filters: params.filters, sort: params.sort, page: params.page, perPage: params.perPage },
      ).then(({ search }: any) => ({
        data: search.items.map((item: any) => ({
          product_id: item.productId, title: item.title, slug: item.slug, description: item.description,
          vendor: item.vendor, product_type: item.productType, price: item.price, thumbnail_url: item.thumbnailUrl,
          availability: item.availability, score: item.score, is_pinned: item.isPinned,
        })),
        meta: { total: search.total, page: search.page, per_page: search.perPage, search_query_id: search.searchQueryId },
        facets: search.facets,
      })),

    suggestions: async (q: string): Promise<SearchSuggestionsResponse> =>
      this.request<SearchSuggestionsResponse>(
        `query($q: String!) { searchSuggestions(query: $q) { products { productId title thumbnailUrl } collections { id title } categories { id title } popularQueries } }`,
        { q },
      ).then(({ searchSuggestions }: any) => ({ data: searchSuggestions })),
  }

  readonly cart = {
    get: (): Promise<ApiResource<Cart>> => this.request<{ cart: GraphQLCart }>('query { cart { ' + CART_FIELDS + ' } }').then(({ cart }) => ({ data: toCart(cart) })),

    addItem: (data: { variant_id: string, quantity: number }): Promise<ApiResource<Cart>> =>
      this.request<{ addCartItem: GraphQLCart }>(
        `mutation($variantId: ID!, $quantity: Int!) { addCartItem(variantId: $variantId, quantity: $quantity) { ${CART_FIELDS} } }`,
        { variantId: data.variant_id, quantity: data.quantity },
      ).then(({ addCartItem }) => ({ data: toCart(addCartItem) })),

    updateItem: (itemId: string, data: { quantity: number }): Promise<ApiResource<Cart>> =>
      this.request<{ updateCartItem: GraphQLCart }>(
        `mutation($itemId: ID!, $quantity: Int!) { updateCartItem(itemId: $itemId, quantity: $quantity) { ${CART_FIELDS} } }`,
        { itemId, quantity: data.quantity },
      ).then(({ updateCartItem }) => ({ data: toCart(updateCartItem) })),

    removeItem: (itemId: string): Promise<ApiResource<Cart>> =>
      this.request<{ removeCartItem: GraphQLCart }>(
        `mutation($itemId: ID!) { removeCartItem(itemId: $itemId) { ${CART_FIELDS} } }`,
        { itemId },
      ).then(({ removeCartItem }) => ({ data: toCart(removeCartItem) })),
  }
}

const PRODUCT_FIELDS = `
  id title slug description vendor productType
  seo { title description }
  price { amount currency }
  variants {
    id title sku
    price { amount currency }
    compareAtPrice { amount currency }
    options { option value }
    availability { tracked available inStock }
    media { url alt position }
  }
  media { url alt position }
`

const CART_FIELDS = `
  id token currency
  items { id quantity lineTotal { amount currency } variant { id title sku price { amount currency } media { url alt position } } }
`

interface GraphQLPageInfo { currentPage: number, lastPage: number, perPage: number, total: number }

interface GraphQLProduct {
  id: string
  title: string
  slug: string
  description: string | null
  vendor: string | null
  productType: string | null
  seo: { title: string | null, description: string | null }
  price: { amount: number, currency: string } | null
  variants: Array<{
    id: string
    title: string
    sku: string | null
    price: { amount: number, currency: string }
    compareAtPrice: { amount: number, currency: string } | null
    options: Array<{ option: string, value: string }>
    availability: { tracked: boolean, available: number, inStock: boolean }
    media: Array<{ url: string, alt: string | null, position: number }>
  }>
  media: Array<{ url: string, alt: string | null, position: number }>
}

interface GraphQLCart {
  id: string
  token: string
  currency: string
  items: Array<{
    id: string
    quantity: number
    lineTotal: { amount: number, currency: string } | null
    variant: { id: string, title: string, sku: string | null, price: { amount: number, currency: string }, media: Array<{ url: string, alt: string | null, position: number }> } | null
  }>
}

function pageInfoToMeta(pageInfo: GraphQLPageInfo) {
  return { current_page: pageInfo.currentPage, from: null, last_page: pageInfo.lastPage, per_page: pageInfo.perPage, to: null, total: pageInfo.total }
}

function toStorefrontProduct(product: GraphQLProduct): StorefrontProduct {
  return {
    id: product.id,
    title: product.title,
    slug: product.slug,
    description: product.description,
    vendor: product.vendor,
    product_type: product.productType,
    seo: product.seo,
    price: product.price,
    variants: product.variants.map(variant => ({
      id: variant.id,
      title: variant.title,
      sku: variant.sku,
      price: variant.price,
      compare_at_price: variant.compareAtPrice,
      options: variant.options,
      availability: { tracked: variant.availability.tracked, available: variant.availability.available, in_stock: variant.availability.inStock },
      media: variant.media,
    })),
    media: product.media,
  }
}

function toCart(cart: GraphQLCart): Cart {
  const items = cart.items.filter(item => item.variant !== null)

  return {
    id: cart.id,
    items: items.map(item => ({
      id: item.id,
      variant_id: item.variant!.id,
      title: item.variant!.title,
      sku: item.variant!.sku,
      quantity: item.quantity,
      price: item.variant!.price,
      line_total: item.lineTotal ?? { amount: 0, currency: cart.currency },
      media: item.variant!.media,
    })),
    items_subtotal: items.reduce((sum, item) => sum + (item.lineTotal?.amount ?? 0), 0),
    total: items.reduce((sum, item) => sum + (item.lineTotal?.amount ?? 0), 0),
    currency: cart.currency,
  }
}
