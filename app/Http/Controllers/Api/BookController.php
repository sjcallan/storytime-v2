<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InspirePlotRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Jobs\Book\GenerateBookCoverJob;
use App\Models\Book;
use App\Services\Book\BookService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected BookService $bookService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $books = Book::query()
            ->with(['user', 'chapters', 'characters', 'profile'])
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return response()->json($books);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBookRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();

            $book = Book::create([
                ...$validated,
                'user_id' => auth()->id(),
                'profile_id' => session('current_profile_id'),
            ]);

            $this->bookService->createBookMetaDataByBookId($book->id);

            $book->refresh();
            $book->load(['user', 'chapters', 'characters', 'profile']);

            return response()->json($book, 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(Book $book): JsonResponse
    {
        $this->authorize('view', $book);

        $book->load(['user', 'chapters', 'characters', 'profile']);

        return response()->json($book);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBookRequest $request, Book $book): JsonResponse
    {
        $this->authorize('update', $book);

        $book->update($request->validated());

        $book->load(['user', 'chapters', 'characters', 'profile']);

        return response()->json($book);
    }

    /**
     * Generate metadata (title, characters) for the book.
     */
    public function generateMetadata(Book $book): JsonResponse
    {
        $this->authorize('update', $book);

        $existingCharacters = $book->characters()
            ->where('type', 'user')
            ->get()
            ->map(fn ($c) => [
                'name' => $c->name,
                'description' => $c->description,
                'gender' => $c->gender,
                'age' => $c->age,
            ])
            ->toJson();

        $metaData = $this->bookService->createBookMetaDataByBookId(
            $book->id,
            $existingCharacters
        );

        $book->refresh();
        $book->load(['characters']);

        return response()->json([
            'title' => $metaData['title'] ?? $book->title,
            'characters' => $book->characters,
        ]);
    }

    /**
     * Regenerate the book cover image.
     */
    public function regenerateCover(Book $book): JsonResponse
    {
        $this->authorize('update', $book);

        $book->update(['cover_image_status' => 'pending']);

        GenerateBookCoverJob::dispatch($book->id);

        return response()->json([
            'message' => 'Cover generation started',
            'cover_image_status' => 'pending',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Book $book): JsonResponse
    {
        $this->authorize('delete', $book);

        $book->delete();

        return response()->json(null, 204);
    }

    /**
     * Generate a creative plot inspiration based on book type, genre, and age level.
     */
    public function inspirePlot(InspirePlotRequest $request): JsonResponse
    {
        $inspiration = $this->bookService->generatePlotInspiration($request->validated());

        return response()->json([
            'inspiration' => $inspiration,
        ]);
    }
}
