<?php

declare(strict_types=1);

namespace ContentCredentials\Display;

/**
 * Makes untrusted text from a file visible before it is escaped (SPEC-003
 * AC8): C0 and C1 control characters, DEL, and the Unicode direction
 * controls (U+200E, U+200F, U+202A–U+202E, U+2066–U+2069) each become
 * U+FFFD, so nothing can hide or reorder what an administrator reads.
 * Bytes that are not UTF-8 are replaced first. Escaping stays the view's
 * job.
 */
final class Text
{
    private const INVISIBLE = '/[\x{0000}-\x{001F}\x{007F}-\x{009F}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    public static function visible(string $untrusted): string
    {
        $utf8 = mb_check_encoding($untrusted, 'UTF-8') ? $untrusted : mb_scrub($untrusted, 'UTF-8');

        return preg_replace(self::INVISIBLE, "\u{FFFD}", $utf8) ?? '';
    }
}
