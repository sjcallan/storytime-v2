<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReadingLogRequest;
use App\Http\Requests\UpdateReadingLogRequest;
use App\Models\ReadingLog;
use Illuminate\Http\JsonResponse;

class ReadingLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $logs = ReadingLog::query()
            ->with(['user', 'book', 'chapter'])
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return response()->json($logs);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreReadingLogRequest $request): JsonResponse
    {
        $log = ReadingLog::create([
            ...$request->validated(),
            'user_id' => auth()->id(),
        ]);

        $log->load(['user', 'book', 'chapter']);

        return response()->json($log, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(ReadingLog $readingLog): JsonResponse
    {
        $this->authorize('view', $readingLog);

        $readingLog->load(['user', 'book', 'chapter']);

        return response()->json($readingLog);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateReadingLogRequest $request, ReadingLog $readingLog): JsonResponse
    {
        $this->authorize('update', $readingLog);

        $readingLog->update($request->validated());

        $readingLog->load(['user', 'book', 'chapter']);

        return response()->json($readingLog);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReadingLog $readingLog): JsonResponse
    {
        $this->authorize('delete', $readingLog);

        $readingLog->delete();

        return response()->json(null, 204);
    }
}
