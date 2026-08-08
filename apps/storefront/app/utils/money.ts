import type { Money } from '@obscurify/types'

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
