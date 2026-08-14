<?php

namespace App\Domain\Cms\Http\Controllers;

use App\Domain\Cms\Http\Requests\StorePageTemplateRequest;
use App\Domain\Cms\Http\Requests\UpdatePageTemplateRequest;
use App\Domain\Cms\Http\Resources\PageTemplateResource;
use App\Domain\Cms\Models\PageTemplate;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class PageTemplateController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PageTemplateResource::collection(PageTemplate::query()->orderBy('name')->get());
    }

    public function store(StorePageTemplateRequest $request): PageTemplateResource
    {
        return new PageTemplateResource(PageTemplate::query()->create($request->validated()));
    }

    public function update(UpdatePageTemplateRequest $request, PageTemplate $pageTemplate): PageTemplateResource
    {
        $pageTemplate->update($request->validated());

        return new PageTemplateResource($pageTemplate);
    }

    public function destroy(PageTemplate $pageTemplate): Response
    {
        $pageTemplate->delete();

        return response()->noContent();
    }
}
