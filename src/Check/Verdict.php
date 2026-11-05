<?php

declare(strict_types=1);

namespace ContentCredentials\Check;

use DateTimeImmutable;
use Provemark\C2paVerifier\Verifier\VerificationReport;

/**
 * One media item's verdict, as SPEC-002 stores it, with the source of the
 * trust settings it was checked with (SPEC-004; null for an error before
 * verification).
 *
 * Its state is the verifier's ValidationState value, or one of the two the
 * module derives: `none` (no manifest in the file) and `error` (not
 * checked, with a reason). Text from the file is kept exactly as the
 * verifier gives it; whoever shows it escapes it.
 *
 * Parseable on PHP 8.1 (SPEC-002 AC12): no readonly class, no typed
 * constants.
 */
final class Verdict
{
    public const NONE = 'none';

    public const ERROR = 'error';

    public const INTERRUPTED = 'interrupted';

    public const UNREADABLE = 'unreadable';

    public const EXCEPTION = 'exception';

    public const REQUIREMENTS = 'requirements';

    /**
     * @param  list<string>  $codes
     */
    private function __construct(
        public readonly string $state,
        public readonly ?string $format,
        public readonly ?string $signerIssuer,
        public readonly ?string $signerCommonName,
        public readonly ?string $signedAt,
        public readonly array $codes,
        public readonly ?string $remoteManifestUrl,
        public readonly ?string $reason,
        public readonly ?string $verifier,
        public readonly ?string $checkedSha256,
        public readonly DateTimeImmutable $checkedAt,
        public readonly ?string $trust = null,
        public readonly ?string $aiStatement = null,
    ) {}

    /**
     * @param  string|null  $trust  the source of the trust settings used (SPEC-004)
     */
    public static function fromReport(VerificationReport $report, string $sha256, string $verifier, DateTimeImmutable $checkedAt, ?string $trust = null): self
    {
        $state = $report->hasManifest ? $report->result->state->value : self::NONE;
        $signature = $report->hasManifest ? ($report->signatureInfo ?? []) : [];
        // Only a file that verifies may say this (SPEC-007).
        $aiStatement = $state === 'Trusted' || $state === 'Valid' ? AiStatement::read($report->toArray()) : null;
        $codes = [];
        if ($state === 'Invalid') {
            $statuses = $report->toArray()['validation_status'] ?? [];
            foreach (is_array($statuses) ? $statuses : [] as $status) {
                if (is_array($status) && is_string($status['code'] ?? null)) {
                    $codes[] = $status['code'];
                }
            }
        }

        return new self(
            $state,
            $report->format,
            self::text($signature, 'issuer'),
            self::text($signature, 'common_name'),
            self::text($signature, 'time'),
            $codes,
            $report->remoteManifestUrl,
            null,
            $verifier,
            $sha256,
            $checkedAt,
            $trust,
            $aiStatement,
        );
    }

    public static function error(string $reason, ?string $verifier, ?string $sha256, DateTimeImmutable $checkedAt): self
    {
        return new self(self::ERROR, null, null, null, null, [], null, $reason, $verifier, $sha256, $checkedAt);
    }

    /**
     * @param  array<mixed>  $values
     */
    private static function text(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
