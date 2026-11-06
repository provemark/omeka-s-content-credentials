<?php

declare(strict_types=1);

/*
 * SPEC-008: uninstall removes the module's job rows and only those, also from a
 * deactivated module, and the warning shows in every state. AC11 and AC14, which
 * need a clean installation, are in tests/Release.
 */

use ContentCredentials\Tests\Integration\Support\OmekaInstallation;

const SPEC008_MODULE = 'ContentCredentials';
const SPEC008_JOB = 'ContentCredentials\\\\Omeka\\\\RecheckJob'; // as an SQL literal
const SPEC008_OTHER_JOB = 'Omeka\\\\Job\\\\BatchUpdate'; // as an SQL literal
const SPEC008_WARNING = 'Uninstalling deletes every verdict and evidence document it has stored.';

function spec008Omeka(): OmekaInstallation
{
    $omeka = OmekaInstallation::php83();
    $omeka->logIn();
    $row = $omeka->moduleRow(SPEC008_MODULE);
    if ($row === null) {
        $omeka->moduleAction('install', SPEC008_MODULE);
    } elseif ($row['is_active'] !== '1') {
        $omeka->moduleAction('activate', SPEC008_MODULE);
    }

    return $omeka;
}

function spec008JobRows(OmekaInstallation $omeka, string $class): int
{
    return (int) $omeka->sql("SELECT COUNT(*) FROM job WHERE class = '{$class}'");
}

function spec008AddJobs(OmekaInstallation $omeka, string $class): void
{
    foreach (['completed', 'stopped', 'error', 'in_progress'] as $status) {
        $omeka->sql("INSERT INTO job (status, class, started) VALUES ('{$status}', '{$class}', NOW())");
    }
}

it('AC12: only the module\'s job rows go', function (): void {
    $omeka = spec008Omeka();
    spec008AddJobs($omeka, SPEC008_JOB);
    spec008AddJobs($omeka, SPEC008_OTHER_JOB);
    $others = spec008JobRows($omeka, SPEC008_OTHER_JOB);
    expect(spec008JobRows($omeka, SPEC008_JOB))->toBeGreaterThanOrEqual(4);

    try {
        $omeka->moduleAction('uninstall', SPEC008_MODULE);

        expect(spec008JobRows($omeka, SPEC008_JOB))->toBe(0)
            ->and(spec008JobRows($omeka, SPEC008_OTHER_JOB))->toBe($others)
            ->and($omeka->get('/admin/job')->status)->toBe(200);
    } finally {
        $omeka->sql("DELETE FROM job WHERE class = '".SPEC008_OTHER_JOB."'");
    }
})->group('SPEC-008');

it('AC13: from a deactivated module too', function (): void {
    $omeka = spec008Omeka();
    spec008AddJobs($omeka, SPEC008_JOB);
    $omeka->sql("REPLACE INTO setting (id, value) VALUES ('content_credentials_digicert', '\"1\"')");
    $omeka->moduleAction('deactivate', SPEC008_MODULE);
    expect($omeka->moduleRow(SPEC008_MODULE)['is_active'] ?? null)->toBe('0');

    $omeka->moduleAction('uninstall', SPEC008_MODULE);

    expect($omeka->moduleRow(SPEC008_MODULE))->toBeNull()
        ->and(spec008JobRows($omeka, SPEC008_JOB))->toBe(0)
        ->and($omeka->sql("SHOW TABLES LIKE 'content_credentials%'"))->toBe('')
        ->and($omeka->sql("SELECT COUNT(*) FROM setting WHERE id LIKE 'content_credentials%'"))->toBe('0');
})->group('SPEC-008');

it('AC15: the warning', function (): void {
    $omeka = spec008Omeka();
    $confirm = '/admin/module/uninstall-confirm?id='.SPEC008_MODULE;

    expect($omeka->get('/admin/module')->body)->toContain(SPEC008_WARNING)
        ->and($omeka->get($confirm)->body)->toContain(SPEC008_WARNING);

    $omeka->moduleAction('deactivate', SPEC008_MODULE);
    try {
        expect($omeka->get('/admin/module')->body)->toContain(SPEC008_WARNING)
            ->and($omeka->get($confirm)->body)->toContain(SPEC008_WARNING);
    } finally {
        $omeka->moduleAction('activate', SPEC008_MODULE);
    }
})->group('SPEC-008');
