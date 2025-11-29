<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('characters') && is_array($this->characters)) {
            $characters = collect($this->characters)->map(function ($character) {
                if (isset($character['age']) && ! is_string($character['age'])) {
                    $character['age'] = (string) $character['age'];
                }

                return $character;
            })->toArray();

            $this->merge(['characters' => $characters]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'cover_image' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', 'in:draft,in_progress,completed,published'],
            'age_level' => ['nullable', 'integer', 'min:0', 'max:18'],
            'genre' => ['nullable', 'string', 'max:255'],
            'plot' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'last_opened_date' => ['nullable', 'date'],
            'is_published' => ['boolean'],
            'user_characters' => ['nullable', 'string'],
            'scene' => ['nullable', 'string'],
            'published_at' => ['nullable', 'date'],
            'additional_instructions' => ['nullable', 'string'],
            'first_chapter_prompt' => ['nullable', 'string'],
            'characters' => ['nullable', 'array'],
            'characters.*.name' => ['required_with:characters', 'string', 'max:255'],
            'characters.*.gender' => ['nullable', 'string', 'max:255'],
            'characters.*.description' => ['nullable', 'string'],
            'characters.*.age' => ['nullable', 'string', 'max:255'],
            'characters.*.backstory' => ['nullable', 'string'],
        ];
    }
}
