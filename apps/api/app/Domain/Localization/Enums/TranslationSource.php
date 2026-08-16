<?php

namespace App\Domain\Localization\Enums;

/**
 * How a Translation index row was populated — spec section 1 lists
 * "TranslationSource" as a core entity; kept as an enum column on
 * Translation rather than its own table (like FiscalReceiptOperation,
 * not like SearchProvider) since it's a fixed, non-store-configurable
 * classification with no other attributes of its own.
 */
enum TranslationSource: string
{
    /** Found by `translations:scan` walking a `lang/*.php` file or frontend locale JSON bundle. */
    case Scan = 'scan';

    /** Seeded by `translations:sync-languages`/migrations at install time. */
    case Seed = 'seed';

    /** Edited directly through a future admin translation-editing UI (not built this milestone). */
    case Manual = 'manual';
}
