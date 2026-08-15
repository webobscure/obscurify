<?php

namespace App\Domain\Analytics\Http\Controllers;

use App\Domain\Analytics\Http\Resources\MetricDefinitionResource;
use App\Domain\Analytics\Models\MetricDefinition;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Backs the Widget Builder / Report Builder's metric picker (spec
 * section 10).
 */
final class MetricDefinitionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return MetricDefinitionResource::collection(MetricDefinition::query()->orderBy('category')->orderBy('label')->get());
    }
}
