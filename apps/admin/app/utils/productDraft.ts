export interface ProductDraft {
  title: string
  description: string | null
  vendor: string | null
  product_type: string | null
  tags: string[]
  status: string
  seo_title: string | null
  seo_description: string | null
  slug: string
}

/**
 * Pure comparison behind products/[id].vue's header Save state (see
 * docs/design/DESIGN_SYSTEM.md "Product Editor" — one page-level Save,
 * not per-section). Extracted so dirty-tracking is unit-testable without
 * mounting the page (this app has no Vue component-mount test harness —
 * see apps/admin/tests/ — logic worth testing lives in plain functions).
 */
export function isProductDraftDirty(draft: ProductDraft, saved: ProductDraft): boolean {
  return (
    draft.title !== saved.title
    || draft.description !== saved.description
    || draft.vendor !== saved.vendor
    || draft.product_type !== saved.product_type
    || draft.status !== saved.status
    || draft.seo_title !== saved.seo_title
    || draft.seo_description !== saved.seo_description
    || draft.slug !== saved.slug
    || !sameTags(draft.tags, saved.tags)
  )
}

function sameTags(a: string[], b: string[]): boolean {
  if (a.length !== b.length) return false
  return a.every((tag, index) => tag === b[index])
}

export function productDraftFrom(product: ProductDraft): ProductDraft {
  return {
    title: product.title,
    description: product.description,
    vendor: product.vendor,
    product_type: product.product_type,
    tags: [...product.tags],
    status: product.status,
    seo_title: product.seo_title,
    seo_description: product.seo_description,
    slug: product.slug,
  }
}
