<?php

namespace App\Domain\Builder\Support;

/**
 * The known Theme Customizer fields (spec section 4: Logo, Typography,
 * Primary/Secondary colors, Buttons, Border radius, Spacing, Container
 * width, Header, Footer, Announcement bar, Social links, Favicon) — a
 * plain list, not a hard enum, the same "easy to extend" reasoning as
 * BuiltInLibrary. Every field is just a `ThemeSetting` row under one of
 * these keys; nothing new to store or render — ThemeRenderer already
 * returns every ThemeSetting as `globalSettings` on every rendered page
 * (see ThemeRenderer::renderVersion()), so a storefront theme simply
 * reads e.g. `globalSettings.primary_color` the same way it already
 * reads any other setting. This schema exists purely so the admin UI
 * can render an appropriate input per field (color picker, font
 * select, a range slider for spacing) instead of one raw JSON textarea
 * — Milestone 13's own settings editor is exactly that raw-JSON
 * fallback, still available for anything not in this known list.
 */
final class ThemeCustomizerSchema
{
    /**
     * @return array<int, array{key: string, label: string, type: string, group: string}>
     */
    public static function fields(): array
    {
        return [
            ['key' => 'logo', 'label' => 'Logo', 'type' => 'image', 'group' => 'Branding'],
            ['key' => 'favicon', 'label' => 'Favicon', 'type' => 'image', 'group' => 'Branding'],
            ['key' => 'typography_heading_font', 'label' => 'Heading font', 'type' => 'font', 'group' => 'Typography'],
            ['key' => 'typography_body_font', 'label' => 'Body font', 'type' => 'font', 'group' => 'Typography'],
            ['key' => 'color_primary', 'label' => 'Primary color', 'type' => 'color', 'group' => 'Colors'],
            ['key' => 'color_secondary', 'label' => 'Secondary color', 'type' => 'color', 'group' => 'Colors'],
            ['key' => 'button_style', 'label' => 'Button style', 'type' => 'select', 'group' => 'Buttons'],
            ['key' => 'button_border_radius', 'label' => 'Button border radius', 'type' => 'range', 'group' => 'Buttons'],
            ['key' => 'border_radius', 'label' => 'Border radius', 'type' => 'range', 'group' => 'Layout'],
            ['key' => 'spacing', 'label' => 'Spacing scale', 'type' => 'range', 'group' => 'Layout'],
            ['key' => 'container_width', 'label' => 'Container width', 'type' => 'range', 'group' => 'Layout'],
            ['key' => 'header_layout', 'label' => 'Header layout', 'type' => 'select', 'group' => 'Header'],
            ['key' => 'header_sticky', 'label' => 'Sticky header', 'type' => 'boolean', 'group' => 'Header'],
            ['key' => 'footer_text', 'label' => 'Footer text', 'type' => 'richtext', 'group' => 'Footer'],
            ['key' => 'announcement_bar_text', 'label' => 'Announcement text', 'type' => 'text', 'group' => 'Announcement bar'],
            ['key' => 'announcement_bar_enabled', 'label' => 'Show announcement bar', 'type' => 'boolean', 'group' => 'Announcement bar'],
            ['key' => 'social_facebook', 'label' => 'Facebook URL', 'type' => 'url', 'group' => 'Social links'],
            ['key' => 'social_instagram', 'label' => 'Instagram URL', 'type' => 'url', 'group' => 'Social links'],
            ['key' => 'social_twitter', 'label' => 'Twitter/X URL', 'type' => 'url', 'group' => 'Social links'],
        ];
    }
}
