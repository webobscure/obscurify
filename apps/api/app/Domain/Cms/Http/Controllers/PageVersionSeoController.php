<?php

namespace App\Domain\Cms\Http\Controllers;

use App\Domain\Cms\Enums\SeoSubjectType;
use App\Domain\Cms\Http\Requests\UpdateSeoMetadataRequest;
use App\Domain\Cms\Http\Resources\SeoMetadataResource;
use App\Domain\Cms\Models\PageVersion;
use App\Domain\Cms\Models\SeoMetadata;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class PageVersionSeoController extends Controller
{
    public function show(PageVersion $pageVersion): SeoMetadataResource
    {
        return new SeoMetadataResource($this->find($pageVersion) ?? new SeoMetadata);
    }

    /**
     * Always 200, even on first save (an `updateOrCreate` insert) — this
     * sets a page version's SEO fields, the same PATCH/upsert semantics
     * as ThemeSettingController::update, not a "create a new REST
     * resource" endpoint. Returning the JsonResource directly would let
     * Laravel's implicit `wasRecentlyCreated` check answer 201 on that
     * first save, which is technically accurate but a confusing surprise
     * for a merchant PATCHing the same field twice in a row.
     */
    public function update(UpdateSeoMetadataRequest $request, PageVersion $pageVersion): JsonResponse
    {
        $pageVersion->assertEditable();

        $seo = SeoMetadata::query()->updateOrCreate(
            ['subject_type' => SeoSubjectType::PageVersion->value, 'subject_id' => $pageVersion->id],
            $request->validated(),
        );

        return (new SeoMetadataResource($seo))->response()->setStatusCode(200);
    }

    private function find(PageVersion $pageVersion): ?SeoMetadata
    {
        return SeoMetadata::query()
            ->where('subject_type', SeoSubjectType::PageVersion->value)
            ->where('subject_id', $pageVersion->id)
            ->first();
    }
}
