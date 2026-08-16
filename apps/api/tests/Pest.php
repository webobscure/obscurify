<?php

use App\Domain\Domains\Models\Domain;
use App\Domain\Stores\Enums\StoreUserRole;
use App\Domain\Stores\Enums\StoreUserStatus;
use App\Domain\Stores\Models\Store;
use App\Domain\Stores\Models\StoreUser;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Concurrency tests need rows genuinely committed so a second, independent
 * database connection can see and contend for their locks — RefreshDatabase
 * wraps each test in an uncommitted transaction, which would make fixtures
 * invisible to that second connection. These tests manage their own
 * cleanup instead of relying on a rollback.
 */
pest()->extend(TestCase::class)->in('Concurrency');

/**
 * Creates a Store owned by the given user, with an active owner membership
 * already attached — the same invariant CreateStore enforces atomically.
 */
function createStoreForUser(User $user, array $overrides = [], StoreUserRole $role = StoreUserRole::Owner): Store
{
    $store = Store::factory()->create([
        'owner_id' => $user->id,
        ...$overrides,
    ]);

    $membership = new StoreUser([
        'role' => $role,
        'status' => StoreUserStatus::Active,
    ]);
    $membership->store_id = $store->id;
    $membership->user_id = $user->id;
    $membership->save();

    return $store;
}

/**
 * Header used to select the active tenant on merchant-admin requests.
 */
function tenantHeader(Store $store): array
{
    return ['X-Store-Id' => $store->id];
}

/**
 * Attaches a primary custom domain to a store, so storefront requests
 * can resolve the tenant purely from the Host header (no admin session,
 * no X-Store-Id).
 */
function domainForStore(Store $store, string $host, array $overrides = []): Domain
{
    return app(TenantContext::class)->scope(
        $store,
        fn () => Domain::query()->create(['domain' => $host, 'type' => 'primary', 'is_primary' => true, ...$overrides]),
    );
}

/**
 * Builds an absolute URL with the given host so Symfony's Request::create()
 * derives HTTP_HOST from the URI itself — passing a bare 'Host' header
 * does NOT work here: Laravel's test client always resolves relative URIs
 * against an absolute base URL first, and Symfony\Component\HttpFoundation\Request::create()
 * unconditionally overwrites HTTP_HOST from any host present in the URI
 * it's given (see Request::create(), the `isset($components['host'])`
 * branch) — so the only reliable way to control the effective request
 * host in a test is to make the URI itself carry it.
 */
function storefrontUrl(string $host, string $path): string
{
    return "http://{$host}{$path}";
}

/**
 * POSTs a GraphQL document to the single public endpoint (Milestone
 * 23) — `$host` resolves the storefront tenant exactly like every
 * other storefront request (see EnsureStorefrontTenantContext); pass
 * `tenantHeader($store)` in `$headers` instead for a merchant-authenticated
 * request (GraphQLAuthenticator prefers the Sanctum guard over hostname
 * resolution — see that class's own docblock).
 *
 * @param  array<string, mixed>  $variables
 * @param  array<string, string>  $headers
 */
function graphqlRequest(string $host, string $query, array $variables = [], array $headers = []): TestResponse
{
    return test()->postJson(storefrontUrl($host, '/api/graphql'), array_filter([
        'query' => $query,
        'variables' => $variables,
    ], fn ($v) => $v !== []), $headers);
}

/**
 * Bearer-auth header for a customer-portal request authenticated by a
 * CustomerAccessToken (see AuthenticateCustomerToken) — shared across
 * every tests/Feature/Customers test.
 */
function authHeader(string $token): array
{
    return ['Authorization' => "Bearer {$token}"];
}

/**
 * Runs each of the given callables in its own forked OS process, released
 * from a shared starting gate at nearly the same instant, so they
 * genuinely race for the same Postgres row locks — a single PHP process
 * is strictly single-threaded and cannot produce real concurrency, so
 * this is the only way to observe what actually happens when two
 * independent backend connections contend for the same lock, as opposed
 * to simulating it sequentially.
 *
 * Every fixture row the callables depend on must already be committed
 * before calling this (see tests/Concurrency's Pest.php registration:
 * no RefreshDatabase there, since a forked child needs its own database
 * connection and must see genuinely committed rows). Each child purges
 * any inherited connection and reconnects fresh — sharing a live
 * Postgres socket across processes corrupts the protocol stream.
 *
 * @param  array<callable(): mixed>  $callables
 * @return array<int, array{ok: bool, value?: mixed, error?: string}> one result per callable, in input order
 */
function runConcurrently(array $callables): array
{
    DB::purge();

    $children = [];

    foreach ($callables as $index => $callable) {
        $startPair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $resultPair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($startPair === false || $resultPair === false) {
            throw new RuntimeException('stream_socket_pair failed');
        }

        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('pcntl_fork failed');
        }

        if ($pid === 0) {
            fclose($startPair[1]);
            fclose($resultPair[0]);

            DB::purge();

            fread($startPair[0], 1);

            try {
                $value = $callable();
                $payload = json_encode(['ok' => true, 'value' => $value]);
            } catch (Throwable $e) {
                $payload = json_encode(['ok' => false, 'error' => $e->getMessage()]);
            }

            fwrite($resultPair[1], $payload === false ? '{"ok":false,"error":"unserializable result"}' : $payload);
            fclose($resultPair[1]);
            fclose($startPair[0]);

            exit(0);
        }

        fclose($startPair[0]);
        fclose($resultPair[1]);

        $children[$index] = ['pid' => $pid, 'start' => $startPair[1], 'result' => $resultPair[0]];
    }

    foreach ($children as $child) {
        fwrite($child['start'], 'g');
        fclose($child['start']);
    }

    $results = [];

    foreach ($children as $index => $child) {
        $raw = stream_get_contents($child['result']);
        fclose($child['result']);
        pcntl_waitpid($child['pid'], $status);

        $results[$index] = json_decode($raw, true) ?? ['ok' => false, 'error' => 'no output from child process'];
    }

    ksort($results);

    return $results;
}
