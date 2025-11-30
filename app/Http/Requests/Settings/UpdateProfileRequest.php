<?php

namespace App\Http\Requests\Settings;

use App\Models\Profile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $profile = $this->route('profile');

        return $profile && $profile->user_id === $this->user()->id;
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
}
