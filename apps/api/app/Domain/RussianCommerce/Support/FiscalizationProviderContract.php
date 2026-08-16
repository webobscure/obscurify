<?php

namespace App\Domain\RussianCommerce\Support;

use App\Domain\RussianCommerce\Models\FiscalReceipt;
use Illuminate\Http\Request;

/**
 * Provider-neutral fiscalization boundary (spec section 5). Mirrors
 * PaymentProviderContract's async shape deliberately — a real OFD/cash-
 * register provider (ATOL, OrangeData, CloudKassir — see
 * FiscalizationProvider::FUTURE_CODES) confirms fiscalization
 * asynchronously, the same way a card payment confirms via webhook, not
 * synchronously in the request that triggered it.
 */
interface FiscalizationProviderContract
{
    /**
     * Registry key, e.g. "fake".
     */
    public function code(): string;

    /**
     * Submits the receipt for fiscalization. Must not mark the receipt
     * Fiscalized itself — that only ever happens through
     * ProcessFiscalizationCallback, called after verifyCallback() passes.
     */
    public function submitReceipt(FiscalReceipt $receipt): FiscalizationSubmissionResult;

    /**
     * Constant-time signature check against the raw request. Must be
     * called, and must pass, before parseCallback() is trusted.
     */
    public function verifyCallback(Request $request): bool;

    public function parseCallback(Request $request): FiscalizationCallbackEvent;
}
