<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChapterRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:255'],
            'book_id' => ['nullable', 'string', 'exists:books,id'],
            'body' => ['nullable', 'string'],
            'summary' => ['nullable', 'string'],
            'user_prompt' => ['nullable', 'string'],
            'error' => ['nullable', 'string'],
            'final_chapter' => ['boolean'],
            'sort' => ['nullable', 'integer'],
            'cta' => ['nullable', 'string'],
            'cta_total_cost' => ['nullable', 'numeric'],
            'image_prompt' => ['nullable', 'string'],
            'image' => ['nullable', 'string'],
            'book_summary' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:draft,in_progress,completed,published'],
        ];
    }
}
