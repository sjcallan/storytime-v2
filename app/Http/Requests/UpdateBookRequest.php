<?php

namespace App\Http\Requests;

use App\Traits\Http\ValidatesModeration;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBookRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:255'],
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
        ];
    }

    /**
     * @return array<string>
     */
    protected function moderatedFields(): array
    {
        return [
            'title',
            'plot',
            'user_characters',
            'scene',
            'additional_instructions',
            'first_chapter_prompt',
        ];
    }

    protected function moderationSource(): string
    {
        return 'update_book';
    }
}
