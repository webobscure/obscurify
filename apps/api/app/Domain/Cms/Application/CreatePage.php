<?php

namespace App\Domain\Cms\Application;

use App\Domain\Cms\Enums\PageStatus;
use App\Domain\Cms\Enums\PageVersionStatus;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\PageTemplate;
use App\Domain\Cms\Models\PageVersion;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Page with its first draft PageVersion (v1). `page_template_id`
 * is a create-time-only hint — its `sections` are copied in once, never
 * stored as a live reference (see PageTemplate's migration docblock for
 * why), so it is not a column on Page or PageVersion at all.
 */
final class CreatePage
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Page
    {
        return DB::transaction(function () use ($data) {
            $page = Page::query()->create([
                'title' => $data['title'],
                'slug' => $data['slug'],
                'status' => PageStatus::Draft->value,
            ]);

            $sections = $data['sections'] ?? null;

            if ($sections === null && isset($data['page_template_id'])) {
                $sections = PageTemplate::query()->findOrFail($data['page_template_id'])->sections;
            }

            PageVersion::query()->create([
                'page_id' => $page->id,
                'version_number' => 1,
                'status' => PageVersionStatus::Draft->value,
                'sections' => $sections ?? [],
            ]);

            return $page->load('versions');
        });
    }
}
