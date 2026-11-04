<?php

declare(strict_types=1);

/*
 * SPEC-000: the traceability checker, tested on fixture trees under
 * tests/Fixtures/spec-check/. Each tree is the smallest specs/ + tests/ pair
 * that triggers one criterion. The fixture "test" files are never collected
 * by Pest (phpunit.xml lists tests/Unit only) and are skipped by the checker
 * itself when it runs on the repository root.
 *
 * Ported from provemark/c2pa-verifier v0.10.0 (tests/Unit/SpecCheckTest.php);
 * AC10 and AC13 are this project's.
 */

require_once dirname(__DIR__, 2).'/bin/spec-check.php';

function specCheckFixture(string $case): string
{
    return dirname(__DIR__).'/Fixtures/spec-check/'.$case;
}

it('AC1: reports every spec with its status and no findings on a clean tree', function (): void {
    $result = specCheck(specCheckFixture('clean'));

    expect($result->findings)->toBe([])
        ->and($result->specs)->toBe(['SPEC-001' => 'approved'])
        ->and($result->render())->toContain('spec SPEC-001 approved');
})->group('SPEC-000');

it('AC2: a group that names no existing spec is a finding', function (): void {
    $result = specCheck(specCheckFixture('unknown-group'));

    expect($result->findings)->toBe([
        'SPEC-042: group carried by tests/Unit/FixtureTest.php but no specs/SPEC-042-*.md exists',
    ])->and($result->exitCode())->toBe(1);
})->group('SPEC-000');

it('AC3: a test file without any spec group is a finding', function (): void {
    $result = specCheck(specCheckFixture('no-group'));

    expect($result->findings)->toBe([
        "tests/Unit/BareTest.php: no ->group('SPEC-###') call",
    ])->and($result->exitCode())->toBe(1);
})->group('SPEC-000');

it('AC4: a draft spec whose group is already carried by a test is a finding', function (): void {
    $result = specCheck(specCheckFixture('draft-with-tests'));

    expect($result->findings)->toBe([
        'SPEC-001: status draft but tests/Unit/FixtureTest.php carries its group — tests precede approval',
    ])->and($result->exitCode())->toBe(1);
})->group('SPEC-000');

it('AC5: an implemented spec without a test carrying its group is a finding', function (): void {
    $result = specCheck(specCheckFixture('implemented-no-test'));

    expect($result->findings)->toBe([
        'SPEC-001: status implemented but no test carries its group',
    ])->and($result->exitCode())->toBe(1);
})->group('SPEC-000');

it('AC6: an implemented spec with an unfilled Traceability row is a finding naming the criterion', function (): void {
    $result = specCheck(specCheckFixture('implemented-empty-traceability'));

    expect($result->findings)->toBe([
        'SPEC-001: status implemented but Traceability row AC2 names no test',
    ])->and($result->exitCode())->toBe(1);
})->group('SPEC-000');

it('AC7: a spec without a recognisable status is a finding and is not treated as any status', function (): void {
    $result = specCheck(specCheckFixture('bad-status'));

    // Exactly these two, so neither file was carried into the AC4/AC5 checks.
    expect($result->findings)->toBe([
        'specs/SPEC-001-no-status.md: Status missing',
        'specs/SPEC-002-capital.md: Status "Draft" is not one of draft, approved, implemented, superseded',
    ])
        ->and($result->specs)->toBe([])
        ->and($result->exitCode())->toBe(1);
})->group('SPEC-000');

it('AC8: a clean tree exits 0 and says OK with the counts', function (): void {
    $result = specCheck(specCheckFixture('clean'));

    expect($result->exitCode())->toBe(0)
        ->and($result->render())->toContain('OK: 1 spec(s), 1 test file(s)');
})->group('SPEC-000');

