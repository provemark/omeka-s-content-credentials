<?php

declare(strict_types=1);

namespace ContentCredentials;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;

/**
 * The module's entry point for Omeka S.
 *
 * Omeka S finds this class as "<module directory>\Module", so the namespace
 * must equal the directory name, ContentCredentials. It autoloads src/ under
 * that namespace itself; this file loads nothing else.
 *
 * Installing refuses PHP below 8.3 with a message on the modules page
 * (SPEC-001; module.ini cannot state a PHP version).
 *
 * Keep this file parseable on PHP 8.1: Omeka S 4.2 runs from 8.1 and loads it
 * before the module can say it needs 8.3 (ADR-0001).
 */
final class Module extends AbstractModule
{
    /**
     * @return array<mixed>
     */
    public function getConfig(): array
    {
        $config = require __DIR__.'/config/module.config.php';
        if (! is_array($config)) {
            throw new \UnexpectedValueException('config/module.config.php must return an array.');
        }

        return $config;
    }

    public function install(ServiceLocatorInterface $serviceLocator): void
    {
        // A module that is not installed is not loaded by Omeka S, so its src/
        // is not on the autoloader yet while install() runs (measured, step 07).
        require_once __DIR__.'/src/Requirements.php';
        $problem = Requirements::phpProblem(PHP_VERSION);
        if ($problem !== null) {
            throw new ModuleCannotInstallException($problem);
        }
    }
}
