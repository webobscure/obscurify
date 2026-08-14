<?php

namespace App\Domain\Cms\Application;

use App\Domain\Cms\Enums\SeoSubjectType;
use App\Domain\Cms\Models\PageVersion;
use App\Domain\Cms\Models\SeoMetadata;

/**
 * Copies content from one PageVersion to another — sections plus its SEO
 * metadata row, if any — reused by PublishPageVersion (which always
 * clones the version it just published into a fresh draft) and
 * DuplicatePage. Mirrors CloneThemeVersionContent's role for themes.
 */
final class ClonePageVersionContent
{
    public function handle(PageVersion $source, PageVersion $target): void
    {
        $target->update(['sections' => $source->sections]);

        $sourceSeo = SeoMetadata::query()
            ->where('subject_type', SeoSubjectType::PageVersion->value)
            ->where('subject_id', $source->id)
            ->first();

        if ($sourceSeo !== null) {
            SeoMetadata::query()->create([
                'subject_type' => SeoSubjectType::PageVersion->value,
                'subject_id' => $target->id,
                'meta_title' => $sourceSeo->meta_title,
                'meta_description' => $sourceSeo->meta_description,
                'canonical_url' => $sourceSeo->canonical_url,
                'og_image' => $sourceSeo->og_image,
            ]);
        }
    }
}
