<?php

namespace App\Jobs;

use App\Services\RequestLog\RequestLogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TrackImageRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  array{url: string|null, error: string|null}  $response
     */
    public function __construct(
        protected string $model,
        protected string $itemType,
        protected array $response,
        protected string $prompt,
        protected int $inputImagesCount,
        protected int $outputImagesCount,
        protected float $responseTime,
        protected ?string $userId = null,
        protected ?string $profileId = null,
        protected ?string $bookId = null,
        protected ?string $chapterId = null,
        protected ?string $characterId = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(RequestLogService $requestLogService): void
    {
        Log::debug('TRACK IMAGE REQUEST: '.$this->itemType, [
            'model' => $this->model,
            'input_images' => $this->inputImagesCount,
            'output_images' => $this->outputImagesCount,
        ]);

        $pricing = $this->getPricingForModel($this->model);

        $inputCost = $this->inputImagesCount * $pricing['cost_per_input_image'];
        $outputCost = $this->outputImagesCount * $pricing['cost_per_output_image'];
        $totalCost = $inputCost + $outputCost;

        $requestLogService->store([
            'user_id' => $this->userId,
            'profile_id' => $this->profileId,
            'book_id' => $this->bookId,
            'chapter_id' => $this->chapterId,
            'item_type' => $this->itemType,
            'type' => 'image',
            'request' => json_encode([
                'prompt' => $this->prompt,
                'input_images_count' => $this->inputImagesCount,
                'character_id' => $this->characterId,
            ]),
            'response' => json_encode($this->response),
            'response_time' => $this->responseTime,
            'response_status_code' => $this->response['error'] ? 500 : 200,
            'model' => $this->model,
            'input_images_count' => $this->inputImagesCount,
            'output_images_count' => $this->outputImagesCount,
            'cost_per_input_image' => $pricing['cost_per_input_image'],
            'cost_per_output_image' => $pricing['cost_per_output_image'],
            'total_cost' => $totalCost,
        ]);

        Log::debug('TRACK IMAGE REQUEST: '.$this->itemType.' COMPLETE', [
            'total_cost' => $totalCost,
        ]);
    }

    /**
     * Get pricing configuration for the specified model.
     *
     * @return array{cost_per_input_image: float, cost_per_output_image: float}
     */
    protected function getPricingForModel(string $model): array
    {
        $imageConfig = config('ai.image_generation.replicate', []);

        if (str_contains($model, 'flux-2-pro')) {
            return [
                'cost_per_input_image' => $imageConfig['flux_2_pro']['cost_per_input_image'] ?? 0.015,
                'cost_per_output_image' => $imageConfig['flux_2_pro']['cost_per_output_image'] ?? 0.015,
            ];
        }

        if (str_contains($model, 'flux-2-max')) {
            return [
                'cost_per_input_image' => $imageConfig['flux_2_max']['cost_per_input_image'] ?? 0.015,
                'cost_per_output_image' => $imageConfig['flux_2_max']['cost_per_output_image'] ?? 0.015,
            ];
        }

        if (str_contains($model, 'flux-krea')) {
            return [
                'cost_per_input_image' => $imageConfig['flux_krea_dev']['cost_per_input_image'] ?? 0.0,
                'cost_per_output_image' => $imageConfig['flux_krea_dev']['cost_per_output_image'] ?? 0.025,
            ];
        }

        return [
            'cost_per_input_image' => $imageConfig['custom_model']['cost_per_input_image'] ?? 0.0,
            'cost_per_output_image' => $imageConfig['custom_model']['cost_per_output_image'] ?? 0.025,
        ];
    }
}
