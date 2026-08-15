<?php

namespace App\Domain\Automation\Enums;

enum WorkflowVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
