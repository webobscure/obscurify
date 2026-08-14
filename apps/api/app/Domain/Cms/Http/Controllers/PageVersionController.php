<?php

namespace App\Domain\Cms\Http\Controllers;

use App\Domain\Cms\Http\Requests\UpdatePageVersionSectionsRequest;
use App\Domain\Cms\Http\Resources\PageVersionResource;
use App\Domain\Cms\Models\PageVersion;
use App\Http\Controllers\Controller;

final class PageVersionController extends Controller
{
    public function updateSections(UpdatePageVersionSectionsRequest $request, PageVersion $pageVersion): PageVersionResource
    {
        $pageVersion->assertEditable();

        $pageVersion->update(['sections' => $request->validated()['sections']]);

        return new PageVersionResource($pageVersion);
    }
}
