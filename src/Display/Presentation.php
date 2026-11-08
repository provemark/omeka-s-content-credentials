<?php

declare(strict_types=1);

namespace ContentCredentials\Display;

use ContentCredentials\Check\AiStatement;
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
        self::TRUSTED => 'Verified: trusted signer', // @translate
        self::VALID => 'Intact: signer not trusted', // @translate
        self::INVALID => 'Does not verify', // @translate
        Verdict::NONE => 'No Content Credentials', // @translate
        Verdict::ERROR => 'Could not be checked', // @translate
    ];

    /** The labels of SPEC-007: English source strings, for `translate`. */
    public const AI_LABELS = [
        AiStatement::GENERATED => 'AI-generated (signed)', // @translate
        AiStatement::EDITED => 'AI-edited (signed)', // @translate
    ];

    public const NOT_CHECKED = 'Not checked'; // @translate

    public const UNREADABLE = 'Result unreadable'; // @translate

    private const REASONS = [
        Verdict::INTERRUPTED => 'Reason: the check did not finish', // @translate
        Verdict::UNREADABLE => 'Reason: the file could not be read', // @translate
        Verdict::EXCEPTION => 'Reason: the verifier failed', // @translate
        Verdict::REQUIREMENTS => "Reason: this server's PHP is older than 8.3", // @translate
    ];

    /**
     * @param  list<array{string, list<string>}>  $lines
     */
    private function __construct(
        private readonly string $headline,
        private readonly array $lines,
        private readonly ?string $aiLabel = null,
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
        $statement = $row['ai_statement'] ?? null;
        if (! is_string($state) || ! in_array($state, self::STATES, true) || $codes === null || $trust === false
            || ($statement !== null && (! is_string($statement) || ! isset(self::AI_LABELS[$statement])))
            || ($state === Verdict::ERROR && (! is_string($reason) || ! isset(self::REASONS[$reason])))) {
            return new self(self::UNREADABLE, []);
        }

        $lines = [];
        if ($state === Verdict::ERROR) {
            $lines[] = [self::REASONS[$reason], []];
        }
        if ($state === self::INVALID) {
            $lines[] = ['Codes:', []]; // @translate
            foreach ($codes as $code) {
                $lines[] = ['%s', [Text::visible($code)]];
            }
        }
        $url = self::text($row, 'remote_manifest_url');
        if ($state === Verdict::NONE && $url !== null) {
            $lines[] = ['Refers to Content Credentials elsewhere (not checked): %s', [$url]]; // @translate
        }
        if (in_array($state, [self::TRUSTED, self::VALID, self::INVALID], true)) {
            // SPEC-003 amendment 5: name what is present, never an empty place.
            $name = self::present($row, 'signer_common_name');
            $issuer = self::present($row, 'signer_issuer');
            if ($name !== null && $issuer !== null) {
                $lines[] = ['Signed by %1$s (%2$s)', [$name, $issuer]]; // @translate
            } elseif ($name !== null || $issuer !== null) {
                $lines[] = ['Signed by %s', [$name ?? $issuer]]; // @translate
            }
            $signedAt = self::text($row, 'signed_at');
            if ($signedAt !== null) {
                $lines[] = ['Signed at %s', [$signedAt]]; // @translate
            }
        }
        $checkedAt = self::text($row, 'checked_at');
        $verifier = self::text($row, 'verifier');
        if ($checkedAt !== null && $verifier !== null) {
            $lines[] = ['Checked %1$s UTC with c2pa-verifier %2$s', [$checkedAt, $verifier]]; // @translate
        }
        if ($trust !== null) {
            $lines[] = $trust;
        }

        // The label follows the verdict, whatever the column holds (SPEC-007 AC9).
        $verifies = $state === self::TRUSTED || $state === self::VALID;

        return new self(self::HEADLINES[$state], $lines, $verifies && is_string($statement) ? self::AI_LABELS[$statement] : null);
    }

    /**
     * The line naming the trust source (SPEC-004 AC7, SPEC-003 amendment
     * 1): null for no source (NULL), false for a value the module never
     * writes, which makes the row unreadable.
     *
     * @return array{string, list<string>}|false|null
     */
    public static function trustLine(mixed $trust): array|false|null
    {
        if ($trust === null) {
            return null;
        }
        if (! is_string($trust)) {
            return false;
        }
        if (preg_match('/^c2pa-(\d{4}-\d{2}-\d{2})(\+digicert)?$/', $trust, $m) === 1) {
            return isset($m[2])
                ? ['Trust: the C2PA trust list of %s, with DigiCert timestamps', [$m[1]]] // @translate
                : ['Trust: the C2PA trust list of %s', [$m[1]]]; // @translate
        }

        return match ($trust) {
            'custom' => ['Trust: custom trust settings', []], // @translate
            'none' => ['Trust: none (checked without a trust list)', []], // @translate
            default => false,
        };
    }

    /** The headline: an English source string, for `translate`. */
    public function headline(): string
    {
        return $this->headline;
    }

    /** "AI-generated (signed)", "AI-edited (signed)" or null: for `translate`. */
    public function aiLabel(): ?string
    {
        return $this->aiLabel;
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
     * A value made visible, or null when it is missing or empty after
     * trimming (amendment 5).
     *
     * @param  array<mixed>  $row
     */
    private static function present(array $row, string $column): ?string
    {
        $value = $row[$column] ?? null;

        // Trimmed before Text::visible(), which would make a tab a visible U+FFFD.
        return is_string($value) && trim($value) !== '' ? Text::visible($value) : null;
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
