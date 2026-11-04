<?php

declare(strict_types=1);

namespace ContentCredentials\Display;

use ContentCredentials\Check\Verdict;

/**
 * What a stored row says, in the module's words (SPEC-003; the trust line
 * of SPEC-004). It never shows
 * a verdict the row does not hold: a row whose state, codes or reason is
 * not one SPEC-002 writes is "Result unreadable", and no row is "Not
 * checked".
 *
 * Lines are pairs of an English source string (a sprintf format, for
 * `translate`) and its values; each value has been through Text::visible()
 * and is still to be escaped by whoever outputs it.
 */
final class Presentation
{
    public const TRUSTED = 'Trusted';

    public const VALID = 'Valid';

    public const INVALID = 'Invalid';

    public const STATES = [self::TRUSTED, self::VALID, self::INVALID, Verdict::NONE, Verdict::ERROR];

    public const HEADLINES = [
        self::TRUSTED => 'Verified: trusted signer',
        self::VALID => 'Intact: signer not trusted',
        self::INVALID => 'Does not verify',
        Verdict::NONE => 'No Content Credentials',
        Verdict::ERROR => 'Could not be checked',
    ];

    public const NOT_CHECKED = 'Not checked';

    public const UNREADABLE = 'Result unreadable';

    private const REASONS = [
        Verdict::INTERRUPTED => 'Reason: the check did not finish',
        Verdict::UNREADABLE => 'Reason: the file could not be read',
        Verdict::EXCEPTION => 'Reason: the verifier failed',
        Verdict::REQUIREMENTS => "Reason: this server's PHP is older than 8.3",
    ];

    /**
     * @param  list<array{string, list<string>}>  $lines
     */
    private function __construct(
        private readonly string $headline,
        private readonly array $lines,
    ) {}

    /**
     * @param  array<mixed>|null  $row  the table's columns, or null for no row; any other
     *                                  array is read as untrusted and may be unreadable
     */
    public static function of(?array $row): self
    {
        if ($row === null) {
            return new self(self::NOT_CHECKED, []);
        }
        $state = $row['state'] ?? null;
        $codes = self::codes($row['codes'] ?? null);
        $reason = $row['reason'] ?? null;
        $trust = self::trustLine($row['trust'] ?? null);
        if (! is_string($state) || ! in_array($state, self::STATES, true) || $codes === null || $trust === false
            || ($state === Verdict::ERROR && (! is_string($reason) || ! isset(self::REASONS[$reason])))) {
            return new self(self::UNREADABLE, []);
        }

        $lines = [];
        if ($state === Verdict::ERROR) {
            $lines[] = [self::REASONS[$reason], []];
        }
        if ($state === self::INVALID) {
            $lines[] = ['Codes:', []];
            foreach ($codes as $code) {
                $lines[] = ['%s', [Text::visible($code)]];
            }
        }
        $url = self::text($row, 'remote_manifest_url');
        if ($state === Verdict::NONE && $url !== null) {
            $lines[] = ['Refers to Content Credentials elsewhere (not checked): %s', [$url]];
        }
        if (in_array($state, [self::TRUSTED, self::VALID, self::INVALID], true)) {
            $name = self::text($row, 'signer_common_name');
            $issuer = self::text($row, 'signer_issuer');
            if ($name !== null || $issuer !== null) {
                $lines[] = ['Signed by %1$s (%2$s)', [$name ?? '', $issuer ?? '']];
            }
            $signedAt = self::text($row, 'signed_at');
            if ($signedAt !== null) {
                $lines[] = ['Signed at %s', [$signedAt]];
            }
        }
        $checkedAt = self::text($row, 'checked_at');
        $verifier = self::text($row, 'verifier');
        if ($checkedAt !== null && $verifier !== null) {
            $lines[] = ['Checked %1$s UTC with c2pa-verifier %2$s', [$checkedAt, $verifier]];
        }
        if ($trust !== null) {
            $lines[] = $trust;
        }

        return new self(self::HEADLINES[$state], $lines);
    }

    /**
     * The line naming the trust source (SPEC-004 AC7, SPEC-003 amendment
     * 1): null for no source (NULL), false for a value the module never
     * writes, which makes the row unreadable.
     *
     * @return array{string, list<string>}|false|null
     */
    private static function trustLine(mixed $trust): array|false|null
    {
        if ($trust === null) {
            return null;
        }
        if (! is_string($trust)) {
            return false;
        }
        if (preg_match('/^c2pa-(\d{4}-\d{2}-\d{2})(\+digicert)?$/', $trust, $m) === 1) {
            return isset($m[2])
                ? ['Trust: the C2PA trust list of %s, with DigiCert timestamps', [$m[1]]]
                : ['Trust: the C2PA trust list of %s', [$m[1]]];
        }

        return match ($trust) {
            'custom' => ['Trust: custom trust settings', []],
            'none' => ['Trust: none (checked without a trust list)', []],
            default => false,
        };
    }

    /** The headline: an English source string, for `translate`. */
    public function headline(): string
    {
        return $this->headline;
    }

    /**
     * @return list<array{string, list<string>}>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * @return list<string>|null null when the column is not a JSON list of strings
     */
    private static function codes(mixed $json): ?array
    {
        if (! is_string($json)) {
            return null;
        }
        $codes = json_decode($json, true);
        if (! is_array($codes) || ! array_is_list($codes)) {
            return null;
        }
        $strings = [];
        foreach ($codes as $code) {
            if (! is_string($code)) {
                return null;
            }
            $strings[] = $code;
        }

        return $strings;
    }

    /**
     * @param  array<mixed>  $row
     */
    private static function text(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;

        return is_string($value) ? Text::visible($value) : null;
    }
}
