<?php

namespace App\Domain\Storefront\Http\Controllers;

use App\Domain\Themes\Enums\ThemeTemplateType;
use App\Domain\Themes\Support\ThemeRenderer;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * The one endpoint the Nuxt storefront calls to render *any* themed
 * page (spec section 10: "Nuxt storefront must render through
 * ThemeRenderer... do not hardcode pages"). Always resolves the
 * store's *active* theme — this route sits behind `storefront.tenant`,
 * never `auth:sanctum` (see routes/api.php), so there is no merchant
 * session here that could request a preview. Draft/preview rendering
 * is a separate, admin-only endpoint (`ThemeController::preview`) —
 * spec section 11: "Visitors always see the active theme."
 */
final class StorefrontThemeController extends Controller
{
    public function render(string $template, ThemeRenderer $renderer, TenantContext $tenantContext): JsonResponse
    {
        $type = ThemeTemplateType::tryFrom($template);

        if ($type === null) {
            throw ValidationException::withMessages(['template' => 'Unknown template type.']);
        }

        $page = $renderer->render($tenantContext->storeId(), $type);

        return response()->json(['data' => $page]);
    }
}
