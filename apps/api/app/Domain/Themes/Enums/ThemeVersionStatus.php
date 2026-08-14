<?php

namespace App\Domain\Themes\Enums;

enum ThemeVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
