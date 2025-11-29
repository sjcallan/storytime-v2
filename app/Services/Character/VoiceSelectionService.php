<?php

namespace App\Services\Character;

use App\Services\OpenAi\ChatService;
use AWS\CRT\Log;
use Illuminate\Support\Facades\Log as FacadesLog;

class VoiceSelectionService
{   
    protected mixed $age;

    protected string $nationality;

    protected string $gender;

    protected string $engine = 'nueral';

    /**
     * 
     */
    public function __construct(protected CharacterService $characterService, protected ChatService $chatService ) 
    {

    }

    /**
     * 
     */
    public function setEngine(string $engine = 'neural') 
    {
        $this->engine = $engine;
    }

    /**
     * 
     */
    public function setGender(string $gender = null) 
    {
        $this->gender = $gender;
    }

    /**
     * 
     */
    public function setNationality(string $nationality = null) 
    {
        $this->nationality = strtolower($nationality);
    }

    /**
     * 
     */
    public function setAge(mixed $age = null) 
    {
        $this->age = $age;
    }

    /**
     * 
     */
    public function getVoiceName()
    {
        switch(strtolower($this->gender)) {
            case "male":
                return $this->getMaleVoice();
            break;
            
            case "female":
                return $this->getFemaleVoice();
            break;

            default:
                return $this->getOtherVoice();
            break;
        }
    }

    /**
     * 
     */
    protected function getMaleVoice()
    {
        FacadesLog::debug($this->age);

        if($this->nationality == 'british'|| $this->nationality == 'scottish') {
            if($this->age <= 30) {
                return 'Brian';
            }

            return 'Arthur';
        }

        if($this->nationality == 'australian') {
            return 'Olivia';
        }

        if($this->age <= 10) {
            return "Kevin";
        }

        if($this->age <= 18) {
            return "Justin";
        }

        if($this->age <= 30) {
            return "Joey";
        }

        if($this->nationality == 'british') {
            return 'Arthur';
        }

        if($this->age <= 50) {
            return "Matthew";
        }

        return "Stephen";
    }



    /**
     * 
     */
    protected function getFemaleVoice()
    {
        if($this->nationality == 'british' || $this->nationality == 'scottish') {
            if($this->age <= 30) {
                return "Emma";
            }

            return 'Amy';
        }

        if($this->nationality == 'australian') {
            $this->setEngine('standard');
            return 'Russel';
        }

        if($this->age <= 10) {
            return "Ivy";
        }

        if($this->age <= 40) {
            return "Salli";
        }

        if($this->age <= 60) {
            return "Joanna";
        }

        return "Kendra";
    }



    /**
     * 
     */
    protected function getOtherVoice()
    {
        return "Kevin";
    }
}