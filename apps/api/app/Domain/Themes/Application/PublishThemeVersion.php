<?php

namespace App\Domain\Themes\Application;

use App\Domain\Themes\Enums\ThemeVersionStatus;
use App\Domain\Themes\Models\Theme;
use App\Domain\Themes\Models\ThemeVersion;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Freezes the theme's current draft version (status -> published,
 * published_at set — from this point it's immutable, spec section 12),
 * activates it for the store (ActivateTheme), and creates a brand new
 * draft version cloned from what was just published — so editing can
 * continue immediately without touching the now-frozen snapshot.
 */
final class PublishThemeVersion
{
    public function __construct(
        private readonly CloneThemeVersionContent $cloneThemeVersionContent,
        private readonly ActivateTheme $activateTheme,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(ThemeVersion $version): ThemeVersion
    {
        if (! $version->isDraft()) {
            throw ValidationException::withMessages(['version' => 'Only a draft version can be published.']);
        }

        return DB::transaction(function () use ($version) {
            $version->update([
                'status' => ThemeVersionStatus::Published->value,
                'published_at' => now(),
            ]);

            $theme = Theme::query()->findOrFail($version->theme_id);
            $nextNumber = ThemeVersion::query()->where('theme_id', $theme->id)->max('version_number') + 1;

            $newDraft = ThemeVersion::query()->create([
                'theme_id' => $theme->id,
                'created_from_version_id' => $version->id,
                'version_number' => $nextNumber,
                'status' => ThemeVersionStatus::Draft->value,
            ]);

            $this->cloneThemeVersionContent->handle($version, $newDraft);
            $this->activateTheme->handle($theme, $version);

            $this->recordOutboxEvent->handle('ThemeVersionPublished', 'Theme', $theme->id, [
                'theme_id' => $theme->id,
                'store_id' => $theme->store_id,
                'theme_version_id' => $version->id,
                'version_number' => $version->version_number,
            ]);

            return $version->fresh();
        });
    }
}
