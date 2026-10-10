<?php

namespace App\Traits;

use Closure;
use Illuminate\Validation\ValidationException;
use JsonException;

/** Preserve nested arrays and cleared fields alongside multipart catalog artwork. */
trait NormalizesCatalogPayload
{
    /** Decode the optional JSON payload before the catalog Form Request validates fields. */
    protected function prepareForValidation(): void
    {
        if (! $this->has('payload')) {
            return;
        }

        try {
            $payload = json_decode($this->input('payload'), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException|\TypeError $exception) {
            throw ValidationException::withMessages(['payload' => ['The catalog form payload is invalid.']]);
        }

        if (! is_array($payload) || array_is_list($payload)) {
            throw ValidationException::withMessages(['payload' => ['The catalog form must contain named fields.']]);
        }

        unset($payload['payload'], $payload['image'], $payload['banner_image'], $payload['main_image']);
        $this->merge($payload);

        if ($this->input('slug') === null || $this->input('slug') === '') {
            $this->request->remove('slug');
        }
    }

    /** Validate only supported locales and require a nonblank display label when requested. */
    protected function translationRules(string $field, bool $required = false, int $max = 190): array
    {
        $rules = [$required ? 'required' : 'nullable', 'array:gu,en,hi'];
        if ($required) {
            $rules[] = function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_array($value) || ! collect($value)->contains(
                    fn ($label) => is_string($label) && trim($label) !== ''
                )) {
                    $fail('Enter a display name in at least one language.');
                }
            };
        }

        return [
            $field => $rules,
            $field.'.gu' => ['nullable', 'string', 'max:'.$max],
            $field.'.en' => ['nullable', 'string', 'max:'.$max],
            $field.'.hi' => ['nullable', 'string', 'max:'.$max],
        ];
    }
}
