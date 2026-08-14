<?php

namespace App\Domain\Cms\Http\Controllers;

use App\Domain\Cms\Http\Requests\StoreAuthorRequest;
use App\Domain\Cms\Http\Requests\UpdateAuthorRequest;
use App\Domain\Cms\Http\Resources\AuthorResource;
use App\Domain\Cms\Models\Author;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class AuthorController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AuthorResource::collection(Author::query()->orderBy('name')->get());
    }

    public function store(StoreAuthorRequest $request): AuthorResource
    {
        return new AuthorResource(Author::query()->create($request->validated()));
    }

    public function update(UpdateAuthorRequest $request, Author $author): AuthorResource
    {
        $author->update($request->validated());

        return new AuthorResource($author);
    }

    public function destroy(Author $author): Response
    {
        $author->delete();

        return response()->noContent();
    }
}
