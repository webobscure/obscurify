<?php

namespace App\Domain\Cms\Http\Controllers;

use App\Domain\Cms\Http\Requests\StoreRedirectRequest;
use App\Domain\Cms\Http\Requests\UpdateRedirectRequest;
use App\Domain\Cms\Http\Resources\RedirectResource;
use App\Domain\Cms\Models\Redirect;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class RedirectController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return RedirectResource::collection(Redirect::query()->orderByDesc('created_at')->get());
    }

    public function store(StoreRedirectRequest $request): RedirectResource
    {
        // The DB column default (301) never reflects back onto the
        // in-memory model after a plain insert — set it explicitly so
        // the response actually carries the value the row got.
        $data = $request->validated();
        $data['status_code'] ??= 301;

        return new RedirectResource(Redirect::query()->create($data));
    }

    public function update(UpdateRedirectRequest $request, Redirect $redirect): RedirectResource
    {
        $redirect->update($request->validated());

        return new RedirectResource($redirect);
    }

    public function destroy(Redirect $redirect): Response
    {
        $redirect->delete();

        return response()->noContent();
    }
}
