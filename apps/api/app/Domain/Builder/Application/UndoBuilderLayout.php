<?php

namespace App\Domain\Builder\Application;

use App\Domain\Builder\Models\BuilderHistory;
use App\Domain\Builder\Models\BuilderRevision;
use App\Domain\Builder\Models\PageLayout;
use Illuminate\Validation\ValidationException;

final class UndoBuilderLayout
{
    public function __construct(private readonly RestoreBuilderRevision $restoreBuilderRevision) {}

    public function handle(PageLayout $layout): PageLayout
    {
        $history = BuilderHistory::query()->where('page_layout_id', $layout->id)->first();
        $currentSequence = $history?->currentRevision->sequence ?? 0;

        $previous = BuilderRevision::query()
            ->where('page_layout_id', $layout->id)
            ->where('sequence', '<', $currentSequence)
            ->orderByDesc('sequence')
            ->first();

        if ($previous === null) {
            throw ValidationException::withMessages(['revision' => 'Nothing to undo.']);
        }

        return $this->restoreBuilderRevision->handle($layout, $previous);
    }
}
