<?php

declare(strict_types=1);

// M7.1 probe, never shipped: another module with its own, unprefixed copy of the
// verifier (the VERSION changed so that it can be told apart), autoloaded from
// the moment this file loads. Tells which copy the installed module ends up using.

namespace RogueVerifier;

use Omeka\Module\AbstractModule;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Provemark\\C2paVerifier\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__.'/verifier/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

final class Module extends AbstractModule {}
