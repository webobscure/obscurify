<?php

namespace App\Domain\Builder\Http\Controllers;

use App\Domain\Builder\Http\Requests\StoreThemeAssetRequest;
use App\Domain\Builder\Http\Resources\ThemeAssetResource;
use App\Domain\Themes\Models\ThemeAsset;
use App\Domain\Themes\Models\ThemeVersion;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Upload/list/delete for ThemeAsset — the model, migration, and
 * `BelongsToTenant` scoping existed since Milestone 13, but no
 * controller ever exposed them (flagged as a known gap in
 * docs/architecture/themes.md §8). The visual Builder's asset picker
 * (spec section 10: "Allow selecting: Images, Videos, Icons,
 * Backgrounds, Uploaded files") is what actually needs this now.
 */
final class ThemeAssetController extends Controller
{
    public function index(ThemeVersion $themeVersion): AnonymousResourceCollection
    {
        return ThemeAssetResource::collection(
            $themeVersion->assets()->orderByDesc('created_at')->get(),
        );
    }

    public function store(StoreThemeAssetRequest $request, ThemeVersion $themeVersion): ThemeAssetResource
    {
        $file = $request->file('file');
        $path = $file->store('theme-assets/'.$themeVersion->id, 'public');

        $asset = $themeVersion->assets()->create([
            'type' => $request->validated('type'),
            'path' => $path,
            'disk' => 'public',
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ]);

        return new ThemeAssetResource($asset);
    }

    public function destroy(ThemeAsset $themeAsset): Response
    {
        Storage::disk($themeAsset->disk)->delete($themeAsset->path);
        $themeAsset->delete();

        return response()->noContent();
    }
}
