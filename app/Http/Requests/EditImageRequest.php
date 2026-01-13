<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EditImageRequest extends FormRequest
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
            'prompt' => ['required', 'string', 'min:10', 'max:10000'],
            'character_image_urls' => ['nullable', 'array'],
            'character_image_urls.*' => ['string', 'url'],
            'reference_image_urls' => ['nullable', 'array'],
            'reference_image_urls.*' => ['string', 'url'],
            'aspect_ratio' => ['nullable', 'string', 'in:16:9,4:3,1:1,3:4,9:16'],
            'use_original_as_reference' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prompt.required' => 'Please provide a scene description for your image.',
            'prompt.min' => 'The scene description must be at least 10 characters.',
            'prompt.max' => 'The scene description is too long.',
        ];
    }
}
