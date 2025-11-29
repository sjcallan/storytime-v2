<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChapterRequest;
use App\Http\Requests\UpdateChapterRequest;
use App\Models\Chapter;
use Illuminate\Http\JsonResponse;

class ChapterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $chapters = Chapter::query()
            ->with(['user', 'book', 'characters'])
            ->where('user_id', auth()->id())
            ->orderBy('sort')
            ->get();

        return response()->json($chapters);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreChapterRequest $request): JsonResponse
    {
        $chapter = Chapter::create([
            ...$request->validated(),
            'user_id' => auth()->id(),
        ]);

        $chapter->load(['user', 'book', 'characters']);

        return response()->json($chapter, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Chapter $chapter): JsonResponse
    {
        $this->authorize('view', $chapter);

        $chapter->load(['user', 'book', 'characters']);

        return response()->json($chapter);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateChapterRequest $request, Chapter $chapter): JsonResponse
    {
        $this->authorize('update', $chapter);

        $chapter->update($request->validated());

        $chapter->load(['user', 'book', 'characters']);

        return response()->json($chapter);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Chapter $chapter): JsonResponse
    {
        $this->authorize('delete', $chapter);

        $chapter->delete();

        return response()->json(null, 204);
    }
}
