<?php

namespace App\Services\ProjectUserFields;

use App\Enums\ProjectUserFieldType;
use App\Models\ProjectUserField;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use JsonException;

class NormalizeProjectUserFieldValue
{
    /**
     * Normalize a raw value according to a field definition.
     */
    public function normalize(ProjectUserField $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }
        }

        return match ($field->type) {
            ProjectUserFieldType::StringType,
            ProjectUserFieldType::Text,
            ProjectUserFieldType::Enum,
            ProjectUserFieldType::Url,
            ProjectUserFieldType::Phone,
            ProjectUserFieldType::Uuid => (string) $value,
            ProjectUserFieldType::Email => Str::lower((string) $value),
            ProjectUserFieldType::Integer => (int) $value,
            ProjectUserFieldType::Decimal => $this->normalizeDecimal($value),
            ProjectUserFieldType::Boolean => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            ProjectUserFieldType::Date => CarbonImmutable::parse($value)->toDateString(),
            ProjectUserFieldType::DateTime => CarbonImmutable::parse($value)->utc()->toDateTimeString(),
            ProjectUserFieldType::Json => $this->normalizeJson($value),
        };
    }

    /**
     * Prepare normalized data for the typed storage columns.
     *
     * @return array<string, mixed>
     */
    public function toStorageAttributes(ProjectUserField $field, mixed $normalizedValue): array
    {
        $attributes = [
            'value_string' => null,
            'value_text' => null,
            'value_integer' => null,
            'value_decimal' => null,
            'value_boolean' => null,
            'value_date' => null,
            'value_datetime' => null,
            'value_json' => null,
        ];

        if ($normalizedValue === null) {
            return $attributes;
        }

        $attributes[$field->storageColumn()] = $normalizedValue;

        return $attributes;
    }

    /**
     * Hash a normalized value for uniqueness enforcement.
     */
    public function hash(ProjectUserField $field, mixed $normalizedValue): string
    {
        return hash('sha256', $this->canonicalizeForHash($field, $normalizedValue));
    }

    /**
     * Normalize a decimal into a canonical string representation.
     */
    private function normalizeDecimal(mixed $value): string
    {
        $decimal = trim((string) $value);

        if (str_contains($decimal, 'e') || str_contains($decimal, 'E')) {
            $decimal = sprintf('%.14F', (float) $decimal);
        }

        $negative = str_starts_with($decimal, '-');
        $decimal = ltrim($decimal, '+-');
        [$integerPart, $fractionPart] = array_pad(explode('.', $decimal, 2), 2, null);

        $integerPart = ltrim((string) $integerPart, '0');
        $integerPart = $integerPart === '' ? '0' : $integerPart;

        $fractionPart = $fractionPart === null ? null : rtrim($fractionPart, '0');

        $normalized = $fractionPart === null || $fractionPart === ''
            ? $integerPart
            : "{$integerPart}.{$fractionPart}";

        return $negative && $normalized !== '0' ? "-{$normalized}" : $normalized;
    }

    /**
     * Normalize a JSON-like payload into a stable array structure.
     */
    private function normalizeJson(mixed $value): array
    {
        if (is_string($value)) {
            try {
                $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $value = [];
            }
        }

        if (! is_array($value)) {
            return [];
        }

        return $this->sortArrayRecursively($value);
    }

    /**
     * Build a canonical string for uniqueness hashing.
     */
    private function canonicalizeForHash(ProjectUserField $field, mixed $normalizedValue): string
    {
        return match ($field->type) {
            ProjectUserFieldType::Json => json_encode(
                $this->sortArrayRecursively($normalizedValue),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            ProjectUserFieldType::Boolean => $normalizedValue ? '1' : '0',
            default => (string) $normalizedValue,
        };
    }

    /**
     * Recursively sort associative arrays to keep JSON hashes stable.
     *
     * @param  array<int|string, mixed>  $value
     * @return array<int|string, mixed>
     */
    private function sortArrayRecursively(array $value): array
    {
        foreach ($value as $key => $nestedValue) {
            if (is_array($nestedValue)) {
                $value[$key] = $this->sortArrayRecursively($nestedValue);
            }
        }

        if (! Arr::isAssoc($value)) {
            return $value;
        }

        ksort($value);

        return $value;
    }
}
