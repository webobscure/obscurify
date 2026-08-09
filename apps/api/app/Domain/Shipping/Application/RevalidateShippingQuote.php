<?php

namespace App\Domain\Shipping\Application;

use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Shipping\Enums\ShippingMethodStatus;
use App\Domain\Shipping\Exceptions\ShippingQuoteExpiredException;
use App\Domain\Shipping\Exceptions\ShippingQuoteInvalidException;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingQuote;

/**
 * Checkout-completion-time revalidation (spec section 12): the quote model
 * policy was chosen over always-recalculating, so this checks the
 * already-selected ShippingQuote is still good rather than re-deriving a
 * rate from scratch. Called from inside CompleteCheckout's own transaction
 * on the already-locked Checkout — only when one was actually selected;
 * see CompleteCheckout for why selection itself stays optional.
 */
final class RevalidateShippingQuote
{
    public function handle(Checkout $checkout): ShippingQuote
    {
        $quote = ShippingQuote::query()->find($checkout->shipping_quote_id);

        // Tenant-scoped find() already can't cross stores (BelongsToTenant),
        // but a quote belonging to a *different* checkout in the same store
        // is a structural impossibility today (nothing ever points a
        // checkout at another checkout's quote) — checked explicitly anyway
        // per spec section 13's "must belong to the same Checkout and
        // Store", since defending an invariant that's merely true today is
        // cheaper than an IDOR the day something changes that.
        if ($quote === null || $quote->checkout_id !== $checkout->id) {
            throw ShippingQuoteInvalidException::make('quote does not belong to this checkout.');
        }

        if ($quote->isExpired()) {
            throw ShippingQuoteExpiredException::make();
        }

        if ($quote->currency !== $checkout->currency) {
            throw ShippingQuoteInvalidException::make('currency mismatch.');
        }

        if ($quote->shipping_method_id !== null) {
            $method = ShippingMethod::query()->find($quote->shipping_method_id);

            if ($method === null || $method->status !== ShippingMethodStatus::Active) {
                throw ShippingQuoteInvalidException::make('shipping method is no longer active.');
            }
        }

        return $quote;
    }
}
