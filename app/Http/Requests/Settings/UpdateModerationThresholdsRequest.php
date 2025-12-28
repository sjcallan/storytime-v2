<?php

namespace App\Http\Requests\Settings;

use App\Models\Profile;
use Illuminate\Foundation\Http\FormRequest;

class UpdateModerationThresholdsRequest extends FormRequest
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
        $categories = array_keys(Profile::MODERATION_CATEGORIES);

        $rules = [
            'thresholds' => ['required', 'array'],
        ];

        foreach ($categories as $category) {
            $safeKey = str_replace(['/', '-'], '_', $category);
            $rules["thresholds.{$safeKey}"] = ['required', 'numeric', 'min:0.01', 'max:1'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'thresholds.required' => 'Moderation thresholds are required.',
            'thresholds.array' => 'Invalid threshold format.',
            'thresholds.*.required' => 'All threshold values are required.',
            'thresholds.*.numeric' => 'Threshold values must be numbers.',
            'thresholds.*.min' => 'Threshold values must be at least 0.01.',
            'thresholds.*.max' => 'Threshold values cannot exceed 1.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $thresholds = $this->input('thresholds', []);

        $converted = [];
        foreach ($thresholds as $key => $value) {
            $originalKey = str_replace('_', '/', $key);
            if (str_contains($originalKey, 'self/harm')) {
                $originalKey = str_replace('self/harm', 'self-harm', $originalKey);
            }
            $converted[$key] = $value;
        }

        $this->merge(['thresholds' => $converted]);
    }

    /**
     * Get the validated data with proper category keys restored.
     *
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated($key, $default);

        if ($key === null && isset($validated['thresholds'])) {
            $converted = [];
            foreach ($validated['thresholds'] as $safeKey => $value) {
                $originalKey = str_replace('_', '/', $safeKey);
                if (str_contains($originalKey, 'self/harm')) {
                    $originalKey = str_replace('self/harm', 'self-harm', $originalKey);
                }
                $converted[$originalKey] = (float) $value;
            }
            $validated['thresholds'] = $converted;
        }

        return $validated;
    }
}
