<?php

declare(strict_types=1);

/*
 * SPEC-001 AC5: the module's PHP requirement, read from a version string.
 */

use ContentCredentials\Requirements;

it('AC5: PHP 8.3.0 and later pass', function (string $version): void {
    expect(Requirements::phpProblem($version))->toBeNull();
})->with(['8.3.0', '8.3.36', '8.4.0RC1', '8.5.12', '9.0.0'])->group('SPEC-001');

it('AC5: PHP below 8.3 is refused with a message', function (string $version): void {
    expect(Requirements::phpProblem($version))
        ->toBe("Content Credentials (C2PA) needs PHP 8.3 or later; this server runs PHP {$version}.");
})->with(['8.2.99', '8.2.35', '8.1.0', '7.4.33'])->group('SPEC-001');

it('AC5: a string that is not a version fails closed', function (string $version): void {
    expect(Requirements::phpProblem($version))
        ->toBe("Content Credentials (C2PA) needs PHP 8.3 or later; this server runs PHP {$version}.");
})->with(['', 'abc', 'eight.three', '8.3'])->group('SPEC-001');
