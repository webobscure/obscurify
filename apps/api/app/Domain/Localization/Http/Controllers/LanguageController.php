<?php

namespace App\Domain\Localization\Http\Controllers;

use App\Domain\Localization\Http\Resources\LanguageResource;
use App\Domain\Localization\Models\Language;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The platform-wide catalog (spec section 2) — every store reads the
 * same list; there is no per-store variant of this endpoint. Backs the
 * language-switcher options in both Admin and Storefront, and the
 * choices offered on the Store's own language-settings page.
 */
final class LanguageController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return LanguageResource::collection(
            Language::query()->where('is_active', true)->orderBy('sort_order')->get(),
        );
    }
}
