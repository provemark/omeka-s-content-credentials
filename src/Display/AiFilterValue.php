<?php

declare(strict_types=1);

namespace ContentCredentials\Display;

use ContentCredentials\Check\AiStatement;

/**
 * What the browse's AI filter value means (SPEC-007 AC10, AC12): one of the
 * two statements, no filter (absent or empty), or `false` for any other value,
 * which must match nothing.
 */
final class AiFilterValue
{
    public const PARAMETER = 'content_credentials_ai';

    public static function parse(mixed $value): string|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) && in_array($value, AiStatement::VALUES, true) ? $value : false;
    }
}
