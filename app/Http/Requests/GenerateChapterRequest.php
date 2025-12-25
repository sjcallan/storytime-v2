<?php

namespace App\Http\Requests;

use App\Traits\Http\ValidatesModeration;
use Illuminate\Foundation\Http\FormRequest;

class GenerateChapterRequest extends FormRequest
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
            'user_prompt' => ['nullable', 'string', 'max:2000'],
            'final_chapter' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string>
     */
    protected function moderatedFields(): array
    {
        return ['user_prompt'];
    }

    protected function moderationSource(): string
    {
        return 'generate_chapter';
    }
}
