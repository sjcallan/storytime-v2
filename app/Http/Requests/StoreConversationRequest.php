<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'character_id' => ['nullable', 'string', 'exists:characters,id'],
            'type' => ['nullable', 'string', 'max:255'],
            'character_name' => ['nullable', 'string', 'max:255'],
            'character_age' => ['nullable', 'string', 'max:255'],
            'character_gender' => ['nullable', 'string', 'max:255'],
            'character_nationality' => ['nullable', 'string', 'max:255'],
            'character_description' => ['nullable', 'string'],
            'character_backstory' => ['nullable', 'string'],
        ];
    }
}
