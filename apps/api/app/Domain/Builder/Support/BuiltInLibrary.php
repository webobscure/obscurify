<?php

namespace App\Domain\Builder\Support;

/**
 * The built-in Section Library / Block Library (spec sections 5/6) — a
 * plain array catalog, not a hard enum (the same "easy to add a new
 * one" reasoning AppScope::known() already established for OAuth
 * scopes), of every section/block *type* a freshly created theme is
 * seeded with (see SeedBuilderLibrary). Each entry's `schema` is the
 * exact field-definition shape ThemeSection.schema/ThemeBlock.schema
 * already use (`{id, type, label, default}`) — nothing new for
 * ThemeRenderer to interpret, since defaultSettings() and the section/
 * block merge logic (ThemeRenderer::resolveSection()) already read
 * this shape.
 */
final class BuiltInLibrary
{
    /**
     * @return array<int, array{handle: string, name: string, schema: array<int, array<string, mixed>>}>
     */
    public static function sections(): array
    {
        return [
            ['handle' => 'hero', 'name' => 'Hero', 'schema' => [
                ['id' => 'heading', 'type' => 'text', 'label' => 'Heading', 'default' => 'Welcome to our store'],
                ['id' => 'subheading', 'type' => 'text', 'label' => 'Subheading', 'default' => ''],
                ['id' => 'image', 'type' => 'image', 'label' => 'Background image', 'default' => null],
                ['id' => 'button_label', 'type' => 'text', 'label' => 'Button label', 'default' => ''],
                ['id' => 'button_url', 'type' => 'url', 'label' => 'Button link', 'default' => ''],
            ]],
            ['handle' => 'image-banner', 'name' => 'Image Banner', 'schema' => [
                ['id' => 'image', 'type' => 'image', 'label' => 'Image', 'default' => null],
                ['id' => 'caption', 'type' => 'text', 'label' => 'Caption', 'default' => ''],
                ['id' => 'url', 'type' => 'url', 'label' => 'Link', 'default' => ''],
            ]],
            ['handle' => 'featured-products', 'name' => 'Featured Products', 'schema' => [
                ['id' => 'heading', 'type' => 'text', 'label' => 'Heading', 'default' => 'Featured products'],
                ['id' => 'collection_id', 'type' => 'collection', 'label' => 'Collection', 'default' => null],
                ['id' => 'product_count', 'type' => 'number', 'label' => 'Products to show', 'default' => 4],
            ]],
            ['handle' => 'featured-collections', 'name' => 'Featured Collections', 'schema' => [
                ['id' => 'heading', 'type' => 'text', 'label' => 'Heading', 'default' => 'Shop by collection'],
            ]],
            ['handle' => 'testimonials', 'name' => 'Testimonials', 'schema' => [
                ['id' => 'heading', 'type' => 'text', 'label' => 'Heading', 'default' => 'What customers say'],
            ]],
            ['handle' => 'faq', 'name' => 'FAQ', 'schema' => [
                ['id' => 'heading', 'type' => 'text', 'label' => 'Heading', 'default' => 'Frequently asked questions'],
            ]],
            ['handle' => 'newsletter', 'name' => 'Newsletter', 'schema' => [
                ['id' => 'heading', 'type' => 'text', 'label' => 'Heading', 'default' => 'Subscribe to our newsletter'],
                ['id' => 'button_label', 'type' => 'text', 'label' => 'Button label', 'default' => 'Subscribe'],
            ]],
            ['handle' => 'rich-text', 'name' => 'Rich Text', 'schema' => [
                ['id' => 'content', 'type' => 'richtext', 'label' => 'Content', 'default' => ''],
            ]],
            ['handle' => 'video', 'name' => 'Video', 'schema' => [
                ['id' => 'video_url', 'type' => 'video', 'label' => 'Video', 'default' => null],
                ['id' => 'caption', 'type' => 'text', 'label' => 'Caption', 'default' => ''],
            ]],
            ['handle' => 'gallery', 'name' => 'Gallery', 'schema' => [
                ['id' => 'heading', 'type' => 'text', 'label' => 'Heading', 'default' => ''],
            ]],
            ['handle' => 'countdown', 'name' => 'Countdown', 'schema' => [
                ['id' => 'heading', 'type' => 'text', 'label' => 'Heading', 'default' => 'Sale ends soon'],
                ['id' => 'end_at', 'type' => 'datetime', 'label' => 'Ends at', 'default' => null],
            ]],
            ['handle' => 'icons-grid', 'name' => 'Icons Grid', 'schema' => [
                ['id' => 'heading', 'type' => 'text', 'label' => 'Heading', 'default' => ''],
            ]],
        ];
    }

    /**
     * @return array<int, array{handle: string, name: string, schema: array<int, array<string, mixed>>}>
     */
    public static function blocks(): array
    {
        return [
            ['handle' => 'heading', 'name' => 'Heading', 'schema' => [
                ['id' => 'text', 'type' => 'text', 'label' => 'Text', 'default' => 'Heading'],
                ['id' => 'level', 'type' => 'select', 'label' => 'Level', 'default' => 'h2'],
            ]],
            ['handle' => 'paragraph', 'name' => 'Paragraph', 'schema' => [
                ['id' => 'text', 'type' => 'richtext', 'label' => 'Text', 'default' => ''],
            ]],
            ['handle' => 'button', 'name' => 'Button', 'schema' => [
                ['id' => 'label', 'type' => 'text', 'label' => 'Label', 'default' => 'Shop now'],
                ['id' => 'url', 'type' => 'url', 'label' => 'Link', 'default' => ''],
            ]],
            ['handle' => 'image', 'name' => 'Image', 'schema' => [
                ['id' => 'image', 'type' => 'image', 'label' => 'Image', 'default' => null],
                ['id' => 'alt', 'type' => 'text', 'label' => 'Alt text', 'default' => ''],
            ]],
            ['handle' => 'video', 'name' => 'Video', 'schema' => [
                ['id' => 'video_url', 'type' => 'video', 'label' => 'Video', 'default' => null],
            ]],
            ['handle' => 'icon', 'name' => 'Icon', 'schema' => [
                ['id' => 'icon', 'type' => 'icon', 'label' => 'Icon', 'default' => null],
            ]],
            ['handle' => 'divider', 'name' => 'Divider', 'schema' => []],
            ['handle' => 'spacer', 'name' => 'Spacer', 'schema' => [
                ['id' => 'height', 'type' => 'number', 'label' => 'Height (px)', 'default' => 40],
            ]],
            ['handle' => 'accordion', 'name' => 'Accordion', 'schema' => [
                ['id' => 'title', 'type' => 'text', 'label' => 'Title', 'default' => ''],
            ]],
            ['handle' => 'tabs', 'name' => 'Tabs', 'schema' => [
                ['id' => 'label', 'type' => 'text', 'label' => 'Tab label', 'default' => ''],
            ]],
            ['handle' => 'slider', 'name' => 'Slider', 'schema' => []],
            ['handle' => 'product-card', 'name' => 'Product Card', 'schema' => [
                ['id' => 'product_id', 'type' => 'product', 'label' => 'Product', 'default' => null],
            ]],
            ['handle' => 'collection-card', 'name' => 'Collection Card', 'schema' => [
                ['id' => 'collection_id', 'type' => 'collection', 'label' => 'Collection', 'default' => null],
            ]],
        ];
    }
}
