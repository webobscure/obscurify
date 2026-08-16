<?php

namespace App\Domain\GraphQL\Support;

use GraphQL\Deferred;

/**
 * Generic per-request batching loader (spec section 6: "Prevent N+1
 * queries") — the standard webonyx `Deferred` pattern: `load()` never
 * fetches immediately, it only records the id and hands back a
 * `Deferred`. webonyx's executor resolves an entire selection set's
 * sibling fields before draining its promise queue, so by the time the
 * *first* Deferred's executor callback actually runs, every sibling
 * field's `load()` call for this loader has already registered its id —
 * the first callback to run fetches the whole batch in one query and
 * populates the shared cache; every other callback for this batch then
 * just reads the cache, dispatching nothing further.
 *
 * One instance per entity type, constructed fresh per GraphQL request
 * (see GraphQLServiceProvider) — the cache must never survive across
 * requests/tenants.
 *
 * @template TValue
 */
final class DataLoader
{
    /** @var array<string, true> */
    private array $pending = [];

    /** @var array<string, TValue|null> */
    private array $cache = [];

    /**
     * @param  callable(list<string>): array<string, TValue>  $batchLoad  Given ids, returns a map of id => value for the ones found.
     */
    public function __construct(private readonly mixed $batchLoad) {}

    public function load(string $id): Deferred
    {
        if (! array_key_exists($id, $this->cache)) {
            $this->pending[$id] = true;
        }

        return new Deferred(function () use ($id) {
            $this->dispatch();

            return $this->cache[$id] ?? null;
        });
    }

    /**
     * @param  list<string>  $ids
     * @return Deferred resolving to a list<TValue|null> in the same order as $ids.
     */
    public function loadMany(array $ids): Deferred
    {
        foreach ($ids as $id) {
            if (! array_key_exists($id, $this->cache)) {
                $this->pending[$id] = true;
            }
        }

        return new Deferred(function () use ($ids) {
            $this->dispatch();

            return array_map(fn (string $id) => $this->cache[$id] ?? null, $ids);
        });
    }

    private function dispatch(): void
    {
        if ($this->pending === []) {
            return;
        }

        $ids = array_keys($this->pending);
        $this->pending = [];

        $results = ($this->batchLoad)($ids);

        foreach ($ids as $id) {
            $this->cache[$id] = $results[$id] ?? null;
        }
    }
}