it('AC8: a tree with several problems reports one line per finding and exits 1', function (): void {
    $result = specCheck(specCheckFixture('dirty'));

    expect($result->exitCode())->toBe(1)
        ->and(count($result->findings))->toBe(3)
        ->and($result->render())->toContain('FAIL: 3 finding(s)');

    foreach ($result->findings as $finding) {
        expect($finding)->toMatch('/^(SPEC-\d{3}|[^:\s]+\.(php|md)): \S/');
    }
})->group('SPEC-000');

it('AC8: this repository itself is clean, with the fixture trees skipped', function (): void {
    $root = dirname(__DIR__, 2);
    $result = specCheck($root);

    // the *Test.php files outside tests/Fixtures, counted independently of the checker
    $outsideFixtures = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/tests', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file instanceof SplFileInfo && str_ends_with($file->getFilename(), 'Test.php') && ! str_contains($file->getPathname(), '/tests/Fixtures/')) {
            $outsideFixtures++;
        }
    }

    expect($result->findings)->toBe([])
        ->and($result->specs)->toHaveKey('SPEC-000')
        ->and($result->testFiles)->toBe($outsideFixtures)
        ->and($result->exitCode())->toBe(0);
})->group('SPEC-000');

it('AC9: a multi-argument group call counts for every spec it names', function (): void {
    $result = specCheck(specCheckFixture('multi-group'));

    expect($result->findings)->toBe([])
        ->and($result->specs)->toBe(['SPEC-001' => 'approved', 'SPEC-004' => 'approved']);
})->group('SPEC-000');

it('AC10: spec-check is the first step of composer check', function (): void {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    $scripts = is_array($composer) && is_array($composer['scripts'] ?? null) ? $composer['scripts'] : [];
    $check = is_array($scripts['check'] ?? null) ? $scripts['check'] : [];

    expect($check[0] ?? null)->toBe('@spec-check')
        ->and($scripts['spec-check'] ?? null)->toBe('@php bin/spec-check.php');
})->group('SPEC-000');

it('AC11: a test naming a criterion its spec does not trace is a finding naming file, line and criterion', function (): void {
    $result = specCheck(specCheckFixture('orphan-criterion'));

    expect($result->findings)->toBe([
        'SPEC-001: tests/Unit/FixtureTest.php:22 names AC3, which has no row in the spec\'s Traceability table',
        'SPEC-001: tests/Unit/FixtureTest.php:26 names AC9, which has no row in the spec\'s Traceability table',
    ])->and($result->exitCode())->toBe(1);
})->group('SPEC-000');

it('AC12: an open question without a status line is a finding in an approved spec, not in a draft', function (): void {
    $result = specCheck(specCheckFixture('open-question-without-status'));

    // line 18: a numbered question; line 25: a bullet; a status after a blank line counts, a struck one needs none
    expect($result->findings)->toBe([
        'SPEC-001: open question at line 18 has no *Status line',
        'SPEC-001: open question at line 25 has no *Status line',
    ])->and($result->exitCode())->toBe(1);
})->group('SPEC-000');

it('AC13: two files with one spec ID are one finding, and the ID takes part in nothing else', function (): void {
    $result = specCheck(specCheckFixture('duplicate-id'));

    // SPEC-007-b.md is implemented without a test: had the ID been kept, AC5 would add a finding.
    expect($result->findings)->toBe([
        'SPEC-007: specs/SPEC-007-a.md and specs/SPEC-007-b.md share one ID',
    ])
        ->and($result->specs)->toBe([])
        ->and($result->exitCode())->toBe(1);
})->group('SPEC-000');

it('AC13: a filename without SPEC-, three digits and a hyphen is a finding', function (): void {
    $result = specCheck(specCheckFixture('malformed-name'));

    expect($result->findings)->toBe([
        'specs/SPEC-0071-x.md: filename does not start with SPEC-###-',
        'specs/SPEC-7-x.md: filename does not start with SPEC-###-',
    ])
        ->and($result->specs)->toBe([])
        ->and($result->exitCode())->toBe(1);
})->group('SPEC-000');
