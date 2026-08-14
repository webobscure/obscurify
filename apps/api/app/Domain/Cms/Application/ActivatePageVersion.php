<?php

namespace App\Domain\Cms\Application;

use App\Domain\Cms\Models\ActivePageVersion as ActivePageVersionModel;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\PageVersion;
use Illuminate\Validation\ValidationException;

/**
 * Points a page's ActivePageVersion at a specific, already-published
 * version — never a draft. Mirrors ActivateTheme.
 */
final class ActivatePageVersion
{
    public function handle(Page $page, PageVersion $version): ActivePageVersionModel
    {
        if ($version->status->value !== 'published') {
            throw ValidationException::withMessages(['page_version' => 'Only a published version can be activated.']);
        }

        return ActivePageVersionModel::query()->updateOrCreate(
            ['page_id' => $page->id],
            ['page_version_id' => $version->id, 'activated_at' => now()],
        );
    }
}
