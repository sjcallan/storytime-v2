<?php

namespace App\Http\Requests\Settings;

use App\Traits\Http\ValidatesModeration;
use Illuminate\Foundation\Http\FormRequest;

class GenerateBackgroundImageRequest extends FormRequest
{
    use ValidatesModeration;

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
            'description' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string>
     */
    protected function moderatedFields(): array
    {
        return ['description'];
    }

    protected function moderationSource(): string
    {
        return 'generate_background_image';
    }
}
