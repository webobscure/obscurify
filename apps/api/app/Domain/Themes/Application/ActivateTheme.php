<?php

namespace App\Domain\Themes\Application;

use App\Domain\Themes\Models\ActiveTheme;
use App\Domain\Themes\Models\Theme;
use App\Domain\Themes\Models\ThemeVersion;
use Illuminate\Validation\ValidationException;

/**
 * Points the store's single ActiveTheme row at a specific, already-
 * published version — never a draft (visitors must never see
 * unpublished work, spec section 11). Used by PublishThemeVersion (the
 * happy path) and RollbackTheme (picking an older published version).
 */
final class ActivateTheme
{
    public function handle(Theme $theme, ThemeVersion $version): ActiveTheme
    {
        if ($version->status->value !== 'published') {
            throw ValidationException::withMessages(['version' => 'Only a published version can be activated.']);
        }

        return ActiveTheme::query()->updateOrCreate(
            [],
            ['theme_id' => $theme->id, 'theme_version_id' => $version->id, 'activated_at' => now()],
        );
    }
}
