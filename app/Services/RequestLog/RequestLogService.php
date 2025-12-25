<?php

namespace App\Services\RequestLog;

use App\Models\Chapter;
use App\Repositories\RequestLog\RequestLogRepository;
use App\Traits\Service\Creatable;
use App\Traits\Service\Deletable;
use App\Traits\Service\Gettable;
use App\Traits\Service\Updatable;

class RequestLogService
{
    use Creatable, Deletable, Gettable, Updatable;

    /** @var \App\Repositories\RequestLog\RequestLogRepository */
    protected $repository;

    public function __construct(RequestLogRepository $userRepository)
    {
        $this->repository = $userRepository;
    }

    /**
     * Store a request log with validation
     */
    public function store(array $data, ?array $options = null)
    {
        $data = $this->validateAndSanitizeData($data);

        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return $this->repository->store($data, $options);
    }

    /**
     * Validate and sanitize request log data
     */
    protected function validateAndSanitizeData(array $data): array
    {
        if (isset($data['chapter_id']) && ! empty($data['chapter_id'])) {
            $chapterExists = Chapter::where('id', $data['chapter_id'])->exists();

            if (! $chapterExists) {
                $data['chapter_id'] = null;
            }
        } else {
            $data['chapter_id'] = null;
        }

        return $data;
    }

    /**
     * Parse text generation response for storage
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function parseResponseForStore(array $response): array
    {
        return [
            'type' => 'text',
            'open_ai_id' => $response['id'] ?? null,
            'model' => $response['model'] ?? null,
            'prompt_tokens' => $response['prompt_tokens'] ?? null,
            'completion_tokens' => $response['completion_tokens'] ?? null,
            'total_tokens' => $response['total_tokens'] ?? null,
            'cost_per_token' => $response['cost_per_token'] ?? null,
            'total_cost' => $response['total_cost'] ?? null,
        ];
    }

    /**
     * Parse image generation response for storage
     *
     * @param  array{url: string|null, error: string|null}  $response
     * @return array<string, mixed>
     */
    public function parseImageResponseForStore(
        array $response,
        string $model,
        int $inputImagesCount,
        int $outputImagesCount
    ): array {
        $pricing = $this->getImagePricingForModel($model);

        $inputCost = $inputImagesCount * $pricing['cost_per_input_image'];
        $outputCost = $outputImagesCount * $pricing['cost_per_output_image'];

        return [
            'type' => 'image',
            'model' => $model,
            'input_images_count' => $inputImagesCount,
            'output_images_count' => $outputImagesCount,
            'cost_per_input_image' => $pricing['cost_per_input_image'],
            'cost_per_output_image' => $pricing['cost_per_output_image'],
            'total_cost' => $inputCost + $outputCost,
        ];
    }

    /**
     * Get pricing configuration for the specified image model
     *
     * @return array{cost_per_input_image: float, cost_per_output_image: float}
     */
    protected function getImagePricingForModel(string $model): array
    {
        $imageConfig = config('ai.image_generation.replicate', []);

        if (str_contains($model, 'flux-2-pro')) {
            return [
                'cost_per_input_image' => $imageConfig['flux_2_pro']['cost_per_input_image'] ?? 0.015,
                'cost_per_output_image' => $imageConfig['flux_2_pro']['cost_per_output_image'] ?? 0.015,
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

    public function getAllByBookId(string $bookId, ?array $fields = null, ?array $options = null)
    {
        return $this->repository->getAllByBookId($bookId, $fields, $options);
    }
}
