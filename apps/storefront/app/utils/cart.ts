import type { CartItem } from '@obscurify/types'

/** Total unit count across all cart lines, for the header's cart indicator. */
export function computeItemCount(items: CartItem[]): number {
  return items.reduce((sum, item) => sum + item.quantity, 0)
}
