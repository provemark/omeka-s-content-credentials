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
 * 11). Strauss leaves paths alone, so the loader looks which layout it is in
 * (SPEC-008): the zip has vendor-prefixed/, the repository vendor/.
 */
final class VerifierLoader
{
    private const PREFIX = 'Provemark\\C2paVerifier\\';

    private static bool $registered = false;

    /** Which source directory the module runs on: the prefixed one when it exists. */
    public static function sourceDirectory(string $moduleDirectory): string
    {
        $source = '/provemark/c2pa-verifier/src';
        $prefixed = $moduleDirectory.'/vendor-prefixed'.$source;

        return is_dir($prefixed) ? $prefixed : $moduleDirectory.'/vendor'.$source;
    }

    public static function register(string $moduleDirectory): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        $sourceDirectory = self::sourceDirectory($moduleDirectory);
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
