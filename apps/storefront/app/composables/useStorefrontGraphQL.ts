import { StorefrontGraphQLClient } from '@obscurify/api-client'

/**
 * The GraphQL transport for the exact same storefront operations
 * useStorefrontApi() exposes over REST (Milestone 23, spec section 9:
 * "switch from REST to GraphQL without business logic changes") —
 * `StorefrontGraphQLClient` reshapes every GraphQL response into the
 * identical `ApiResource<T>`/`ApiCollection<T>` contract REST already
 * returns, so a page can call this composable instead of
 * useStorefrontApi() and read the response exactly the same way.
 * Not module-scoped for the same reason as useStorefrontApi() — see
 * that composable's own docblock.
 */
export function useStorefrontGraphQL(): StorefrontGraphQLClient {
  return new StorefrontGraphQLClient({
    baseUrl: useStorefrontApiBaseUrl(),
  })
}
