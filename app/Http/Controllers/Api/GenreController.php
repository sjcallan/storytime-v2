<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGenreRequest;
use App\Http\Requests\UpdateGenreRequest;
use App\Models\Genre;
use Illuminate\Http\JsonResponse;

class GenreController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $genres = Genre::query()->latest()->get();

        return response()->json($genres);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGenreRequest $request): JsonResponse
    {
        $genre = Genre::create($request->validated());

        return response()->json($genre, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Genre $genre): JsonResponse
    {
        return response()->json($genre);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGenreRequest $request, Genre $genre): JsonResponse
    {
        $genre->update($request->validated());

        return response()->json($genre);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Genre $genre): JsonResponse
    {
        $genre->delete();

        return response()->json(null, 204);
    }
}
