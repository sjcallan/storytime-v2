<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Character;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $books = Book::query()
            ->with(['user', 'chapters', 'characters'])
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
            $charactersData = $validated['characters'] ?? [];
            unset($validated['characters']);

            $book = Book::create([
                ...$validated,
                'user_id' => auth()->id(),
                'profile_id' => session('current_profile_id'),
            ]);

            foreach ($charactersData as $characterData) {
                Character::create([
                    ...$characterData,
                    'book_id' => $book->id,
                    'user_id' => auth()->id(),
                    'type' => 'user',
                ]);
            }

            $book->load(['user', 'chapters', 'characters']);

            return response()->json($book, 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(Book $book): JsonResponse
    {
        $this->authorize('view', $book);

        $book->load(['user', 'chapters', 'characters']);

        return response()->json($book);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBookRequest $request, Book $book): JsonResponse
    {
        $this->authorize('update', $book);

        $book->update($request->validated());

        $book->load(['user', 'chapters', 'characters']);

        return response()->json($book);
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
}
