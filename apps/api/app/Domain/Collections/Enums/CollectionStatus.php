<?php

namespace App\Domain\Collections\Enums;

enum CollectionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
