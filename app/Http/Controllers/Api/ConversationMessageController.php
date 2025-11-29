<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConversationMessageRequest;
use App\Http\Requests\UpdateConversationMessageRequest;
use App\Models\ConversationMessage;
use Illuminate\Http\JsonResponse;

class ConversationMessageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $messages = ConversationMessage::query()
            ->with(['conversation', 'character'])
            ->whereHas('conversation', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->latest()
            ->get();

        return response()->json($messages);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreConversationMessageRequest $request): JsonResponse
    {
        $message = ConversationMessage::create($request->validated());

        $message->load(['conversation', 'character']);

        return response()->json($message, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(ConversationMessage $conversationMessage): JsonResponse
    {
        $this->authorize('view', $conversationMessage);

        $conversationMessage->load(['conversation', 'character']);

        return response()->json($conversationMessage);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateConversationMessageRequest $request, ConversationMessage $conversationMessage): JsonResponse
    {
        $this->authorize('update', $conversationMessage);

        $conversationMessage->update($request->validated());

        $conversationMessage->load(['conversation', 'character']);

        return response()->json($conversationMessage);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ConversationMessage $conversationMessage): JsonResponse
    {
        $this->authorize('delete', $conversationMessage);

        $conversationMessage->delete();

        return response()->json(null, 204);
    }
}
