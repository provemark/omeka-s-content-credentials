<?php

declare(strict_types=1);

namespace ContentCredentials;

use Omeka\Module\AbstractModule;

/**
 * The module's entry point for Omeka S (M0.5: the empty skeleton).
 *
 * Omeka S finds this class as "<module directory>\Module", so the namespace
 * must equal the directory name, ContentCredentials. It autoloads src/ under
 * that namespace itself; this file loads nothing else.
 *
 * Keep this file parseable on PHP 8.1: Omeka S 4.2 runs from 8.1 and loads it
 * before the module can say it needs 8.3 (ADR-0001).
 */
final class Module extends AbstractModule
{
    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return require __DIR__.'/config/module.config.php';
    }
}
