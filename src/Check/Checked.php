<?php

declare(strict_types=1);

namespace ContentCredentials\Check;

/**
 * The outcome of a check: its verdict and, for `Trusted`, `Valid` and
 * `Invalid`, its evidence. `none` and `error` keep none (SPEC-006 AC3, AC4).
 */
final class Checked
{
    public function __construct(
        public readonly Verdict $verdict,
        public readonly ?Evidence $evidence = null,
    ) {}
}
