<?php

declare(strict_types=1);

/*
 * SPEC-008 AC9: the module finds the verifier in both layouts, and AC13: the
 * job class written out for an uninstall of an inactive module is the job's.
 */

use ContentCredentials\Omeka\RecheckJob;
use ContentCredentials\Omeka\Schema;
use ContentCredentials\Omeka\VerifierLoader;

/** @param list<string> $folders */
function spec008ModuleDirectory(array $folders): string
{
    $directory = dirname(__DIR__, 2).'/build/tests/layout-'.bin2hex(random_bytes(4));
    foreach ($folders as $folder) {
        mkdir($directory.'/'.$folder.'/provemark/c2pa-verifier/src', 0777, true);
    }
    if ($folders === []) {
        mkdir($directory, 0777, true);
    }

    return $directory;
}

it('AC9: both layouts', function (string $folders, string $expected): void {
    $directory = spec008ModuleDirectory($folders === '' ? [] : explode(',', $folders));

    expect(VerifierLoader::sourceDirectory($directory))->toBe($directory.'/'.$expected.'/provemark/c2pa-verifier/src');
})->with([
    'the zip' => ['vendor-prefixed', 'vendor-prefixed'],
    'the repository' => ['vendor', 'vendor'],
    'both' => ['vendor,vendor-prefixed', 'vendor-prefixed'],
    'neither: the path that then fails closed' => ['', 'vendor'],
])->group('SPEC-008');

it('AC13: from a deactivated module too: the class written out is the job', function (): void {
    expect(Schema::JOB_CLASS)->toBe(RecheckJob::class);
})->group('SPEC-008');
