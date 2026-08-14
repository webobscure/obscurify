<?php

namespace App\Domain\Cms\Enums;

enum BlogPostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Scheduled = 'scheduled';
    case Archived = 'archived';
}
