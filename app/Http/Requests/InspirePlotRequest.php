<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InspirePlotRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:chapter,theatre,story,screenplay'],
            'genre' => ['required', 'string', 'max:255'],
            'age_level' => ['required', 'string', 'max:3'],
            'inspiration_type' => ['nullable', 'string', 'in:plot,opening,location'],
            'plot' => ['nullable', 'string'],
            'first_chapter_prompt' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Please select a story type.',
            'type.in' => 'Please select a valid story type.',
            'genre.required' => 'Please select a genre.',
            'age_level.required' => 'Please select an age level.',
        ];
    }
}
