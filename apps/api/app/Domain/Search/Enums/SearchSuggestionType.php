<?php

namespace App\Domain\Search\Enums;

enum SearchSuggestionType: string
{
    case Query = 'query';
    case Product = 'product';
    case Collection = 'collection';
    case Category = 'category';
}
