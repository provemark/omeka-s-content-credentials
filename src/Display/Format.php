<?php

declare(strict_types=1);

namespace ContentCredentials\Display;

/**
 * Fills a translated format, failing closed (SPEC-009 AC7): a translation
 * whose placeholders the arguments cannot fill would throw PHP's ValueError
 * and take the whole page down (step 63), so the English source is filled
 * in instead. Escaping stays the caller's job.
 */
final class Format
{
    /**
     * @param  string  $translated  the format as the translator gave it
     * @param  string  $source  the module's English format, which the arguments fit
     * @param  list<string>  $arguments
     */
    public static function fill(string $translated, string $source, array $arguments): string
    {
        try {
            return vsprintf($translated, $arguments);
        } catch (\ValueError) {
            return vsprintf($source, $arguments);
        }
    }
}
