<?php

namespace App\Domain\Cms\Application;

use App\Domain\Cms\Enums\PageStatus;
use App\Domain\Cms\Enums\PageVersionStatus;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\PageVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Clones an entire page — a new Page row plus a single new draft
 * PageVersion carrying a full copy of the source's *current* content
 * (its draft if one exists, otherwise its latest version). Mirrors
 * DuplicateTheme.
 */
final class DuplicatePage
{
    public function __construct(private readonly ClonePageVersionContent $clonePageVersionContent) {}

    public function handle(Page $source): Page
    {
        return DB::transaction(function () use ($source) {
            $sourceVersion = $source->versions()
                ->where('status', PageVersionStatus::Draft->value)
                ->latest('version_number')
                ->first() ?? $source->versions()->latest('version_number')->firstOrFail();

            $copy = Page::query()->create([
                'title' => $source->title.' (copy)',
                'slug' => $source->slug.'-copy-'.Str::lower(Str::random(6)),
                'status' => PageStatus::Draft->value,
            ]);

            $newVersion = PageVersion::query()->create([
                'page_id' => $copy->id,
                'version_number' => 1,
                'status' => PageVersionStatus::Draft->value,
                'sections' => [],
            ]);

            $this->clonePageVersionContent->handle($sourceVersion, $newVersion);

            return $copy->load('versions');
        });
    }
}
