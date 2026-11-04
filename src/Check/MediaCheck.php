<?php

declare(strict_types=1);

namespace ContentCredentials\Check;

use Closure;
use ContentCredentials\Requirements;
use DateTimeImmutable;
use DateTimeZone;
use Provemark\C2paVerifier\Verifier\VerificationReport;
use Throwable;

/**
 * From an original on disk to a verdict (SPEC-002). Never throws: every
 * failure is a verdict with state `error` and a reason. The verifier is
 * loaded only when the PHP version allows it (AC12), and an exception's
 * message is never kept (AC9: it may hold paths).
 */
final class MediaCheck
{
    /**
     * @param  Closure(): string  $loadVerifier  makes the verifier loadable, returns its version
     * @param  Closure(resource): mixed  $verify  verifies a stream, returns its VerificationReport
     */
    public function __construct(
        private readonly Closure $loadVerifier,
        private readonly Closure $verify,
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
        try {
            $report = ($this->verify)($stream);

            return $report instanceof VerificationReport
                ? Verdict::fromReport($report, $sha256, $verifier, $now)
                : Verdict::error(Verdict::EXCEPTION, $verifier, $sha256, $now);
        } catch (Throwable) {
            return Verdict::error(Verdict::EXCEPTION, $verifier, $sha256, $now);
        } finally {
            fclose($stream);
        }
    }
}
