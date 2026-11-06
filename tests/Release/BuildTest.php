<?php

declare(strict_types=1);

/*
 * SPEC-008 AC1 to AC4 and AC8: what tools/build.sh makes. AC1 and AC2 build in a
 * repository of their own, so the working tree is never touched; it holds what
 * the working tree holds now, so an uncommitted build.sh is tested too.
 */

require_once __DIR__.'/Support.php';

const RELEASE_STRAUSS = 'strauss-0.31.1.phar';

/**
 * A repository of its own holding the working tree as it is, committed, with the
 * pinned phar's folder in place. Not a clone: CI's checkout is shallow, and git
 * then ignores --shared, which leaves out a commit made only for this test.
 */
function releaseClone(): string
{
    $root = releaseRoot();
    $index = releaseWork('index').'/index';
    copy((string) realpath($root.'/'.trim(releaseRun(['git', 'rev-parse', '--git-path', 'index'], $root))), $index);
    $tree = trim((string) shell_exec('cd '.escapeshellarg($root).' && GIT_INDEX_FILE='.escapeshellarg($index).' git add -A && GIT_INDEX_FILE='.escapeshellarg($index).' git write-tree'));
    $clone = releaseWork('clone');
    $archive = $clone.'.tar';
    releaseRun(['git', 'archive', '-o', $archive, $tree], $root);
    releaseRun(['tar', '-xf', $archive, '-C', $clone]);
    unlink($archive);
    releaseRun(['git', 'init', '-q'], $clone);
    releaseRun(['git', 'add', '-A'], $clone);
    releaseRun(['git', '-c', 'user.name=spec008', '-c', 'user.email=spec008@example.com', 'commit', '-q', '-m', 'SPEC-008 build test'], $clone);
    mkdir($clone.'/build/.tools', 0777, true);

    return $clone;
}

it('AC1: the zip comes from the committed HEAD', function (): void {
    $clone = releaseClone();
    copy(releaseRoot().'/build/.tools/'.RELEASE_STRAUSS, $clone.'/build/.tools/'.RELEASE_STRAUSS);
    $committed = (string) file_get_contents($clone.'/src/Requirements.php');
    file_put_contents($clone.'/src/Requirements.php', $committed."\n// not committed\n");
    file_put_contents($clone.'/untracked.txt', 'not tracked');
    mkdir($clone.'/vendor');
    file_put_contents($clone.'/vendor/marker', 'stays');
    $status = releaseRun(['git', '-C', $clone, 'status', '--porcelain']);

    [$exit, , $err] = releaseExec(['sh', 'tools/build.sh'], $clone);

    $zip = $clone.'/build/ContentCredentials-'.releaseVersion().'.zip';
    expect($exit)->toBe(0, $err)->and($zip)->toBeFile();
    $unpacked = releaseWork('ac1');
    releaseRun(['unzip', '-q', $zip, '-d', $unpacked]);
    expect(file_get_contents($unpacked.'/ContentCredentials/src/Requirements.php'))->toBe($committed)
        ->and($unpacked.'/ContentCredentials/untracked.txt')->not->toBeFile()
        ->and(releaseRun(['git', '-C', $clone, 'status', '--porcelain']))->toBe($status)
        ->and(file_get_contents($clone.'/vendor/marker'))->toBe('stays');
})->group('SPEC-008');

it('AC2: a phar that is not the pinned one stops the build', function (): void {
    $clone = releaseClone();
    $zip = $clone.'/build/ContentCredentials-'.releaseVersion().'.zip';
    file_put_contents($zip, 'an earlier zip');
    $phar = $clone.'/build/.tools/'.RELEASE_STRAUSS;
    $bytes = '<?php touch('.var_export($clone.'/ran', true).');';
    file_put_contents($phar, $bytes);

    [$exit, , $err] = releaseExec(['sh', 'tools/build.sh'], $clone);

    expect($exit)->not->toBe(0)
        ->and($err)->toContain($phar)
        ->and($err)->toContain(hash('sha256', $bytes))
        ->and($err)->toContain('17dc2baf27b9a3f52834e3db729c8365d8915a80e37a087d253cf705387aa887')
        ->and($zip)->not->toBeFile()
        ->and($clone.'/ran')->not->toBeFile();
})->group('SPEC-008');

