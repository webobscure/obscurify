<?php

namespace App\Domain\Builder\Application;

use App\Domain\Builder\Models\BuilderHistory;
use App\Domain\Builder\Models\BuilderRevision;
use App\Domain\Builder\Models\PageLayout;
use App\Domain\Builder\Support\ReplaceSectionInstancesFromArray;
use App\Domain\Cms\Models\PageVersion;
use Illuminate\Support\Facades\DB;

/**
 * Lazily bootstraps a PageLayout for a PageVersion the first time the
 * visual Builder opens it — parsing whatever is already in
 * PageVersion.sections (empty for a brand-new page, or already
 * populated if it was edited via Milestone 14's raw-JSON textarea, or
 * cloned forward from a just-published version by ClonePageVersionContent)
 * into SectionInstance/BlockInstance rows, plus a baseline BuilderRevision/
 * BuilderHistory so undo/redo has something to step back to. Deliberately
 * the *only* place `App\Domain\Cms` is touched by Builder, and only by
 * reading a PageVersion's already-public `sections` column — CreatePage/
 * PublishPageVersion/ClonePageVersionContent/RollbackPage in Cms are
 * untouched, preserving the same one-way dependency direction ADR-020
 * already established for Cms depending on Themes (Builder depends on
 * Cms, never the reverse).
 */
final class FindOrCreatePageLayout
{
    public function __construct(private readonly ReplaceSectionInstancesFromArray $replaceSectionInstancesFromArray) {}

    public function handle(PageVersion $version): PageLayout
    {
        $existing = PageLayout::query()->where('page_version_id', $version->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($version) {
            $layout = PageLayout::query()->create(['page_version_id' => $version->id]);

            $this->replaceSectionInstancesFromArray->handle($layout, $version->sections);

            $revision = BuilderRevision::query()->create([
                'page_layout_id' => $layout->id,
                'sequence' => 1,
                'sections' => $version->sections,
            ]);

            BuilderHistory::query()->create([
                'page_layout_id' => $layout->id,
                'current_revision_id' => $revision->id,
            ]);

            return $layout;
        });
    }
}
