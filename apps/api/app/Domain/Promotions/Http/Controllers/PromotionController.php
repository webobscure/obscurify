<?php

namespace App\Domain\Promotions\Http\Controllers;

use App\Domain\Promotions\Application\CreatePromotion;
use App\Domain\Promotions\Application\PreviewPromotions;
use App\Domain\Promotions\Application\UpdatePromotion;
use App\Domain\Promotions\Http\Requests\PreviewPromotionsRequest;
use App\Domain\Promotions\Http\Requests\StorePromotionRequest;
use App\Domain\Promotions\Http\Requests\UpdatePromotionRequest;
use App\Domain\Promotions\Http\Resources\AppliedDiscountResource;
use App\Domain\Promotions\Http\Resources\PromotionResource;
use App\Domain\Promotions\Http\Resources\PromotionUsageResource;
use App\Domain\Promotions\Models\Promotion;
use App\Domain\Promotions\Models\PromotionUsage;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PromotionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $promotions = Promotion::query()
            ->with(['rules', 'actions', 'discountCodes'])
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        return PromotionResource::collection($promotions);
    }

    public function show(Promotion $promotion): PromotionResource
    {
        return new PromotionResource($promotion->load(['rules', 'actions', 'discountCodes']));
    }

    public function store(StorePromotionRequest $request, CreatePromotion $action): PromotionResource
    {
        $promotion = $action->handle($request->validated());

        return new PromotionResource($promotion);
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion, UpdatePromotion $action): PromotionResource
    {
        $promotion = $action->handle($promotion, $request->validated());

        return new PromotionResource($promotion);
    }

    public function usage(Promotion $promotion): AnonymousResourceCollection
    {
        $usages = PromotionUsage::query()
            ->where('promotion_id', $promotion->id)
            ->latest('created_at')
            ->paginate(50);

        return PromotionUsageResource::collection($usages);
    }

    public function preview(PreviewPromotionsRequest $request, PreviewPromotions $action): JsonResponse
    {
        $result = $action->handle($request->validated());

        return response()->json([
            'discount_amount' => $result->discountAmount,
            'applied' => AppliedDiscountResource::collection($result->applied),
        ]);
    }
}
