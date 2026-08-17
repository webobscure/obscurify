import { describe, expect, it } from 'vitest'
import { isProductDraftDirty, productDraftFrom, type ProductDraft } from '../app/utils/productDraft'

function baseProduct(overrides: Partial<ProductDraft> = {}): ProductDraft {
  return {
    title: 'Футболка Oversize',
    description: 'Свободный крой',
    vendor: 'Acme',
    product_type: 'Футболки',
    tags: ['лето', 'хлопок'],
    status: 'active',
    seo_title: null,
    seo_description: null,
    slug: 'futbolka-oversize',
    ...overrides,
  }
}

describe('isProductDraftDirty (products/[id].vue header Save state)', () => {
  it('loading initial data does NOT mark the page dirty', () => {
    const saved = baseProduct()
    const draft = productDraftFrom(saved)
    expect(isProductDraftDirty(draft, saved)).toBe(false)
  })

  it('editing the title marks the page dirty', () => {
    const saved = baseProduct()
    const draft = productDraftFrom(saved)
    draft.title = 'Футболка Oversize Pro'
    expect(isProductDraftDirty(draft, saved)).toBe(true)
  })

  it('editing sidebar metadata (vendor, product type, status, SEO, slug) marks the page dirty', () => {
    const saved = baseProduct()

    expect(isProductDraftDirty({ ...productDraftFrom(saved), vendor: 'Other Vendor' }, saved)).toBe(true)
    expect(isProductDraftDirty({ ...productDraftFrom(saved), product_type: 'Худи' }, saved)).toBe(true)
    expect(isProductDraftDirty({ ...productDraftFrom(saved), status: 'draft' }, saved)).toBe(true)
    expect(isProductDraftDirty({ ...productDraftFrom(saved), seo_title: 'New SEO title' }, saved)).toBe(true)
    expect(isProductDraftDirty({ ...productDraftFrom(saved), slug: 'new-slug' }, saved)).toBe(true)
  })

  it('adding, removing, or reordering tags marks the page dirty', () => {
    const saved = baseProduct()

    expect(isProductDraftDirty({ ...productDraftFrom(saved), tags: [...saved.tags, 'новый'] }, saved)).toBe(true)
    expect(isProductDraftDirty({ ...productDraftFrom(saved), tags: [saved.tags[0]!] }, saved)).toBe(true)
    expect(isProductDraftDirty({ ...productDraftFrom(saved), tags: [...saved.tags].reverse() }, saved)).toBe(true)
  })

  it('an identical tags array (new reference, same values/order) is NOT dirty', () => {
    const saved = baseProduct()
    const draft = productDraftFrom(saved)
    draft.tags = [...saved.tags]
    expect(isProductDraftDirty(draft, saved)).toBe(false)
  })

  it('a successful save (draft synced back from the server response) clears dirty state', () => {
    const saved = baseProduct()
    const draft = productDraftFrom(saved)
    draft.title = 'Изменённое название'
    expect(isProductDraftDirty(draft, saved)).toBe(true)

    // Simulates products/[id].vue's save(): on success, product.value and
    // draft are both re-synced from the server response.
    const serverResponse = baseProduct({ title: draft.title })
    const draftAfterSave = productDraftFrom(serverResponse)
    expect(isProductDraftDirty(draftAfterSave, serverResponse)).toBe(false)
  })

  it('a failed save preserves dirty state (draft is never reset on error)', () => {
    const saved = baseProduct()
    const draft = productDraftFrom(saved)
    draft.title = 'Изменённое название'

    // Simulates products/[id].vue's save() catch path: `product` (and
    // therefore the "saved" comparison baseline) is left untouched when
    // the request throws — only saveFailed flips, draft is untouched.
    expect(isProductDraftDirty(draft, saved)).toBe(true)
  })
})
