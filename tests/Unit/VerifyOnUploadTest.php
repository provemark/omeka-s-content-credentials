<?php

declare(strict_types=1);

/*
 * SPEC-002, the parts that need no Omeka S: from a file to a verdict
 * (MediaCheck), and recording it (Recorder). The oracle is
 * vendor/bin/c2pa-verify on the same bytes.
 */

use ContentCredentials\Check\MediaCheck;
use ContentCredentials\Check\Recorder;
use ContentCredentials\Check\Verdict;
use ContentCredentials\Check\VerdictStore;
use ContentCredentials\Tests\Support\VerifierCli;
use Provemark\C2paVerifier\Verifier\Verifier;

function spec002UnitCheck(): MediaCheck
{
    return new MediaCheck(
        static fn (): string => Verifier::VERSION,
        static fn ($stream) => (new Verifier)->verify($stream, null),
    );
}

/** A store that remembers what was written, in order. */
final class Spec002MemoryStore implements VerdictStore
{
    /** @var list<array{int, Verdict}> */
    public array $writes = [];

    public function write(int $mediaId, Verdict $verdict): void
    {
        $this->writes[] = [$mediaId, $verdict];
    }
}

it('AC3: an unsigned file is none, not Invalid', function (string $name): void {
    $verdict = spec002UnitCheck()->check(VerifierCli::fixture($name), '8.3.0');
    $cli = VerifierCli::report(VerifierCli::fixture($name));

    expect($cli['validation_state'])->toBe('Invalid')
        ->and($cli['has_manifest'])->toBeFalse()
        ->and($verdict->state)->toBe('none')
        ->and($verdict->signerIssuer)->toBeNull()
        ->and($verdict->signerCommonName)->toBeNull()
        ->and($verdict->codes)->toBe([])
        ->and($verdict->reason)->toBeNull();
})->with(['fixture-unsigned.jpg', 'fixture-unsigned.png', 'fixture-unsigned.webp'])->group('SPEC-002');

it('AC4: an altered file is Invalid with the CLI\'s codes', function (): void {
    $path = VerifierCli::alteredJpeg();
    try {
        $verdict = spec002UnitCheck()->check($path, '8.3.0');
        $cli = VerifierCli::report($path);
    } finally {
        unlink($path);
    }
    $codes = VerifierCli::codes($cli);

    expect($verdict->state)->toBe('Invalid')
        ->and($verdict->codes)->toBe($codes)
        ->and($codes)->toBe(['signingCredential.untrusted', 'assertion.dataHash.mismatch']);
})->group('SPEC-002');

it('AC5: every state is the verifier\'s, or none for a file without a manifest', function (string $name): void {
    $verdict = spec002UnitCheck()->check(VerifierCli::fixture($name), '8.3.0');
    $cli = VerifierCli::report(VerifierCli::fixture($name));
    $signature = VerifierCli::signature($cli);

    expect($verdict->state)->toBe($cli['has_manifest'] === true ? $cli['validation_state'] : 'none')
        ->and($verdict->format)->toBe($cli['format'])
        ->and($verdict->signerIssuer)->toBe($signature['issuer'] ?? null)
        ->and($verdict->signerCommonName)->toBe($signature['common_name'] ?? null)
        ->and($verdict->signedAt)->toBe($signature['time'] ?? null)
        ->and($verdict->remoteManifestUrl)->toBe($cli['remote_manifest'] ?? null)
        ->and($verdict->verifier)->toBe(Verifier::VERSION)
        ->and($verdict->checkedSha256)->toBe(hash_file('sha256', VerifierCli::fixture($name)));
})->with([
    'fixture-signed.jpg', 'fixture-signed.png', 'fixture-signed.webp',
    'fixture-unsigned.jpg', 'fixture-unsigned.png', 'fixture-unsigned.webp',
])->group('SPEC-002');

