<?php

declare(strict_types=1);

namespace ContentCredentials\Check;

use RuntimeException;

/**
 * Thrown to end a run as failed (SPEC-005, AC15). Omeka's dispatcher then
 * sets the job to `error` and logs this exception as a string, which site
 * administrators can read. So that string is this one line alone: no class,
 * path or trace (AC7). What went wrong is in the run's own lines before it.
 */
final class RunFailed extends RuntimeException
{
    private const LINE = 'The run failed.';

    public function __construct()
    {
        parent::__construct(self::LINE);
    }

    public function __toString(): string
    {
        return self::LINE;
    }
}
