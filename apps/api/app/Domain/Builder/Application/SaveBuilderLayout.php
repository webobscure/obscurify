<?php

namespace App\Domain\Builder\Application;

use App\Domain\Builder\Models\BuilderHistory;
use App\Domain\Builder\Models\BuilderRevision;
use App\Domain\Builder\Models\PageLayout;
use App\Domain\Builder\Support\ReplaceSectionInstancesFromArray;
use App\Domain\Builder\Support\SerializeSectionInstances;
use Illuminate\Support\Facades\DB;

/**
 * Persists the visual editor's current in-memory sections array — every
 * drag-and-drop mutation (move/insert/duplicate/delete/reorder a
 * section or block) happens client-side against a local reactive array,
 * the same way Shopify/Webflow-style editors work, and this one call
 * saves the resulting document (spec section 2/9's move/insert/
 * duplicate/delete/reorder list is a client-side interaction model, not
 * a menu of separate REST endpoints). "Autosave" (spec section 9) is
 * this same call on a debounce timer, not a different code path.
 *
 * Replaces SectionInstance/BlockInstance wholesale from the submitted
 * array (ReplaceSectionInstancesFromArray), re-derives the canonical
 * jsonb form from what was actually written (SerializeSectionInstances
 * — never trusts the client's array verbatim, since block/section
 * handles the active theme doesn't recognize are silently dropped by
 * the relational round-trip the same way ThemeRenderer already drops
 * them at render time), writes it to PageVersion.sections (the only
 * column ThemeRenderer ever reads), and appends a new BuilderRevision.
 */
final class SaveBuilderLayout
{
    public function __construct(
        private readonly ReplaceSectionInstancesFromArray $replaceSectionInstancesFromArray,
        private readonly SerializeSectionInstances $serializeSectionInstances,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    public function handle(PageLayout $layout, array $sections): PageLayout
    {
        $layout->pageVersion->assertEditable();

        DB::transaction(function () use ($layout, $sections) {
            $this->replaceSectionInstancesFromArray->handle($layout, $sections);

            $canonical = $this->serializeSectionInstances->handle($layout);
            $layout->pageVersion->update(['sections' => $canonical]);

            $nextSequence = (BuilderRevision::query()->where('page_layout_id', $layout->id)->max('sequence') ?? 0) + 1;
            $revision = BuilderRevision::query()->create([
                'page_layout_id' => $layout->id,
                'sequence' => $nextSequence,
                'sections' => $canonical,
            ]);

            BuilderHistory::query()->updateOrCreate(
                ['page_layout_id' => $layout->id],
                ['current_revision_id' => $revision->id],
            );
        });

        return $layout;
    }
}