it('AC3: the zip holds what ADR-0006 lists, and nothing else', function (): void {
    $entries = array_values(array_filter(explode("\n", releaseRun(['zipinfo', '-1', releaseZip()]))));
    $top = [];
    $prefixed = [];
    foreach ($entries as $entry) {
        expect(str_starts_with($entry, 'ContentCredentials/'))->toBeTrue($entry)
            ->and(preg_match('#Cli/|\.key|tests/|notes/|specs/|tools/|\.git|CLAUDE|\.DS_Store|composer\.lock|autoload#', $entry))->toBe(0, $entry);
        $relative = substr($entry, strlen('ContentCredentials/'));
        $name = explode('/', $relative)[0];
        if ($name !== '') {
            $top[$name] = $name;
        }
        if (str_starts_with($relative, 'vendor-prefixed/') && $relative !== 'vendor-prefixed/') {
            $inside = implode('/', array_slice(explode('/', substr($relative, strlen('vendor-prefixed/'))), 0, 3));
            $prefixed[$inside] = $inside;
        }
    }
    $top = array_values($top);
    sort($top);
    $prefixed = array_values($prefixed);
    sort($prefixed);

    expect($top)->toBe(['CHANGELOG.md', 'LICENSE', 'Module.php', 'README.md', 'config', 'src', 'trust', 'vendor-prefixed', 'view'])
        ->and($prefixed)->toBe([
            'provemark/',
            'provemark/c2pa-verifier/',
            'provemark/c2pa-verifier/LICENSE',
            'provemark/c2pa-verifier/composer.json',
            'provemark/c2pa-verifier/src',
        ]);
})->group('SPEC-008');

it('AC4: nothing but the prefix changed', function (): void {
    $root = releaseRoot();
    $module = releaseUnpacked();
    $verifier = $module.'/vendor-prefixed/provemark/c2pa-verifier';
    $original = $root.'/vendor/provemark/c2pa-verifier';

    // The verifier, with the prefix taken out, against the original without Cli/.
    $expected = array_filter(releaseHashes($original.'/src'), static fn (string $path): bool => ! str_starts_with($path, 'Cli/'), ARRAY_FILTER_USE_KEY);
    $actual = [];
    foreach (array_keys(releaseHashes($verifier.'/src')) as $path) {
        $text = (string) file_get_contents($verifier.'/src/'.$path);
        $actual[$path] = hash('sha256', str_replace(['ContentCredentials\\\\Vendor\\\\', 'ContentCredentials\\Vendor\\'], '', $text));
    }
    expect($actual)->toBe($expected)
        ->and(hash_file('sha256', $verifier.'/LICENSE'))->toBe(hash_file('sha256', $original.'/LICENSE'));

    foreach (['trust', 'view', 'config'] as $directory) {
        expect(releaseHashes($module.'/'.$directory))->toBe(releaseHashes($root.'/'.$directory));
    }
    expect(hash_file('sha256', $module.'/Module.php'))->toBe(hash_file('sha256', $root.'/Module.php'));

    // Nothing in the module's source or the verifier names the unprefixed namespace.
    foreach ([$module.'/src', $verifier.'/src'] as $directory) {
        foreach (array_keys(releaseHashes($directory)) as $path) {
            expect((string) file_get_contents($directory.'/'.$path))->not->toMatch('/(?<!Vendor\\\\)(?<!Vendor\\\\\\\\)Provemark\\\\+C2paVerifier/');
        }
    }
})->group('SPEC-008');

it('AC16: CI runs the zip on every PHP the README promises', function (): void {
    $workflow = (string) file_get_contents(releaseRoot().'/.github/workflows/ci.yml');
    $release = substr($workflow, (int) strpos($workflow, "\n  release:"));
    $release = substr($release, 0, (int) strpos($release, "\n  parse-on-older-php:"));

    expect($release)->toContain("php: ['8.3', '8.4', '8.5']")
        ->and($release)->toContain('tools/env/release.sh --php ${{ matrix.php }}')
        ->and($release)->toContain('tools/env/release.sh --integration --php ${{ matrix.php }}');
})->group('SPEC-008');

it('AC8: the integration tests hold on the zip', function (): void {
    // The run itself is CI's: tools/env/release.sh --integration. This keeps it there.
    $workflow = (string) file_get_contents(releaseRoot().'/.github/workflows/ci.yml');
    $script = (string) file_get_contents(releaseRoot().'/tools/env/release.sh');

    expect($workflow)->toContain('tools/env/release.sh --integration')
        ->and($script)->toContain('pest --testsuite=Integration');
})->group('SPEC-008');
