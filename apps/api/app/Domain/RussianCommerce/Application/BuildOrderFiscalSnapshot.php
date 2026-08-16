<?php

namespace App\Domain\RussianCommerce\Application;

use App\Domain\Carts\Models\CartItem;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Orders\Models\Order;
use App\Domain\RussianCommerce\Enums\VatRate;
use App\Domain\RussianCommerce\Models\FiscalizationSettings;
use App\Domain\RussianCommerce\Models\OrderFiscalSnapshot;
use App\Domain\RussianCommerce\Models\StoreLegalProfile;
use App\Domain\RussianCommerce\Support\ResolveProductFiscalProfile;
use Illuminate\Support\Collection;

/**
 * Spec section 11 — called once, inside CompleteCheckout's own
 * transaction, right after the Order/OrderItem rows exist. Writes a
 * frozen copy of the store's CURRENT StoreLegalProfile onto the order;
 * later edits to that profile never touch already-created snapshots,
 * simply because nothing else ever reads StoreLegalProfile for an
 * existing order again.
 *
 * A store that has never configured a StoreLegalProfile has no Russian
 * commerce identity to snapshot yet — handle() returns null rather than
 * inventing placeholder legal_name/inn values, since
 * seller_legal_name/seller_inn are non-nullable columns (a snapshot
 * without a real seller identity would be worse than no snapshot at
 * all). This keeps Russian Commerce an opt-in bolt-on: stores that
 * never touch it get no OrderFiscalSnapshot rows, and nothing else in
 * checkout depends on one existing.
 */
final class BuildOrderFiscalSnapshot
{
    public function __construct(private readonly ResolveProductFiscalProfile $resolveProductFiscalProfile) {}

    /**
     * @param  Collection<int, array{item: CartItem, variant: ProductVariant, product: Product}>  $lines
     */
    public function handle(Order $order, Collection $lines): ?OrderFiscalSnapshot
    {
        $legalProfile = StoreLegalProfile::query()->where('store_id', $order->store_id)->first();

        if ($legalProfile === null) {
            return null;
        }

        $settings = FiscalizationSettings::query()->where('store_id', $order->store_id)->first();
        $receiptRequired = $settings !== null && $settings->receipts_required;

        // A line's VAT rate comes from ResolveProductFiscalProfile (spec
        // section 12), so a single order can legitimately mix rates. The
        // snapshot's own vat_rate column is one representative label for
        // display (the rate responsible for the largest share of
        // vat_amount) — it is never used as a computation input.
        // Per-line VAT is what actually drives FiscalReceiptItem later,
        // see CreateFiscalReceipt.
        $vatAmountByRate = [];
        $totalVatAmount = 0;

        foreach ($lines as $line) {
            $profile = $this->resolveProductFiscalProfile->handle($line['variant']);
            $lineTotal = $line['variant']->price_amount * $line['item']->quantity;
            $lineVat = $profile['vat_rate']->amountFromInclusiveTotal($lineTotal);

            $totalVatAmount += $lineVat;
            $rateKey = $profile['vat_rate']->value;
            $vatAmountByRate[$rateKey] = ($vatAmountByRate[$rateKey] ?? 0) + $lineVat;
        }

        arsort($vatAmountByRate);
        $dominantRate = array_key_first($vatAmountByRate) ?? VatRate::None->value;

        return OrderFiscalSnapshot::query()->create([
            'order_id' => $order->id,
            'seller_legal_entity_type' => $legalProfile->legal_entity_type->value,
            'seller_legal_name' => $legalProfile->legal_name,
            'seller_inn' => $legalProfile->inn,
            'seller_kpp' => $legalProfile->kpp,
            'vat_rate' => $dominantRate,
            'vat_amount' => $totalVatAmount,
            'receipt_required' => $receiptRequired,
        ]);
    }
}
