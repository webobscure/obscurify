<?php

namespace App\Domain\Notifications\Support;

use Illuminate\Support\Arr;

/**
 * Interpolates `{{path.to.value}}` placeholders against a context array
 * (spec section 4: Customer/Order/Payment/Shipment/Refund/Return/Store/
 * Workflow variables) — the same dot-path/`Arr::get()` convention
 * WorkflowConditionEvaluator's `variable_key` and
 * WorkflowActionExecutor's `{{steps.x.output.y}}` already use, applied
 * here to a whole template body instead of one config value. A missing
 * path renders as an empty string rather than leaving the literal
 * placeholder in the output — safer for a customer-facing message.
 */
final class NotificationTemplateRenderer
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function render(?string $template, array $context): string
    {
        if ($template === null || $template === '') {
            return '';
        }

        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            fn (array $matches) => $this->stringify(Arr::get($context, $matches[1])),
            $template,
        ) ?? $template;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value) ?: '';
    }
}
