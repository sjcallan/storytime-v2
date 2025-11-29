<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequestLogRequest extends FormRequest
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
            'book_id' => ['nullable', 'string', 'exists:books,id'],
            'chapter_id' => ['nullable', 'string', 'exists:chapters,id'],
            'item_type' => ['nullable', 'string', 'max:255'],
            'request' => ['nullable', 'string'],
            'response' => ['nullable', 'string'],
            'response_status_code' => ['nullable', 'integer'],
            'response_time' => ['nullable', 'numeric'],
            'open_ai_id' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'prompt_tokens' => ['nullable', 'integer'],
            'completion_tokens' => ['nullable', 'integer'],
            'total_tokens' => ['nullable', 'integer'],
            'cost_per_token' => ['nullable', 'numeric'],
            'total_cost' => ['nullable', 'numeric'],
        ];
    }
}
