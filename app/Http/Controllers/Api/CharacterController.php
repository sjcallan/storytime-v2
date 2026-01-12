<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCharacterRequest;
use App\Http\Requests\UpdateCharacterRequest;
use App\Jobs\Image\GenerateImageJob;
use App\Models\Character;
use App\Services\Image\ImageService;
use Illuminate\Http\JsonResponse;

class CharacterController extends Controller
{
    public function __construct(protected ImageService $imageService) {}

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
     * Always creates a new Image record to preserve history.
     */
    public function regeneratePortrait(Character $character): JsonResponse
    {
        $this->authorize('update', $character);

        // Check for existing portrait image to copy prompt from
        $existingImage = $this->imageService->getCharacterPortrait($character->id);

        // Always create a new image record
        $image = $this->imageService->createCharacterPortraitImage(
            $character,
            $existingImage?->prompt
        );

        // Update character's portrait_image_id to point to new image
        $character->update(['portrait_image_id' => $image->id]);

        // Dispatch the generation job
        GenerateImageJob::dispatch($image)->onQueue('images');

        return response()->json([
            'message' => 'Portrait regeneration started',
            'id' => $character->id,
            'image' => [
                'id' => $image->id,
                'status' => $image->status->value,
            ],
        ]);
    }
}
