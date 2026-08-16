<?php

namespace App\Domain\RussianCommerce\Http\Controllers;

use App\Domain\RussianCommerce\Application\CreateFiscalizationProvider;
use App\Domain\RussianCommerce\Application\DeleteFiscalizationProvider;
use App\Domain\RussianCommerce\Application\UpdateFiscalizationProvider;
use App\Domain\RussianCommerce\Http\Requests\StoreFiscalizationProviderRequest;
use App\Domain\RussianCommerce\Http\Requests\UpdateFiscalizationProviderRequest;
use App\Domain\RussianCommerce\Http\Resources\FiscalizationProviderResource;
use App\Domain\RussianCommerce\Models\FiscalizationProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class FiscalizationProviderController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return FiscalizationProviderResource::collection(FiscalizationProvider::query()->orderBy('name')->get());
    }

    public function store(StoreFiscalizationProviderRequest $request, CreateFiscalizationProvider $action): JsonResponse
    {
        $provider = $action->handle($request->validated());

        return (new FiscalizationProviderResource($provider))->response()->setStatusCode(201);
    }

    public function update(
        UpdateFiscalizationProviderRequest $request,
        FiscalizationProvider $fiscalizationProvider,
        UpdateFiscalizationProvider $action,
    ): FiscalizationProviderResource {
        return new FiscalizationProviderResource($action->handle($fiscalizationProvider, $request->validated()));
    }

    public function destroy(FiscalizationProvider $fiscalizationProvider, DeleteFiscalizationProvider $action): Response
    {
        $action->handle($fiscalizationProvider);

        return response()->noContent();
    }
}
