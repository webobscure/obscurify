<?php

namespace App\Domain\Themes\Support;

/**
 * ThemeRenderer's sole output — everything the storefront needs to draw
 * one page, and nothing it needs to compute itself (spec section 9:
 * "Resolve active theme, resolve template, load sections, merge
 * settings, render storefront").
 */
final readonly class RenderedPage
{
    /**
     * @param  array<int, RenderedSection>  $sections
     * @param  array<string, mixed>  $globalSettings
     */
    public function __construct(
        public string $template,
        public array $sections,
        public array $globalSettings,
        public string $themeId,
        public string $themeVersionId,
        public bool $isPreview,
    ) {}
}
