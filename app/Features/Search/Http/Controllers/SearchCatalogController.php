<?php

declare(strict_types=1);

namespace App\Features\Search\Http\Controllers;

use App\Features\Search\Actions\SearchCatalog;
use App\Features\Search\Http\Requests\SearchRequest;
use App\Features\Search\Http\Resources\SearchResultResource;
use Illuminate\Http\JsonResponse;

final class SearchCatalogController
{
    public function __invoke(SearchRequest $request, SearchCatalog $search): JsonResponse
    {
        return response()->json(SearchResultResource::collection($search($request->searchData()))->resolve());
    }
}
