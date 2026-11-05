<?php

declare(strict_types=1);

namespace ContentCredentials\Check;

/**
 * What a check keeps next to its verdict (SPEC-006, ADR-0005): the
 * verifier's evidence document as it wrote it, and the SHA-256 of the
 * settings text it was judged with (null without settings). The other
 * columns of its row come from the verdict.
 *
 * Not a readonly class: this file is loaded below PHP 8.3 too, where the
 * check only says that it cannot run (SPEC-002 AC12).
 */
final class Evidence
{
    public function __construct(
        public readonly string $document,
        public readonly ?string $trustSha256,
    ) {}
}
