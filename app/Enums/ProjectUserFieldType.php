<?php

namespace App\Enums;

enum ProjectUserFieldType: string
{
    case StringType = 'string';
    case Text = 'text';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Date = 'date';
    case DateTime = 'datetime';
    case Enum = 'enum';
    case Email = 'email';
    case Url = 'url';
    case Phone = 'phone';
    case Uuid = 'uuid';
    case Json = 'json';

    public function label(): string
    {
        return match ($this) {
            self::StringType => 'Short Text',
            self::Text => 'Long Text',
            self::Integer => 'Integer',
            self::Decimal => 'Decimal',
            self::Boolean => 'Boolean',
            self::Date => 'Date',
            self::DateTime => 'Date & Time',
            self::Enum => 'Enum',
            self::Email => 'Email',
            self::Url => 'URL',
            self::Phone => 'Phone',
            self::Uuid => 'UUID',
            self::Json => 'JSON',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }

    public function storageColumn(): string
    {
        return match ($this) {
            self::StringType,
            self::Enum,
            self::Email,
            self::Url,
            self::Phone,
            self::Uuid => 'value_string',
            self::Text => 'value_text',
            self::Integer => 'value_integer',
            self::Decimal => 'value_decimal',
            self::Boolean => 'value_boolean',
            self::Date => 'value_date',
            self::DateTime => 'value_datetime',
            self::Json => 'value_json',
        };
    }

    public function supportsUniqueConstraint(): bool
    {
        return ! in_array($this, [self::Boolean, self::Json], true);
    }

    public function isStringLike(): bool
    {
        return in_array($this, [
            self::StringType,
            self::Text,
            self::Enum,
            self::Email,
            self::Url,
            self::Phone,
            self::Uuid,
        ], true);
    }
}
