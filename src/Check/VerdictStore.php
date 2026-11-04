<?php

declare(strict_types=1);

namespace ContentCredentials\Check;

/** Where a verdict is kept, one per media item; a later write replaces it (SPEC-002). */
interface VerdictStore
{
    public function write(int $mediaId, Verdict $verdict): void;
}
