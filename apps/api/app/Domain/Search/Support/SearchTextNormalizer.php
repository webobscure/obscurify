<?php

namespace App\Domain\Search\Support;

/**
 * The one function both indexing (SearchDocument.search_text) and
 * querying (a customer's typed-in query) run through, so a stored
 * "Café" and a typed "cafe" match identically — case-insensitive and
 * accent-insensitive by construction, since both sides are normalized
 * the same way before ever being compared (spec section 5).
 *
 * `transliterate()` is deliberately its own step, separate from accent
 * stripping — today it's a no-op beyond what `normalize()` already
 * does, but it's the one seam a future script-to-script mapping
 * (Cyrillic to Latin, Greek to Latin, ...) plugs into without touching
 * every caller (spec: "Transliteration-ready architecture").
 */
final class SearchTextNormalizer
{
    public function normalize(string $text): string
    {
        return $this->transliterate($this->stripAccents(mb_strtolower(trim($text))));
    }

    /**
     * @return list<string>
     */
    public function tokenize(string $text): array
    {
        $normalized = $this->normalize($text);
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        return $parts === false ? [] : $parts;
    }

    private function stripAccents(string $text): string
    {
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return $transliterated === false ? $text : $transliterated;
    }

    /**
     * No-op today — see class docblock. A future locale-specific
     * transliteration table (e.g. Cyrillic -> Latin) hooks in here.
     */
    private function transliterate(string $text): string
    {
        return $text;
    }
}
