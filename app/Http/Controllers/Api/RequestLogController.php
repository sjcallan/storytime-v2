<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRequestLogRequest;
use App\Http\Requests\UpdateRequestLogRequest;
use App\Models\RequestLog;
use Illuminate\Http\JsonResponse;

class RequestLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $logs = RequestLog::query()
            ->with(['user', 'book', 'chapter'])
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return response()->json($logs);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRequestLogRequest $request): JsonResponse
    {
        $log = RequestLog::create([
            ...$request->validated(),
            'user_id' => auth()->id(),
        ]);

        $log->load(['user', 'book', 'chapter']);

        return response()->json($log, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(RequestLog $requestLog): JsonResponse
    {
        $this->authorize('view', $requestLog);

        $requestLog->load(['user', 'book', 'chapter']);

        return response()->json($requestLog);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRequestLogRequest $request, RequestLog $requestLog): JsonResponse
    {
        $this->authorize('update', $requestLog);

        $requestLog->update($request->validated());

        $requestLog->load(['user', 'book', 'chapter']);

        return response()->json($requestLog);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RequestLog $requestLog): JsonResponse
    {
        $this->authorize('delete', $requestLog);

        $requestLog->delete();

        return response()->json(null, 204);
    }
}
