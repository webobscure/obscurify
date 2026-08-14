<?php

namespace App\Domain\Themes\Application;

use App\Domain\Themes\Models\Theme;
use App\Domain\Themes\Models\ThemeVersion;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use Illuminate\Validation\ValidationException;

/**
 * Repoints ActiveTheme at an older *published* version of the same
 * theme — spec section 12: "Rollback must be possible." Never touches
 * the current draft; a merchant who rolled back can keep editing their
 * in-progress work and publish it later, unaffected.
 */
final class RollbackTheme
{
    public function __construct(
        private readonly ActivateTheme $activateTheme,
        private readonly RecordOutboxEvent $recordOutboxEvent,
    ) {}

    public function handle(ThemeVersion $version): ThemeVersion
    {
        if ($version->status->value !== 'published') {
            throw ValidationException::withMessages(['version' => 'Can only roll back to a previously published version.']);
        }

        $theme = Theme::query()->findOrFail($version->theme_id);

        $this->activateTheme->handle($theme, $version);

        $this->recordOutboxEvent->handle('ThemeRolledBack', 'Theme', $theme->id, [
            'theme_id' => $theme->id,
            'store_id' => $theme->store_id,
            'theme_version_id' => $version->id,
            'version_number' => $version->version_number,
        ]);

        return $version;
    }
}
