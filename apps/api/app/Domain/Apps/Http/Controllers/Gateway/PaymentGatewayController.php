<?php

namespace App\Domain\Apps\Http\Controllers\Gateway;

use App\Domain\Apps\Http\Resources\Gateway\GatewayPaymentResource;
use App\Domain\Payments\Models\Payment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PaymentGatewayController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $payments = Payment::query()->orderByDesc('created_at')->paginate();

        return GatewayPaymentResource::collection($payments);
    }
}
