<?php

declare(strict_types=1);

/*
 * SPEC-008: what the tests on the built zip share. tools/env/release.sh builds
 * the zip and starts the clean Omeka S they run against; COMPOSE_FILE and the
 * ports come from that script, so OmekaInstallation finds this environment.
 * Work happens under build/tests/, which no static check reads.
 */

use ContentCredentials\Tests\Integration\Support\OmekaInstallation;
use ContentCredentials\Tests\Support\VerifierCli;

const RELEASE_MODULE = 'ContentCredentials';

function releaseRoot(): string
{
    return dirname(__DIR__, 2);
}

function releaseWork(string $name): string
{
    $directory = releaseRoot().'/build/tests/'.$name;
    releaseRun(['rm', '-rf', $directory]);
    mkdir($directory, 0777, true);

    return $directory;
}

/**
 * @param  list<string>  $command
 * @return array{int, string, string} exit code, standard output, standard error
 */
function releaseExec(array $command, ?string $directory = null): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory);
    if (! is_resource($process)) {
        throw new RuntimeException('Could not start '.implode(' ', $command));
    }
    $out = (string) stream_get_contents($pipes[1]);
    $err = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $out, $err];
}

/** @param list<string> $command */
function releaseRun(array $command, ?string $directory = null): string
{
    [$exit, $out, $err] = releaseExec($command, $directory);
    if ($exit !== 0) {
        throw new RuntimeException('Exit '.$exit.' from '.implode(' ', $command).': '.$err);
    }

    return $out;
}

function releaseVersion(): string
{
    $ini = parse_ini_file(releaseRoot().'/config/module.ini', true);
    $version = is_array($ini) && is_array($ini['info'] ?? null) ? ($ini['info']['version'] ?? null) : null;

    return is_string($version) ? $version : throw new RuntimeException('No version in config/module.ini.');
}

function releaseZip(): string
{
    return releaseRoot().'/build/ContentCredentials-'.releaseVersion().'.zip';
}

/** The zip, unpacked once for all tests of a run. */
function releaseUnpacked(): string
{
    static $module = null;
    if (is_string($module)) {
        return $module;
    }
    $directory = releaseWork('unpacked');
    releaseRun(['unzip', '-q', releaseZip(), '-d', $directory]);

    return $module = $directory.'/'.RELEASE_MODULE;
}

/**
 * Every file below a directory, with its bytes' SHA-256.
 *
 * @return array<string, string> relative path => hash
 */
function releaseHashes(string $directory): array
{
    $hashes = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file instanceof SplFileInfo && $file->isFile()) {
            $hashes[substr($file->getPathname(), strlen($directory) + 1)] = (string) hash_file('sha256', $file->getPathname());
        }
    }
    ksort($hashes);

    return $hashes;
}

/** The PHP 8.3 installation of the clean environment, logged in, the module active. */
function releaseOmeka(): OmekaInstallation
{
    $omeka = OmekaInstallation::php83();
    $omeka->logIn();
    if ($omeka->moduleRow(RELEASE_MODULE) === null) {
        $omeka->moduleAction('install', RELEASE_MODULE);
    }

    return $omeka;
}

function releaseUpload(OmekaInstallation $omeka, string $fixture): int
{
    $types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $type = $types[pathinfo($fixture, PATHINFO_EXTENSION)] ?? throw new RuntimeException("No type for {$fixture}");
    $response = $omeka->uploadThroughAdminForm(VerifierCli::fixture($fixture), $fixture, $type);

    return $omeka->mediaIdsOf(OmekaInstallation::itemIdFrom($response))[0] ?? throw new RuntimeException("No media for {$fixture}");
}

/** @param array<string, string> $fields */
function releaseConfigure(OmekaInstallation $omeka, array $fields): int
{
    $page = '/admin/module/configure?id='.RELEASE_MODULE;
    $csrf = 'module_'.RELEASE_MODULE.'_configure_csrf';
    preg_match('#name="'.$csrf.'" value="([^"]+)"#', $omeka->get($page)->body, $m);

    return $omeka->post($page, $fields + [$csrf => $m[1] ?? ''])->status;
}

function releaseLatestJob(OmekaInstallation $omeka): int
{
    return (int) $omeka->sql("SELECT IFNULL(MAX(id), 0) FROM job WHERE class = 'ContentCredentials\\\\Omeka\\\\RecheckJob'");
}
