<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AWS\TranscribeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranscribeController extends Controller
{
    public function __construct(
        protected TranscribeService $transcribeService
    ) {}

    /**
     * Transcribe uploaded audio to text.
     */
    public function transcribe(Request $request): JsonResponse
    {
        $request->validate([
            'audio' => ['required', 'file', 'mimes:webm,mp3,mp4,wav,ogg,m4a', 'max:10240'],
        ]);

        $result = $this->transcribeService->transcribe($request->file('audio'));

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'text' => $result['text'],
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'],
        ], 422);
    }
}
