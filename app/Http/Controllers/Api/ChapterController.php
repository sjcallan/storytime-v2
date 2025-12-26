<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateChapterRequest;
use App\Http\Requests\StoreChapterRequest;
use App\Http\Requests\UpdateChapterRequest;
use App\Jobs\Chapter\RegenerateChapterInlineImageJob;
use App\Models\Book;
use App\Models\Chapter;
use App\Services\Builder\ChapterBuilderService;
use App\Services\Chapter\ChapterService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChapterController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ChapterService $chapterService,
        protected ChapterBuilderService $chapterBuilderService
    ) {}

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
            'profile_id' => session('current_profile_id'),
        ]);

        $chapter->load(['user', 'book', 'characters']);

        return response()->json($chapter, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Chapter $chapter): JsonResponse
    {
        $chapter->load(['user', 'book', 'characters']);

        return response()->json($chapter);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateChapterRequest $request, Chapter $chapter): JsonResponse
    {
        $chapter->update($request->validated());

        $chapter->load(['user', 'book', 'characters']);

        return response()->json($chapter);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Chapter $chapter): JsonResponse
    {
        $chapter->delete();

        return response()->json(null, 204);
    }

    /**
     * Get a chapter by book ID and chapter number (sort).
     */
    public function getByBookAndSort(Book $book, int $chapterNumber): JsonResponse
    {
        $chapter = $this->chapterService->getByBookIdAndSort(
            $book->id,
            $chapterNumber,
            null,
            ['with' => ['characters']]
        );

        if (! $chapter) {
            return response()->json([
                'chapter' => null,
                'total_chapters' => $this->chapterService->getCompleteChapterCount($book->id),
                'has_next' => false,
            ]);
        }

        $totalChapters = $this->chapterService->getCompleteChapterCount($book->id);

        return response()->json([
            'chapter' => $chapter,
            'total_chapters' => $totalChapters,
            'has_next' => $chapterNumber < $totalChapters,
            'has_previous' => $chapterNumber > 1,
        ]);
    }

    /**
     * Generate a suggested prompt placeholder based on the last chapter's cliffhanger.
     */
    public function suggestPrompt(Book $book): JsonResponse
    {
        $lastChapter = $this->chapterService->getLastChapter($book->id);

        if (! $lastChapter || ! $lastChapter->body) {
            return response()->json([
                'placeholder' => null,
            ]);
        }

        $placeholder = $this->chapterBuilderService->generatePromptSuggestion($book, $lastChapter);

        return response()->json([
            'placeholder' => $placeholder,
        ]);
    }

    /**
     * Generate the next chapter for a book.
     */
    public function generateNext(GenerateChapterRequest $request, Book $book): JsonResponse
    {
        $validated = $request->validated();

        $existingChapterCount = $this->chapterService->getCompleteChapterCount($book->id);
        $userPrompt = $validated['user_prompt'] ?? null;

        $chapterData = [
            'final_chapter' => $validated['final_chapter'] ?? false,
        ];

        if ($existingChapterCount === 0) {
            $chapterData['first_chapter_prompt'] = $userPrompt;
            $chapterData['user_prompt'] = null;
        } else {
            $chapterData['user_prompt'] = $userPrompt;
        }

        $chapter = $this->chapterBuilderService->buildChapter($book->id, $chapterData);

        $totalChapters = $this->chapterService->getCompleteChapterCount($book->id);

        return response()->json([
            'chapter' => $chapter,
            'total_chapters' => $totalChapters,
            'has_next' => false,
            'has_previous' => $chapter->sort > 1,
        ], 201);
    }

    /**
     * Regenerate an inline image for a chapter.
     */
    public function regenerateImage(Request $request, Book $book, Chapter $chapter): JsonResponse
    {
        $this->authorize('update', $book);

        if ($chapter->book_id !== $book->id) {
            return response()->json(['message' => 'Chapter does not belong to this book.'], 404);
        }

        $imageIndex = $request->input('image_index');

        if ($imageIndex === null || ! is_numeric($imageIndex)) {
            return response()->json(['message' => 'image_index is required.'], 422);
        }

        $imageIndex = (int) $imageIndex;

        $inlineImages = $chapter->inline_images ?? [];
        $imageExists = false;

        $updatedImages = [];
        foreach ($inlineImages as $image) {
            if (($image['paragraph_index'] ?? null) === $imageIndex) {
                $imageExists = true;
                $updatedImages[] = [
                    ...$image,
                    'status' => 'pending',
                ];
            } else {
                $updatedImages[] = $image;
            }
        }

        if (! $imageExists) {
            return response()->json(['message' => 'Image not found at specified index.'], 404);
        }

        $chapter->update(['inline_images' => $updatedImages]);

        RegenerateChapterInlineImageJob::dispatch($chapter, $imageIndex);

        return response()->json([
            'message' => 'Image regeneration started',
            'image_index' => $imageIndex,
        ]);
    }
}
