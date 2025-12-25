<?php

namespace App\Http\Requests\Settings;

use App\Models\Profile;
use App\Traits\Http\ValidatesModeration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'age_group' => ['required', 'string', Rule::in(array_keys(Profile::AGE_GROUPS))],
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
            'name.required' => 'A profile name is required.',
            'name.max' => 'The profile name cannot exceed 255 characters.',
            'age_group.required' => 'An age group is required.',
            'age_group.in' => 'Please select a valid age group.',
        ];
    }

    /**
     * @return array<string>
     */
    protected function moderatedFields(): array
    {
        return ['name'];
    }

    protected function moderationSource(): string
    {
        return 'store_profile';
    }
}
