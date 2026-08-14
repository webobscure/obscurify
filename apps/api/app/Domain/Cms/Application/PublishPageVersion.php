<?php

namespace App\Domain\Cms\Application;

use App\Domain\Cms\Enums\PageVersionStatus;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\PageVersion;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Freezes a page's current draft version, activates it, and creates a
 * brand new draft cloned from what was just published. Mirrors
 * PublishThemeVersion.
 */
final class PublishPageVersion
{
    public function __construct(
        private readonly ClonePageVersionContent $clonePageVersionContent,
        private readonly ActivatePageVersion $activatePageVersion,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(PageVersion $version): PageVersion
    {
        if (! $version->isDraft()) {
            throw ValidationException::withMessages(['page_version' => 'Only a draft version can be published.']);
        }

        return DB::transaction(function () use ($version) {
            $version->update([
                'status' => PageVersionStatus::Published->value,
                'published_at' => now(),
            ]);

            $page = Page::query()->findOrFail($version->page_id);
            $nextNumber = PageVersion::query()->where('page_id', $page->id)->max('version_number') + 1;

            $newDraft = PageVersion::query()->create([
                'page_id' => $page->id,
                'created_from_version_id' => $version->id,
                'version_number' => $nextNumber,
                'status' => PageVersionStatus::Draft->value,
                'sections' => [],
            ]);

            $this->clonePageVersionContent->handle($version, $newDraft);
            $this->activatePageVersion->handle($page, $version);

            $this->recordOutboxEvent->handle('PageVersionPublished', 'Page', $page->id, [
                'page_id' => $page->id,
                'store_id' => $page->store_id,
                'page_version_id' => $version->id,
                'version_number' => $version->version_number,
            ]);

            return $version->fresh();
        });
    }
}
