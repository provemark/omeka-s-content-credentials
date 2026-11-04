<?php

declare(strict_types=1);

/*
 * SPEC-001: installing and uninstalling the module through the admin
 * interface of the pinned Omeka S, in the Docker environment of tools/env/.
 * Each test sets up its own starting state (installed or not), so they do
 * not depend on their order.
 */

use ContentCredentials\Tests\Integration\Support\OmekaInstallation;

const SPEC001_MODULE = 'ContentCredentials';

function spec001Php83(): OmekaInstallation
{
    $omeka = OmekaInstallation::php83();
    $omeka->logIn();

    return $omeka;
}

function spec001Uninstalled(OmekaInstallation $omeka): void
{
    if ($omeka->moduleRow(SPEC001_MODULE) !== null) {
        $omeka->moduleAction('uninstall', SPEC001_MODULE);
    }
    expect($omeka->moduleRow(SPEC001_MODULE))->toBeNull();
}

function spec001Installed(OmekaInstallation $omeka): void
{
    spec001Uninstalled($omeka);
    $omeka->moduleAction('install', SPEC001_MODULE);
    expect($omeka->moduleRow(SPEC001_MODULE)['is_active'] ?? null)->toBe('1');
}

function spec001IniVersion(): string
{
    $ini = parse_ini_file(dirname(__DIR__, 2).'/config/module.ini', true);
    $version = is_array($ini) && is_array($ini['info'] ?? null) ? ($ini['info']['version'] ?? null) : null;

    return is_string($version) ? $version : throw new RuntimeException('No version in config/module.ini.');
}

it('AC1: installing adds one row and one empty table', function (): void {
    $omeka = spec001Php83();
    spec001Uninstalled($omeka);
    $before = $omeka->snapshot();

    $response = $omeka->moduleAction('install', SPEC001_MODULE);

    expect($response->status)->toBe(302)
        // amendment 2: a configurable module is sent to its configure page
        ->and($response->location)->toEndWith('/admin/module/configure?id='.SPEC001_MODULE)
        ->and(OmekaInstallation::messages($omeka->get('/admin/module')->body))->toContain('The module was successfully installed')
        ->and($omeka->moduleRow(SPEC001_MODULE))->toBe(['id' => SPEC001_MODULE, 'is_active' => '1', 'version' => spec001IniVersion()])
        // amendment 1: SPEC-002's table, empty, and otherwise only the
        // module row (the module table's count goes from 0 to 1, its one
        // row added at the end)
        ->and($omeka->sql('SELECT COUNT(*) FROM content_credentials_verdict'))->toBe('0')
        ->and($omeka->snapshot(['content_credentials_verdict']))->toBe(str_replace("\nmodule 0\n", "\nmodule 1\n", $before).SPEC001_MODULE."\t1\t".spec001IniVersion());
})->group('SPEC-001');

it('AC2: an active module breaks no page', function (): void {
    $omeka = spec001Php83();
    spec001Installed($omeka);

    foreach (['/admin', '/admin/item', '/admin/media', '/admin/module'] as $path) {
        expect($omeka->get($path)->status)->toBe(200, $path);
    }
    expect(OmekaInstallation::php83()->get('/')->status)->toBe(200, '/ as a visitor');
})->group('SPEC-001');

it('AC3: deactivating and activating', function (): void {
    $omeka = spec001Php83();
    spec001Installed($omeka);
    $active = $omeka->snapshot();

    $omeka->moduleAction('deactivate', SPEC001_MODULE);
    expect($omeka->moduleRow(SPEC001_MODULE)['is_active'] ?? null)->toBe('0')
        ->and($omeka->snapshot())->toBe(str_replace(SPEC001_MODULE."\t1\t", SPEC001_MODULE."\t0\t", $active));

    $omeka->moduleAction('activate', SPEC001_MODULE);
    expect($omeka->moduleRow(SPEC001_MODULE)['is_active'] ?? null)->toBe('1')
        ->and($omeka->snapshot())->toBe($active);
})->group('SPEC-001');

it('AC4: uninstalling leaves the installation as it was', function (): void {
    $omeka = spec001Php83();
    spec001Uninstalled($omeka);
    $before = $omeka->snapshot();
    $omeka->moduleAction('install', SPEC001_MODULE);

    $response = $omeka->moduleAction('uninstall', SPEC001_MODULE);

    expect($response->status)->toBe(302)
        ->and(OmekaInstallation::messages($omeka->get('/admin/module')->body))->toContain('The module was successfully uninstalled')
        ->and($omeka->snapshot())->toBe($before);
})->group('SPEC-001');

it('AC6: the refusal on PHP 8.2 reaches the modules page and writes nothing', function (): void {
    $omeka = OmekaInstallation::php82();
    $omeka->logIn();
    spec001Uninstalled($omeka);
    $before = $omeka->snapshot();

    $response = $omeka->moduleAction('install', SPEC001_MODULE);
    $messages = OmekaInstallation::messages($omeka->get('/admin/module')->body);

    expect($response->status)->toBe(302)
        ->and($messages)->toHaveCount(1)
        ->and($messages[0] ?? '')->toMatch('/^Content Credentials \(C2PA\) needs PHP 8\.3 or later; this server runs PHP 8\.2\.\d+\.$/')
        ->and($omeka->moduleRow(SPEC001_MODULE))->toBeNull()
        ->and($omeka->snapshot())->toBe($before);
})->group('SPEC-001');
