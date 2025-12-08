<?php

namespace App\Services\Utility;

use App\Services\Gpt\OpenAi\ApiService;
use Illuminate\Support\Facades\Log;
use Orhanerday\OpenAi\OpenAi;

class ModerationService
{

    protected string $prompt;

    protected bool $flagged = false;

    protected array $categories;

    protected array $categoryScores;

    /**
     * 
     */
    public function __construct(protected ApiService $openAi)
    {
        
    }

    /**
     * 
     */
    public function test(string $string):array
    {
        if(config('gpt.moderation_enabled') == 'disabled') {
            $this->setFlagged(false);
            return [];
        }

        $this->setPrompt($string);
        $response = $this->openAi->moderation($string);

        $this->setFlagged($response['results'][0]['flagged']);
        $this->setCategories($response['results'][0]['categories']);
        $this->setCategoryScores($response['results'][0]['category_scores']);

        // Log::Debug($response, ['moderation']);

        return $response['results'];
    }

    /**
     * 
     */
    public function setPrompt(string $string):void
    {
        $this->prompt = $string;
    }

    /**
     * 
     */
    public function setFlagged(bool $flagged):void
    {
        $this->flagged = $flagged;
    }

    /**
     * 
     */
    public function setCategories(array $categories):void
    {
        $this->categories = $categories;
    }

    /**
     * 
     */
    public function setCategoryScores(array $categoryScores):void
    {
        $this->categoryScores = $categoryScores;
    }

    /**
     * 
     */
    public function getCategories()
    {
       return $this->categories;
    }

    /**
     * 
     */
    public function getCategoryScores()
    {
        return $this->categoryScores;
    }

    /**
     * 
     */
    public function isFlagged(string $string)
    {
        $this->test($string);

        if($this->flagged) {
            return true;
        }
    }


}