it('AC8: an original that cannot be opened is error / unreadable', function (?string $path): void {
    $verdict = spec002UnitCheck()->check($path, '8.3.0');

    expect($verdict->state)->toBe('error')
        ->and($verdict->reason)->toBe('unreadable')
        ->and($verdict->checkedSha256)->toBeNull()
        ->and($verdict->format)->toBeNull();
})->with([
    'no local path (a store that is not Local)' => [null],
    'a path with no file' => [sys_get_temp_dir().'/spec002-does-not-exist.jpg'],
    'a directory' => [sys_get_temp_dir()],
])->group('SPEC-002');

it('AC9: an exception from the verifier is error / exception, its message not kept', function (): void {
    $check = new MediaCheck(
        static fn (): string => '0.10.0',
        static fn ($stream) => throw new RuntimeException('secret /var/www/html/files/original/x.jpg'),
    );

    $verdict = $check->check(VerifierCli::fixture('fixture-signed.jpg'), '8.3.0');

    expect($verdict->state)->toBe('error')
        ->and($verdict->reason)->toBe('exception')
        ->and(serialize($verdict))->not->toContain('secret');
})->group('SPEC-002');

it('AC9: an exception anywhere in recording never leaves the recorder', function (): void {
    $store = new Spec002MemoryStore;
    $failures = [];
    $check = new MediaCheck(static fn (): string => throw new Error('loading failed'), static fn ($stream) => null);
    $recorder = new Recorder($check, $store, static function (Throwable $e, int $mediaId) use (&$failures): void {
        $failures[] = $mediaId;
    });

    $recorder->record(7, VerifierCli::fixture('fixture-signed.jpg'), '8.3.0');

    $last = $store->writes[array_key_last($store->writes) ?? 0][1] ?? null;

    expect($last?->state)->toBe('error')
        ->and($last?->reason)->toBe('exception');
})->group('SPEC-002');

it('AC10: the interrupted row is written before the verifier runs', function (): void {
    $store = new Spec002MemoryStore;
    $seenWhenVerifying = null;
    $check = new MediaCheck(
        static fn (): string => Verifier::VERSION,
        static function ($stream) use ($store, &$seenWhenVerifying) {
            $seenWhenVerifying = $store->writes;

            return (new Verifier)->verify($stream, null);
        },
    );

    (new Recorder($check, $store, static fn (Throwable $e, int $id) => null))->record(5, VerifierCli::fixture('fixture-signed.jpg'), '8.3.0');

    expect($seenWhenVerifying)->toHaveCount(1)
        ->and($seenWhenVerifying[0][0] ?? null)->toBe(5)
        ->and($seenWhenVerifying[0][1]->state ?? null)->toBe('error')
        ->and($seenWhenVerifying[0][1]->reason ?? null)->toBe('interrupted')
        ->and($store->writes)->toHaveCount(2)
        ->and($store->writes[1][1]->state)->toBe('Valid');
})->group('SPEC-002');

it('AC11: a store that fails is reported, never thrown', function (): void {
    $store = new class implements VerdictStore
    {
        public function write(int $mediaId, Verdict $verdict): void
        {
            throw new RuntimeException('table is gone');
        }
    };
    $failures = [];
    $recorder = new Recorder(spec002UnitCheck(), $store, static function (Throwable $e, int $mediaId) use (&$failures): void {
        $failures[] = [$mediaId, $e->getMessage()];
    });

    $recorder->record(9, VerifierCli::fixture('fixture-signed.jpg'), '8.3.0');

    expect($failures)->toBe([[9, 'table is gone']]);
})->group('SPEC-002');

it('AC12: below PHP 8.3 the verifier is not loaded: error / requirements', function (): void {
    $loaded = false;
    $verified = false;
    $check = new MediaCheck(
        static function () use (&$loaded): string {
            $loaded = true;

            return '0.10.0';
        },
        static function ($stream) use (&$verified) {
            $verified = true;

            return null;
        },
    );

    $verdict = $check->check(VerifierCli::fixture('fixture-signed.jpg'), '8.2.35');

    expect($loaded)->toBeFalse()
        ->and($verified)->toBeFalse()
        ->and($verdict->state)->toBe('error')
        ->and($verdict->reason)->toBe('requirements')
        ->and($verdict->verifier)->toBeNull()
        ->and($verdict->checkedSha256)->toBeNull();
})->group('SPEC-002');
