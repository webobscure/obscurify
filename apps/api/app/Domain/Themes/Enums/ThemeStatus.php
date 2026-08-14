<?php

namespace App\Domain\Themes\Enums;

enum ThemeStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
