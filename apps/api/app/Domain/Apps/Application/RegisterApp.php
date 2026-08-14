<?php

namespace App\Domain\Apps\Application;

use App\Domain\Apps\Enums\AppStatus;
use App\Domain\Apps\Enums\AppType;
use App\Domain\Apps\Exceptions\OAuthErrorException;
use App\Domain\Apps\Models\App;
use App\Domain\Apps\Models\OAuthClient;
use App\Domain\Apps\Support\AppScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates an App and its 1:1 OAuthClient together. A Private App is
 * created by (and scoped to) the active store's TenantContext; a Public
 * App has no owning store (spec section 2: "internal support only" —
 * created by platform staff, not exposed as a public self-serve flow).
 * The client secret is generated here, returned once by the controller,
 * and never stored plaintext (only its SHA-256 hash).
 */
final class RegisterApp
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{app: App, client_secret: string}
     */
    public function handle(array $data, ?string $storeId): array
    {
        $requestedScopes = $data['requested_scopes'] ?? [];

        foreach ($requestedScopes as $scope) {
            if (! AppScope::isKnown($scope)) {
                throw OAuthErrorException::invalidScope("Unknown scope: {$scope}");
            }
        }

        $type = $data['type'] ?? AppType::Private->value;

        return DB::transaction(function () use ($data, $storeId, $requestedScopes, $type) {
            $app = App::query()->create([
                'store_id' => $type === AppType::Public->value ? null : $storeId,
                'type' => $type,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'developer' => $data['developer'] ?? null,
                'description' => $data['description'] ?? null,
                'redirect_urls' => $data['redirect_urls'],
                'requested_scopes' => $requestedScopes,
                'status' => AppStatus::Active->value,
            ]);

            $clientSecret = Str::random(48);

            OAuthClient::query()->create([
                'app_id' => $app->id,
                'client_id' => (string) Str::ulid(),
                'client_secret_hash' => Hash::make($clientSecret),
            ]);

            return ['app' => $app, 'client_secret' => $clientSecret];
        });
    }
}
