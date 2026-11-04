<?php

declare(strict_types=1);

namespace ContentCredentials\Check;

use Closure;
use ContentCredentials\Requirements;
use ContentCredentials\Trust\BuiltTrust;
use DateTimeImmutable;
use DateTimeZone;
use Provemark\C2paVerifier\Trust\TrustSettings;
use Provemark\C2paVerifier\Verifier\VerificationReport;
use Throwable;

/**
 * From an original on disk to a verdict (SPEC-002), checked with the trust
 * settings of SPEC-004. Never throws: every
 * failure is a verdict with state `error` and a reason. The verifier is
 * loaded only when the PHP version allows it (AC12), and an exception's
 * message is never kept (AC9: it may hold paths).
 */
final class MediaCheck
{
    /**
     * @param  Closure(): string  $loadVerifier  makes the verifier loadable, returns its version
     * @param  Closure(resource, ?TrustSettings): mixed  $verify  verifies a stream with settings, returns its VerificationReport
     * @param  (Closure(): BuiltTrust)|null  $trust  the settings to verify with (SPEC-004); null: none, no trust source
     */
    public function __construct(
        private readonly Closure $loadVerifier,
        private readonly Closure $verify,
        private readonly ?Closure $trust = null,
    ) {}

    public function check(?string $path, string $phpVersion): Verdict
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if (Requirements::phpProblem($phpVersion) !== null) {
            return Verdict::error(Verdict::REQUIREMENTS, null, null, $now);
        }
        try {
            $verifier = ($this->loadVerifier)();
        } catch (Throwable) {
            return Verdict::error(Verdict::EXCEPTION, null, null, $now);
        }
        if ($path === null || ! is_file($path) || ! is_readable($path)) {
            return Verdict::error(Verdict::UNREADABLE, $verifier, null, $now);
        }
        $sha256 = hash_file('sha256', $path);
        $stream = @fopen($path, 'rb');
        if ($sha256 === false || $stream === false) {
            return Verdict::error(Verdict::UNREADABLE, $verifier, null, $now);
        }
        $trust = $this->trust();
        try {
            $report = ($this->verify)($stream, $trust?->settings);

            return $report instanceof VerificationReport
                ? Verdict::fromReport($report, $sha256, $verifier, $now, $trust?->source)
                : Verdict::error(Verdict::EXCEPTION, $verifier, $sha256, $now);
        } catch (Throwable) {
            return Verdict::error(Verdict::EXCEPTION, $verifier, $sha256, $now);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Settings that cannot be built mean a check without them, never an
     * error (SPEC-004 AC6).
     */
    private function trust(): ?BuiltTrust
    {
        if ($this->trust === null) {
            return null;
        }
        try {
            return ($this->trust)();
        } catch (Throwable) {
            return BuiltTrust::without('the trust settings could not be built');
        }
    }
}
