<?php

namespace App\Http\Requests;

use App\Traits\Http\ValidatesModeration;
use Illuminate\Foundation\Http\FormRequest;

class StoreConversationMessageRequest extends FormRequest
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
            'conversation_id' => ['required', 'string', 'exists:conversations,id'],
            'message' => ['nullable', 'string'],
            'response' => ['nullable', 'string'],
            'audio_file_url' => ['nullable', 'string', 'max:255'],
            'character_id' => ['nullable', 'string', 'exists:characters,id'],
        ];
    }

    /**
     * @return array<string>
     */
    protected function moderatedFields(): array
    {
        return ['message'];
    }

    protected function moderationSource(): string
    {
        return 'conversation_message';
    }
}
