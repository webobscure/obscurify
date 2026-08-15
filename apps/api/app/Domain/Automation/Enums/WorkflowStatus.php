<?php

namespace App\Domain\Automation\Enums;

enum WorkflowStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Disabled = 'disabled';
    case Archived = 'archived';
}
