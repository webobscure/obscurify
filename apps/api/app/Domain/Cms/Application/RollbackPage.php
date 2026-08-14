<?php

namespace App\Domain\Cms\Application;

use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\PageVersion;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Validation\ValidationException;

/**
 * Repoints ActivePageVersion at an older *published* version of the same
 * page. Never touches the current draft. Mirrors RollbackTheme.
 */
final class RollbackPage
{
    public function __construct(
        private readonly ActivatePageVersion $activatePageVersion,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(PageVersion $version): PageVersion
    {
        if ($version->status->value !== 'published') {
            throw ValidationException::withMessages(['page_version' => 'Can only roll back to a previously published version.']);
        }

        $page = Page::query()->findOrFail($version->page_id);

        $this->activatePageVersion->handle($page, $version);

        $this->recordOutboxEvent->handle('PageRolledBack', 'Page', $page->id, [
            'page_id' => $page->id,
            'store_id' => $page->store_id,
            'page_version_id' => $version->id,
            'version_number' => $version->version_number,
        ]);

        return $version;
    }
}
