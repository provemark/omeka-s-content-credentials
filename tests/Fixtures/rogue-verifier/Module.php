<?php

declare(strict_types=1);

// SPEC-008 AC7: another module with its own, unprefixed copy of the verifier in
// verifier/, copied here by the test with another VERSION, autoloaded from the
// moment this file loads.

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
