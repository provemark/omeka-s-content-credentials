<?php

declare(strict_types=1);

/*
 * SPEC-006, the parts that need a stand-in: a check gives a document only
 * for Trusted, Valid and Invalid (AC3, AC4), and a store whose evidence
 * insert throws leaves the placeholder verdict, reported, never thrown
 * (AC9).
 */

use ContentCredentials\Check\Evidence;
use ContentCredentials\Check\MediaCheck;
use ContentCredentials\Check\Recorder;
use ContentCredentials\Check\Verdict;
use ContentCredentials\Check\VerdictStore;
use ContentCredentials\Tests\Support\VerifierCli;
use Provemark\C2paVerifier\Trust\TrustSettings;
use Provemark\C2paVerifier\Verifier\Verifier;

/** A store that remembers its writes, and throws when given evidence. */
final class Spec006Store implements VerdictStore
{
    /** @var list<array{int, Verdict, ?Evidence}> */
    public array $writes = [];

    public function __construct(private readonly bool $evidenceFails = false) {}

    public function write(int $mediaId, Verdict $verdict, ?Evidence $evidence = null): void
    {
        if ($evidence !== null && $this->evidenceFails) {
            throw new RuntimeException('SQLSTATE[42S02]: content_credentials_evidence does not exist');
        }
        $this->writes[] = [$mediaId, $verdict, $evidence];
    }
}

/**
 * @param  (Closure(resource, ?TrustSettings): mixed)|null  $verify
 */
function spec006Check(?Closure $verify = null): MediaCheck
{
    if ($verify !== null) {
        return new MediaCheck(static fn (): string => Verifier::VERSION, $verify);
    }

    return new MediaCheck(static fn (): string => Verifier::VERSION, static fn ($stream) => (new Verifier)->verify($stream, null));
}

it('AC1: a signed file gives the verifier\'s document', function (): void {
    $checked = spec006Check()->checked(VerifierCli::fixture('fixture-signed.jpg'), '8.3.0');
    $document = json_decode($checked->evidence->document ?? '{}', true);
    $state = is_array($document) ? ($document['validation_state'] ?? null) : null;

    expect($checked->verdict->state)->toBe('Valid')
        ->and($checked->evidence?->document)->toStartWith("{\n    \"format\": \"c2pa-verifier/evidence\",\n    \"version\": 1,")
        ->and($state)->toBe('Valid')
        ->and($checked->evidence?->trustSha256)->toBeNull();
})->group('SPEC-006');

it('AC3: a file without Content Credentials gives no document', function (string $name): void {
    $checked = spec006Check()->checked(VerifierCli::fixture($name), '8.3.0');

    expect($checked->verdict->state)->toBe('none')
        ->and($checked->evidence)->toBeNull();
})->with(['fixture-unsigned.jpg', 'fixture-unsigned.png', 'fixture-unsigned.webp'])->group('SPEC-006');

it('AC4: a check that ends in error gives no document', function (?string $path, ?Closure $verify, string $phpVersion, string $reason): void {
    $checked = spec006Check($verify)->checked($path, $phpVersion);

    expect($checked->verdict->state)->toBe('error')
        ->and($checked->verdict->reason)->toBe($reason)
        ->and($checked->evidence)->toBeNull();
})->with([
    'unreadable' => [__DIR__.'/no-such-file.jpg', null, '8.3.0', 'unreadable'],
    'exception' => [VerifierCli::fixture('fixture-signed.jpg'), static fn ($stream) => throw new RuntimeException('secret /var/www'), '8.3.0', 'exception'],
    'requirements' => [VerifierCli::fixture('fixture-signed.jpg'), null, '8.2.35', 'requirements'],
])->group('SPEC-006');

it('AC9: an evidence insert that throws leaves the placeholder, reported, never thrown', function (): void {
    $store = new Spec006Store(evidenceFails: true);
    $failures = [];

    (new Recorder(spec006Check(), $store, static function (Throwable $e, int $mediaId) use (&$failures): void {
        $failures[] = $mediaId;
    }))->record(11, VerifierCli::fixture('fixture-signed.jpg'), '8.3.0');

    expect($store->writes)->toHaveCount(1)
        ->and($store->writes[0][1]->state)->toBe('error')
        ->and($store->writes[0][1]->reason)->toBe('interrupted')
        ->and($store->writes[0][2])->toBeNull()
        ->and($failures)->toBe([11]);
})->group('SPEC-006');

it('AC9: with a store that works, the verdict and its document arrive together', function (): void {
    $store = new Spec006Store;

    (new Recorder(spec006Check(), $store, static fn (Throwable $e, int $id) => null))->record(12, VerifierCli::fixture('fixture-signed.jpg'), '8.3.0');

    expect($store->writes)->toHaveCount(2)
        ->and($store->writes[1][1]->state)->toBe('Valid')
        ->and($store->writes[1][2]?->document)->toContain('"validation_state": "Valid"');
})->group('SPEC-006');
