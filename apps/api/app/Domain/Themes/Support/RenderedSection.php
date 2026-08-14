<?php

namespace App\Domain\Themes\Support;

/**
 * One section instance, fully resolved: schema defaults merged with
 * whatever the merchant actually configured, blocks resolved the same
 * way. This is what the storefront's generic section renderer consumes
 * — it never sees a schema or a raw instance override separately.
 */
final readonly class RenderedSection
{
    /**
     * @param  array<string, mixed>  $settings
     * @param  array<int, array{id: string|null, handle: string, settings: array<string, mixed>, blocks: array<int, mixed>}>  $blocks
     *                                                                                                                                 `blocks` nests recursively (Milestone 15: a block may contain
     *                                                                                                                                 child blocks), so the innermost level is left as `mixed` rather
     *                                                                                                                                 than a self-referencing docblock type PHPStan can't express.
     */
    public function __construct(
        public ?string $id,
        public string $handle,
        public string $name,
        public array $settings,
        public array $blocks,
    ) {}
}
