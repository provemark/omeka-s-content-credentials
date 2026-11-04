<?php

declare(strict_types=1);

namespace ContentCredentials;

/**
 * What the module needs from the server (SPEC-001).
 *
 * Omeka S 4.2 runs from PHP 8.1 and module.ini cannot state a PHP version,
 * so the module checks it itself when it is installed (ADR-0001). This file
 * runs on the PHP it refuses: keep it parseable on PHP 8.1 (no typed class
 * constants, no 8.2+ syntax) and free of Omeka S.
 */
final class Requirements
{
    public const MINIMUM_PHP = '8.3.0';

    /**
     * The message to show when $phpVersion is older than MINIMUM_PHP or is not
     * a version at all (major.minor.patch, as PHP_VERSION always is); null
     * when it passes. Fails closed: what cannot be read is refused.
     */
    public static function phpProblem(string $phpVersion): ?string
    {
        if (preg_match('/^\d+\.\d+\.\d+/', $phpVersion) === 1
            && version_compare($phpVersion, self::MINIMUM_PHP, '>=')
        ) {
            return null;
        }

        return sprintf(
            'Content Credentials (C2PA) needs PHP %s or later; this server runs PHP %s.',
            '8.3',
            $phpVersion,
        );
    }
}
