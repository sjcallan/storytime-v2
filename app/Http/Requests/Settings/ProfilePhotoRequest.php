<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ProfilePhotoRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,gif,webp',
                'max:2048',
                'dimensions:min_width=100,min_height=100,max_width=4096,max_height=4096',
            ],
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
            'photo.required' => 'Please select a photo to upload.',
            'photo.image' => 'The file must be a valid image.',
            'photo.mimes' => 'The photo must be a JPG, PNG, GIF, or WebP file.',
            'photo.max' => 'The photo must not be larger than 2MB.',
            'photo.dimensions' => 'The photo must be between 100x100 and 4096x4096 pixels.',
        ];
    }
}
