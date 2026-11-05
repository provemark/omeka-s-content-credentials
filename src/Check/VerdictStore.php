<?php

declare(strict_types=1);

namespace ContentCredentials\Check;

/**
 * Where a verdict is kept, one per media item; a later write replaces it
 * (SPEC-002). Evidence, when given, is added and stored together with the
 * verdict, or neither is (SPEC-006, ADR-0005).
 */
interface VerdictStore
{
    public function write(int $mediaId, Verdict $verdict, ?Evidence $evidence = null): void;
}
