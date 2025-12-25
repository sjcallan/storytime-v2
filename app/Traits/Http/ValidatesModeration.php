<?php

namespace App\Traits\Http;

use App\Services\Ai\OpenAi\ModerationService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Arr;

trait ValidatesModeration
{
    /**
     * Get the fields that should be checked for moderation.
     * Override this method in your Form Request to specify fields.
     *
     * @return array<string>
     */
    protected function moderatedFields(): array
    {
        return [];
    }

    /**
     * Get the source identifier for moderation logging.
     * Override this to provide context about where the moderation request originated.
     */
    protected function moderationSource(): string
    {
        return static::class;
    }

    /**
     * Configure the validator instance to include moderation checks.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateModeration($validator);
        });
    }

    /**
     * Validate the specified fields against the moderation API.
     */
    protected function validateModeration(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $moderationService = app(ModerationService::class);

        if (! $moderationService->isEnabled()) {
            return;
        }

        $fields = $this->moderatedFields();

        if (empty($fields)) {
            return;
        }

        $textToModerate = $this->collectTextForModeration($fields);

        if (empty($textToModerate)) {
            return;
        }

        $result = $moderationService->moderate($textToModerate, [
            'user_id' => auth()->id(),
            'profile_id' => session('current_profile_id'),
            'source' => $this->moderationSource(),
        ]);

        if ($result->failed()) {
            $validator->errors()->add(
                'moderation',
                $result->getViolationMessage()
            );
        }
    }

    /**
     * Collect text from the specified fields for moderation.
     *
     * @param  array<string>  $fields
     */
    protected function collectTextForModeration(array $fields): string
    {
        $values = [];

        foreach ($fields as $field) {
            $value = $this->getFieldValue($field);

            if (is_string($value) && ! empty(trim($value))) {
                $values[] = trim($value);
            }
        }

        return implode("\n\n", $values);
    }

    /**
     * Get a field value supporting dot notation and array wildcards.
     *
     * @return mixed
     */
    protected function getFieldValue(string $field)
    {
        if (str_contains($field, '*')) {
            return $this->getWildcardFieldValues($field);
        }

        return $this->input($field);
    }

    /**
     * Get values from fields with wildcard notation (e.g., 'characters.*.name').
     */
    protected function getWildcardFieldValues(string $field): ?string
    {
        $data = data_get($this->all(), str_replace('*', '', $field));

        if ($data === null) {
            return null;
        }

        $values = Arr::flatten(is_array($data) ? $data : [$data]);

        $stringValues = array_filter($values, fn ($v) => is_string($v) && ! empty(trim($v)));

        return ! empty($stringValues) ? implode("\n", array_map('trim', $stringValues)) : null;
    }
}
