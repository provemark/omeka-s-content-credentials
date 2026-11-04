<?php

declare(strict_types=1);

namespace ContentCredentials\Display;

/**
 * What the browse filter's query value means (SPEC-003 AC9, AC10): one of
 * the five stored states, no filter (absent or empty), or `false` for a
 * value that is not a state, which must match nothing.
 */
final class FilterValue
{
    public const PARAMETER = 'content_credentials_state';

    public static function parse(mixed $value): string|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) && in_array($value, Presentation::STATES, true) ? $value : false;
    }
}
