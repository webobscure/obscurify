<?php

namespace App\Domain\RussianCommerce\Enums;

/**
 * Spec section 6 — the 54-FZ line-item "предмет расчёта" (what kind of
 * thing this line represents). `Commodity` is the default for an
 * ordinary physical product; `Service`/`Work` exist for a future
 * services catalog; `Payment` and `AgentCommission` are reserved for
 * marketplace/agent-commission scenarios this platform doesn't model
 * yet (see spec section 22: no marketplace synchronization).
 */
enum FiscalReceiptItemPaymentSubject: string
{
    case Commodity = 'commodity';
    case Service = 'service';
    case Work = 'work';
    case Payment = 'payment';
    case AgentCommission = 'agent_commission';
}
