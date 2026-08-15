<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Automatic ("system") tags
    |--------------------------------------------------------------------------
    |
    | Thresholds RecomputeCustomerMetrics uses to auto-assign/remove the
    | four system tags (spec section 11's "VIP status gained"/"Customer
    | became inactive" triggers, plus first-order/repeat-customer). Money
    | thresholds are minor units (ADR-010).
    |
    */

    'inactive_after_days' => (int) env('CUSTOMER_INTELLIGENCE_INACTIVE_AFTER_DAYS', 90),
    'vip_lifetime_value_amount' => (int) env('CUSTOMER_INTELLIGENCE_VIP_LIFETIME_VALUE_AMOUNT', 50000),

];
