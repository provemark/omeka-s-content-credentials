<?php

declare(strict_types=1);

namespace ContentCredentials\Check;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Records one media item's check (SPEC-002): first a verdict `error` /
 * `interrupted`, so a check that dies leaves that and never nothing
 * (AC10); then the check's verdict in its place. Nothing leaves this
 * class: a failure, of the store or anything else, goes to $onFailure
 * (AC9, AC11).
 */
final class Recorder
{
    /**
     * @param  Closure(Throwable, int): mixed  $onFailure
     */
    public function __construct(
        private readonly MediaCheck $check,
        private readonly VerdictStore $store,
        private readonly Closure $onFailure,
    ) {}

    public function record(int $mediaId, ?string $path, string $phpVersion): void
    {
        try {
            $this->store->write($mediaId, Verdict::error(Verdict::INTERRUPTED, null, null, new DateTimeImmutable('now', new DateTimeZone('UTC'))));
            $this->store->write($mediaId, $this->check->check($path, $phpVersion));
        } catch (Throwable $e) {
            try {
                ($this->onFailure)($e, $mediaId);
            } catch (Throwable) {
                // Reporting failed too; the upload must still go ahead.
            }
        }
    }
}
