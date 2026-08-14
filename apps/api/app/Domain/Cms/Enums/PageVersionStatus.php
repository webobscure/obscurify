<?php

namespace App\Domain\Cms\Enums;

enum PageVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
