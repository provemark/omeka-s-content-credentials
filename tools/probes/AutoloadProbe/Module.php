<?php

declare(strict_types=1);

namespace AutoloadProbe;

use Laminas\EventManager\SharedEventManagerInterface;
use Omeka\Module\AbstractModule;

// Measurement only (M1.3): what a module does to Omeka S when it loads a
// whole Composer vendor/ next to Omeka's own.
require_once dirname(__DIR__).'/ContentCredentials/vendor/autoload.php';

final class Module extends AbstractModule
{
    public function attachListeners(SharedEventManagerInterface $shared): void
    {
        $shared->attach('*', 'view.layout', static function (): void {
            $from = [];
            foreach (['Psr\Container\ContainerInterface', 'Psr\Log\LoggerInterface', 'Webmozart\Assert\Assert', 'Symfony\Component\Console\Application'] as $class) {
                $from[$class] = class_exists($class, false) || interface_exists($class, false)
                    ? (string) (new \ReflectionClass($class))->getFileName()
                    : 'not loaded';
            }
            file_put_contents('/tmp/autoload-probe.log', json_encode(['uri' => $_SERVER['REQUEST_URI'] ?? 'cli', 'from' => $from], JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND);
        });
    }
}
