<?php

declare(strict_types=1);

namespace ContentCredentials\Trust;

use Provemark\C2paVerifier\Trust\TrustSettings;

/**
 * The settings a check passes to the verifier, the `trust` value that
 * names them, and, when they could not be built, why (SPEC-004). The
 * reason may echo custom text: it is shown escaped and never logged.
 *
 * Parseable on PHP 8.1, like the rest of the check path.
 */
final class BuiltTrust
{
    public const NONE = 'none';

    public function __construct(
        public readonly ?TrustSettings $settings,
        public readonly string $source,
        public readonly ?string $problem = null,
        /** The SHA-256 of the settings text, kept with the evidence (SPEC-006). */
        public readonly ?string $sha256 = null,
    ) {}

    public static function without(string $problem): self
    {
        return new self(null, self::NONE, $problem);
    }
}
