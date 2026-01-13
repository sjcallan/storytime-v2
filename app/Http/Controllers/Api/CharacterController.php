<?php

namespace App\Http\Controllers\Api;

use App\Enums\ImageStatus;
use App\Enums\ImageType;
use App\Events\Image\ImageDeletedEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCharacterRequest;
use App\Http\Requests\UpdateCharacterRequest;
use App\Http\Requests\UploadCharacterPortraitRequest;
use App\Jobs\Image\GenerateImageJob;
use App\Models\Character;
use App\Models\Image;
use App\Services\Image\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
     * Creates a new Image record and deletes the old one.
     */
    public function regeneratePortrait(Character $character): JsonResponse
    {
        $this->authorize('update', $character);

        // Check for existing portrait image to copy prompt from
        $existingImage = $this->imageService->getCharacterPortrait($character->id);
        $oldImageId = $existingImage?->id;

        // Always create a new image record
        $image = $this->imageService->createCharacterPortraitImage(
            $character,
            $existingImage?->prompt
        );

        // Update character's portrait_image_id to point to new image
        $character->update(['portrait_image_id' => $image->id]);

        // Delete the old image record now that it's orphaned
        if ($oldImageId) {
            $this->imageService->deleteById($oldImageId);
            event(new ImageDeletedEvent($oldImageId, $character->book_id, $character->id));
        }

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

    /**
     * Upload a custom portrait image for a character.
     */
    public function uploadPortrait(UploadCharacterPortraitRequest $request, Character $character): JsonResponse
    {
        $file = $request->file('image');

        // Generate a unique filename for S3
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = "images/{$character->id}_".Str::random(8).".{$extension}";

        // Upload to S3
        $uploaded = Storage::disk('s3')->put($filename, file_get_contents($file->getRealPath()));

        if (! $uploaded) {
            return response()->json([
                'message' => 'Failed to upload image. Please try again.',
            ], 500);
        }

        // Build the full CloudFront URL (matching how generated images are stored)
        $cloudFrontUrl = 'https://d3lz6w5lgn41k.cloudfront.net/'.str_replace('images/', '', $filename);

        // Create an Image record for this upload
        $image = Image::create([
            'book_id' => $character->book_id,
            'character_id' => $character->id,
            'user_id' => auth()->id(),
            'profile_id' => $character->book?->profile_id,
            'type' => ImageType::CharacterPortrait,
            'image_url' => $cloudFrontUrl,
            'prompt' => 'User uploaded image',
            'status' => ImageStatus::Complete,
        ]);

        // Update character's portrait_image_id to point to new image
        $character->update(['portrait_image_id' => $image->id]);

        // Refresh the character and load relationships to get updated data
        $character->refresh();
        $character->load(['user', 'book', 'chapters', 'portraitImage']);

        return response()->json($character);
    }
}
