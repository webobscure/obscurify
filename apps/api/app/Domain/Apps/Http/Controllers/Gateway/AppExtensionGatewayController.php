<?php

namespace App\Domain\Apps\Http\Controllers\Gateway;

use App\Domain\Apps\Enums\AppStatus;
use App\Domain\Apps\Enums\ExtensionPoint;
use App\Domain\Apps\Http\Resources\AppExtensionResource;
use App\Domain\Apps\Models\AppExtension;
use App\Domain\Apps\Support\CurrentAppContext;
use App\Domain\Apps\Support\ExtensionPointRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rules\Enum;

/**
 * An installed app's own extension-point contributions (spec sections
 * 8/9) — registration only requires a valid app token, no specific
 * scope, since it configures the app's own presence rather than reading
 * store data.
 */
final class AppExtensionGatewayController extends Controller
{
    public function __construct(private readonly CurrentAppContext $currentAppContext) {}

    public function index(): AnonymousResourceCollection
    {
        $extensions = AppExtension::query()
            ->where('installed_app_id', $this->currentAppContext->installedApp()->id)
            ->get();

        return AppExtensionResource::collection($extensions);
    }

    public function store(Request $request): AppExtensionResource
    {
        $data = $request->validate([
            'extension_point' => ['required', new Enum(ExtensionPoint::class)],
            'config' => ['required', 'array'],
        ]);

        $point = ExtensionPoint::from($data['extension_point']);
        ExtensionPointRegistry::assertValidConfig($point, $data['config']);

        $extension = AppExtension::query()->create([
            'installed_app_id' => $this->currentAppContext->installedApp()->id,
            'extension_point' => $point->value,
            'config' => $data['config'],
            'status' => AppStatus::Active->value,
        ]);

        return new AppExtensionResource($extension);
    }
}
