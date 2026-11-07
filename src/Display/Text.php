<?php

declare(strict_types=1);

namespace ContentCredentials\Display;

/**
 * Makes untrusted text from a file visible before it is escaped (SPEC-003
 * AC8, amendment 4): every character of the Unicode categories Cc (control)
 * and Cf (format) becomes U+FFFD, so nothing can hide or reorder what an
 * administrator reads. Cf holds the direction controls, the zero-width
 * characters, the byte order mark and the tag characters. Bytes that are not
 * UTF-8 are replaced first. Escaping stays the view's job.
 */
final class Text
{
    private const INVISIBLE = '/[\p{Cc}\p{Cf}]/u';

    public static function visible(string $untrusted): string
    {
        $utf8 = mb_check_encoding($untrusted, 'UTF-8') ? $untrusted : mb_scrub($untrusted, 'UTF-8');

        return preg_replace(self::INVISIBLE, "\u{FFFD}", $utf8) ?? '';
    }
}
