<?php

declare(strict_types=1);

namespace ContentCredentials\Omeka;

/**
 * Makes provemark/c2pa-verifier loadable, and nothing else of the module's
 * vendor/: no Composer autoload.php may be loaded in Omeka S, because the
 * module's vendor/ shares packages with Omeka's at other versions and its
 * platform check kills PHP 8.2 (step 11, SPEC-002 AC13).
 *
 * The namespace string is rewritten by Strauss in the release build (step
 * 11); the directory is the caller's, so the build can point it elsewhere.
 */
final class VerifierLoader
{
    private const PREFIX = 'Provemark\\C2paVerifier\\';

    private static bool $registered = false;

    public static function register(string $sourceDirectory): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        spl_autoload_register(static function (string $class) use ($sourceDirectory): void {
            if (! str_starts_with($class, self::PREFIX)) {
                return;
            }
            $file = $sourceDirectory.'/'.str_replace('\\', '/', substr($class, strlen(self::PREFIX))).'.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }
}
