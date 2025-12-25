<?php

namespace App\Http\Requests;

use App\Traits\Http\ValidatesModeration;
use Illuminate\Foundation\Http\FormRequest;

class StoreCharacterRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'in:book,user'],
            'age' => ['nullable', 'string', 'max:255'],
            'book_id' => ['required', 'string', 'exists:books,id'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'backstory' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string>
     */
    protected function moderatedFields(): array
    {
        return [
            'name',
            'description',
            'backstory',
        ];
    }

    protected function moderationSource(): string
    {
        return 'store_character';
    }
}
