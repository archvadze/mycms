<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class AuditLogFormatter
{
    private const REDACTED = '[redacted]';

    private const SENSITIVE_EXACT_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'authorization',
        'cookie',
        'session',
        'webhook_secret',
        'head_scripts',
        'google_analytics',
    ];

    private const SENSITIVE_PATTERNS = [
        'password',
        'secret',
        'token',
        'api_key',
        'credential',
        'authorization',
        'cookie',
        'session',
        'webhook_secret',
        'paypal',
        'resend',
    ];

    public static function changedFields(Activity $activity): string
    {
        $properties = self::changeSet($activity);
        $attributes = Arr::get($properties, 'attributes', []);
        $old = Arr::get($properties, 'old', []);

        $fields = array_unique([
            ...array_keys(is_array($attributes) ? $attributes : []),
            ...array_keys(is_array($old) ? $old : []),
        ]);

        if ($fields === []) {
            return '-';
        }

        return collect($fields)
            ->map(fn(string $field): string => self::isSensitiveKey($field) ? "{$field}: " . self::REDACTED : $field)
            ->implode(', ');
    }

    public static function sanitizedProperties(Activity $activity): array
    {
        return self::sanitize(array_replace_recursive(
            self::changeSet($activity),
            self::propertiesArray($activity)
        ));
    }

    public static function subjectLabel(Activity $activity): string
    {
        if (! $activity->subject_type) {
            return '-';
        }

        return class_basename($activity->subject_type)
            . ($activity->subject_id ? " #{$activity->subject_id}" : '');
    }

    public static function causerLabel(Activity $activity): string
    {
        $causer = $activity->causer;

        if (! $causer) {
            return 'System';
        }

        return $causer->name
            ?? $causer->email
            ?? class_basename($activity->causer_type) . " #{$activity->causer_id}";
    }

    public static function sanitize(array $value, string $parentKey = ''): array
    {
        $sanitized = [];

        foreach ($value as $key => $item) {
            $keyString = (string) $key;

            if (self::isSensitiveKey($keyString) || self::isSensitiveKey($parentKey)) {
                $sanitized[$key] = self::REDACTED;

                continue;
            }

            $sanitized[$key] = is_array($item)
                ? self::sanitize($item, $keyString)
                : $item;
        }

        return $sanitized;
    }

    public static function isSensitiveKey(string $key): bool
    {
        $normalized = str($key)->lower()->replace(['-', ' '], '_')->toString();

        if (in_array($normalized, self::SENSITIVE_EXACT_KEYS, true)) {
            return true;
        }

        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public static function safeSettingValue(string $key, mixed $value): mixed
    {
        if (self::isSensitiveKey($key)) {
            return self::REDACTED;
        }

        return $value;
    }

    private static function propertiesArray(Activity $activity): array
    {
        $properties = $activity->properties;

        if ($properties instanceof Collection) {
            return $properties->toArray();
        }

        return is_array($properties) ? $properties : [];
    }

    private static function changeSet(Activity $activity): array
    {
        $changes = $activity->attribute_changes ?? [];

        if ($changes instanceof Collection) {
            return $changes->toArray();
        }

        return is_array($changes) ? $changes : [];
    }
}
