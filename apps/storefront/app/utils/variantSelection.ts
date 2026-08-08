import type { StorefrontProductVariant } from '@obscurify/types'

export interface OptionGroup {
  option: string
  values: string[]
}

/**
 * Derives the pickable option groups (e.g. Color: [Black, White]) from a
 * product's variants — there's no separate "options" endpoint on the
 * storefront API, so this reads them straight off whichever variants
 * exist.
 */
export function buildOptionGroups(variants: StorefrontProductVariant[]): OptionGroup[] {
  const map = new Map<string, string[]>()

  for (const variant of variants) {
    for (const opt of variant.options ?? []) {
      const values = map.get(opt.option) ?? []
      if (!values.includes(opt.value)) values.push(opt.value)
      map.set(opt.option, values)
    }
  }

  return Array.from(map.entries()).map(([option, values]) => ({ option, values }))
}

/**
 * Finds the variant matching the current option selection. A product
 * with no options at all (a single "Default Title" variant) has no
 * option groups, so its one variant always matches.
 */
export function matchVariant(
  variants: StorefrontProductVariant[],
  selected: Record<string, string>,
  optionGroupCount: number,
): StorefrontProductVariant | undefined {
  if (optionGroupCount === 0) return variants[0]

  return variants.find((variant) => {
    const options = variant.options ?? []

    return options.length === optionGroupCount
      && options.every(opt => selected[opt.option] === opt.value)
  })
}
