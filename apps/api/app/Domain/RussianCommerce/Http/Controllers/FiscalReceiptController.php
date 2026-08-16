<?php

namespace App\Domain\RussianCommerce\Http\Controllers;

use App\Domain\RussianCommerce\Http\Resources\FiscalReceiptResource;
use App\Domain\RussianCommerce\Models\FiscalReceipt;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only (spec section 17: "fiscalization status shown on
 * Order/Payment details") — a receipt's lifecycle only ever advances
 * through CreateFiscalReceipt/ProcessFiscalizationCallback, never
 * through a direct admin write.
 */
final class FiscalReceiptController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = FiscalReceipt::query()->orderByDesc('created_at');

        if ($request->filled('order_id')) {
            $query->where('order_id', $request->string('order_id'));
        }

        return FiscalReceiptResource::collection($query->paginate());
    }

    public function show(FiscalReceipt $fiscalReceipt): FiscalReceiptResource
    {
        return new FiscalReceiptResource($fiscalReceipt->load('items'));
    }
}
