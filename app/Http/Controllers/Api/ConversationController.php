<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConversationRequest;
use App\Http\Requests\UpdateConversationRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

class ConversationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $conversations = Conversation::query()
            ->with(['user', 'character', 'messages'])
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return response()->json($conversations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreConversationRequest $request): JsonResponse
    {
        $conversation = Conversation::create([
            ...$request->validated(),
            'user_id' => auth()->id(),
        ]);

        $conversation->load(['user', 'character', 'messages']);

        return response()->json($conversation, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->load(['user', 'character', 'messages']);

        return response()->json($conversation);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateConversationRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $conversation->update($request->validated());

        $conversation->load(['user', 'character', 'messages']);

        return response()->json($conversation);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Conversation $conversation): JsonResponse
    {
        $this->authorize('delete', $conversation);

        $conversation->delete();

        return response()->json(null, 204);
    }
}
