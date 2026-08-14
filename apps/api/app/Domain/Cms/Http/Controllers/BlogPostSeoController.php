<?php

namespace App\Domain\Cms\Http\Controllers;

use App\Domain\Cms\Enums\SeoSubjectType;
use App\Domain\Cms\Http\Requests\UpdateSeoMetadataRequest;
use App\Domain\Cms\Http\Resources\SeoMetadataResource;
use App\Domain\Cms\Models\BlogPost;
use App\Domain\Cms\Models\SeoMetadata;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class BlogPostSeoController extends Controller
{
    public function show(BlogPost $blogPost): SeoMetadataResource
    {
        return new SeoMetadataResource($this->find($blogPost) ?? new SeoMetadata);
    }

    /**
     * Always 200 — see PageVersionSeoController::update's docblock for
     * why the implicit 201-on-first-save is deliberately avoided here.
     */
    public function update(UpdateSeoMetadataRequest $request, BlogPost $blogPost): JsonResponse
    {
        $seo = SeoMetadata::query()->updateOrCreate(
            ['subject_type' => SeoSubjectType::BlogPost->value, 'subject_id' => $blogPost->id],
            $request->validated(),
        );

        return (new SeoMetadataResource($seo))->response()->setStatusCode(200);
    }

    private function find(BlogPost $blogPost): ?SeoMetadata
    {
        return SeoMetadata::query()
            ->where('subject_type', SeoSubjectType::BlogPost->value)
            ->where('subject_id', $blogPost->id)
            ->first();
    }
}
