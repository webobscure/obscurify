<?php

namespace App\Domain\Apps\Http\Controllers;

use App\Domain\Apps\Enums\AppStatus;
use App\Domain\Apps\Enums\ExtensionPoint;
use App\Domain\Apps\Http\Resources\AppExtensionResource;
use App\Domain\Apps\Models\AppExtension;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * What the admin UI actually reads to render app-contributed menu
 * items/widgets/dashboard cards (spec section 9: "Do not hardcode menu
 * changes") — `?point=admin_navigation` filters to one ExtensionPoint;
 * omitted, returns every active contribution across all points.
 * Store-scoped by AppExtension's BelongsToTenant global scope, the same
 * as every other admin-facing list.
 */
final class AdminExtensionsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $extensions = AppExtension::query()
            ->where('status', AppStatus::Active->value)
            ->when($request->query('point'), function ($query, $point) {
                ExtensionPoint::from($point);
                $query->where('extension_point', $point);
            })
            ->with('installedApp.app')
            ->get();

        return AppExtensionResource::collection($extensions);
    }
}
