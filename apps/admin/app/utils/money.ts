export interface Money {
  amount: number
  currency: string
}

/**
 * Money amounts are always integer minor units from the backend (see
 * ADR-010) — divide by 100 once, here, at the formatting boundary. Never
 * do money arithmetic on the formatted/divided value.
 */
export function formatMoney(money: Money, locale = 'ru-RU'): string {
  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: money.currency,
  }).format(money.amount / 100)
}

/**
 * The inverse of formatMoney's divide-by-100: a plain decimal string
 * (as a merchant would type it, "," or "." decimal separator) back into
 * integer minor units. Returns null for empty/unparseable input — never
 * NaN, never a float — so a caller can distinguish "cleared the field"
 * from "typed garbage" and decide what to do (MoneyInput.vue falls back
 * to the previous value on unparseable input; this function itself never
 * guesses).
 */
export function parseMoney(input: string): number | null {
  const normalized = input.replace(',', '.').trim()
  if (normalized === '') return null

  const parsed = Number.parseFloat(normalized)
  return Number.isFinite(parsed) ? Math.round(parsed * 100) : null
}

/**
 * Margin as a whole-number percentage, computed entirely in integer
 * minor units (never divides to a float amount first) — null when cost
 * is absent (no cost recorded) or price is zero/absent (division by
 * zero would otherwise silently yield Infinity).
 */
export function calculateMarginPercent(priceAmount: number | null, costAmount: number | null | undefined): number | null {
  if (priceAmount === null || priceAmount === 0) return null
  if (costAmount === null || costAmount === undefined) return null

  return Math.round(((priceAmount - costAmount) / priceAmount) * 100)
}
