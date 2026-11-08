<?php

declare(strict_types=1);

namespace ContentCredentials\Tests\Support;

use RuntimeException;

/**
 * SPEC-009: reads the module's template and writes the tests' translations,
 * in plain PHP. Test code only: nothing here ships, and no `.mo` is
 * committed (amendment 1).
 */
final class Gettext
{
    /** The pseudo translation's brackets: a string shown without them was not translated. */
    public const OPEN = '⟦';

    public const CLOSE = '⟧';

    /**
     * The entries of a `.pot` or `.po`, without the header.
     *
     * @return array<string, array{msgstr: string, flags: list<string>}> msgid => entry
     */
    public static function entries(string $po): array
    {
        $entries = [];
        if (trim($po) === '') {
            return $entries;
        }
        foreach (preg_split('/\n\s*\n/', trim(str_replace("\r\n", "\n", $po))) ?: [] as $block) {
            $flags = [];
            $fields = [];
            $current = null;
            foreach (explode("\n", $block) as $line) {
                if (str_starts_with($line, '#,')) {
                    $flags = array_merge($flags, array_map('trim', explode(',', substr($line, 2))));
                } elseif (preg_match('/^(msgid|msgstr) "(.*)"$/', $line, $m) === 1) {
                    $current = $m[1];
                    $fields[$current] = self::unquote($m[2]);
                } elseif ($current !== null && preg_match('/^"(.*)"$/', $line, $m) === 1) {
                    $fields[$current] = ($fields[$current] ?? '').self::unquote($m[1]);
                } elseif ($line !== '' && ! str_starts_with($line, '#')) {
                    throw new RuntimeException('Not a line of a .po: '.$line);
                }
            }
            if (! isset($fields['msgid'], $fields['msgstr'])) {
                throw new RuntimeException("An entry without msgid or msgstr:\n".$block);
            }
            if ($fields['msgid'] === '') {
                continue;
            }
            if (isset($entries[$fields['msgid']])) {
                throw new RuntimeException('A msgid twice: '.$fields['msgid']);
            }
            $entries[$fields['msgid']] = ['msgstr' => $fields['msgstr'], 'flags' => array_values(array_filter($flags))];
        }

        return $entries;
    }

    /**
     * Each msgid between the brackets, its placeholders kept.
     *
     * @param  iterable<string>  $msgids
     * @return array<string, string>
     */
    public static function pseudo(iterable $msgids): array
    {
        $translations = [];
        foreach ($msgids as $msgid) {
            $translations[$msgid] = self::OPEN.$msgid.self::CLOSE;
        }

        return $translations;
    }

    /**
     * A GNU `.mo` file (little-endian, revision 0, no hash table) with a
     * UTF-8 header and the given translations.
     *
     * @param  array<string, string>  $translations  msgid => msgstr
     */
    public static function mo(array $translations): string
    {
        $translations = ['' => "Content-Type: text/plain; charset=UTF-8\n"] + $translations;
        ksort($translations, SORT_STRING);
        $count = count($translations);
        $originals = 28;
        $targets = $originals + 8 * $count;
        $data = $targets + 8 * $count;
        $tables = ['', ''];
        $strings = '';
        foreach ([array_keys($translations), array_values($translations)] as $i => $list) {
            foreach ($list as $string) {
                $string = (string) $string;
                $tables[$i] .= pack('VV', strlen($string), $data + strlen($strings));
                $strings .= $string."\0";
            }
        }

        return pack('VVVVVVV', 0x950412DE, 0, $count, $originals, $targets, 0, $data).$tables[0].$tables[1].$strings;
    }

    /**
     * The fragments of a msgid a reader would see in English: its text
     * between placeholders, trimmed, three characters or more.
     *
     * @return list<string>
     */
    public static function fragments(string $msgid): array
    {
        $parts = preg_split('/%(?:\d+\$)?[sd]/', $msgid) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => mb_strlen($part) >= 3));
    }

    private static function unquote(string $quoted): string
    {
        return strtr($quoted, ['\\\\' => '\\', '\\"' => '"', '\\n' => "\n", '\\t' => "\t"]);
    }
}
