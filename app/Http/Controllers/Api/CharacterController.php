<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCharacterRequest;
use App\Http\Requests\UpdateCharacterRequest;
use App\Jobs\Character\CreateCharacterPortraitJob;
use App\Models\Character;
use Illuminate\Http\JsonResponse;

class CharacterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $characters = Character::query()
            ->with(['user', 'book', 'chapters'])
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return response()->json($characters);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCharacterRequest $request): JsonResponse
    {
        $character = Character::create([
            ...$request->validated(),
            'user_id' => auth()->id(),
        ]);

        $character->load(['user', 'book', 'chapters']);

        return response()->json($character, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Character $character): JsonResponse
    {
        $this->authorize('view', $character);

        $character->load(['user', 'book', 'chapters']);

        return response()->json($character);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCharacterRequest $request, Character $character): JsonResponse
    {
        $this->authorize('update', $character);

        $character->update($request->validated());

        $character->load(['user', 'book', 'chapters']);

        return response()->json($character);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Character $character): JsonResponse
    {
        $this->authorize('delete', $character);

        $character->delete();

        return response()->json(null, 204);
    }

    /**
     * Regenerate the character's portrait image.
     */
    public function regeneratePortrait(Character $character): JsonResponse
    {
        $this->authorize('update', $character);

        $character->update(['portrait_image' => null]);

        CreateCharacterPortraitJob::dispatch($character)->onQueue('images');

        return response()->json([
            'message' => 'Portrait regeneration started',
            'id' => $character->id,
        ]);
    }
}
