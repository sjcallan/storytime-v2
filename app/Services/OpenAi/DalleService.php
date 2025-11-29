<?php

namespace App\Services\OpenAi;

use Illuminate\Support\Facades\Log;

class DalleService
{

    /** @var \App\Services\OpenAi\ApiService */
    protected $openAiService;

    /**
     * 
     */
    public function __construct(ApiService $openAiService)
    {
        $this->openAiService = $openAiService;
    }

    /**
     * 
     */
    public function getImage(string $prompt)
    {
        return $this->openAiService->image($prompt);
    }
}