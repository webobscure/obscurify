<?php

namespace App\Domain\Promotions\Http\Controllers;

use App\Domain\Promotions\Application\CreateDiscountCode;
use App\Domain\Promotions\Application\UpdateDiscountCode;
use App\Domain\Promotions\Http\Requests\StoreDiscountCodeRequest;
use App\Domain\Promotions\Http\Requests\UpdateDiscountCodeRequest;
use App\Domain\Promotions\Http\Resources\DiscountCodeResource;
use App\Domain\Promotions\Models\DiscountCode;
use App\Domain\Promotions\Models\Promotion;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class DiscountCodeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $codes = DiscountCode::query()
            ->when($request->query('promotion_id'), fn ($query, $promotionId) => $query->where('promotion_id', $promotionId))
            ->orderByDesc('created_at')
            ->get();

        return DiscountCodeResource::collection($codes);
    }

    public function store(StoreDiscountCodeRequest $request, CreateDiscountCode $action): DiscountCodeResource
    {
        $data = $request->validated();
        $promotion = Promotion::query()->findOrFail($data['promotion_id']);
        unset($data['promotion_id']);

        $discountCode = $action->handle($promotion, $data);

        return new DiscountCodeResource($discountCode);
    }

    public function update(UpdateDiscountCodeRequest $request, DiscountCode $discountCode, UpdateDiscountCode $action): DiscountCodeResource
    {
        $discountCode = $action->handle($discountCode, $request->validated());

        return new DiscountCodeResource($discountCode);
    }
}
