<?php

namespace App\Domain\RussianCommerce\Application;

use App\Domain\RussianCommerce\Enums\RussianPaymentMethod;
use App\Domain\RussianCommerce\Models\PaymentMethodSettings;

final class UpdatePaymentMethodSettings
{
    /**
     * @param  list<string>  $enabledMethods  Values of RussianPaymentMethod — validated by the Form Request, not re-validated here.
     */
    public function handle(PaymentMethodSettings $settings, array $enabledMethods): PaymentMethodSettings
    {
        $normalized = collect($enabledMethods)
            ->map(fn (string $value) => RussianPaymentMethod::from($value)->value)
            ->unique()
            ->values()
            ->all();

        $settings->update(['enabled_methods' => $normalized]);

        return $settings->fresh();
    }
}
