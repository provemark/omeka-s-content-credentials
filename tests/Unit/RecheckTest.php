<?php

declare(strict_types=1);

/*
 * SPEC-005, the parts that need a stand-in, which cannot reach a job that
 * Omeka runs as its own PHP process: the run's step over media (Recheck)
 * with a store that throws (AC7) and a PHP version below 8.3 (AC13).
 */

use ContentCredentials\Check\MediaCheck;
use ContentCredentials\Check\Recheck;
use ContentCredentials\Check\Verdict;
use ContentCredentials\Check\VerdictStore;
use ContentCredentials\Tests\Support\VerifierCli;
use Provemark\C2paVerifier\Verifier\Verifier;

/** A store that remembers its writes, and throws for one media item. */
final class Spec005Store implements VerdictStore
{
    /** @var array<int, Verdict> the last verdict written per media item */
    public array $rows = [];

    public function __construct(private readonly ?int $throwFor = null) {}

    public function write(int $mediaId, Verdict $verdict): void
    {
        if ($mediaId === $this->throwFor) {
            throw new RuntimeException('SQLSTATE[HY000]: /var/www/html/secret/path.jpg could not be written');
        }
        $this->rows[$mediaId] = $verdict;
    }
}

/**
 * Batches of the given media, ascending, after an id; every path is the
 * signed JPEG fixture.
 *
 * @param  list<int>  $ids
 * @return Closure(int): list<array{int, ?string}>
 */
function spec005Batches(array $ids): Closure
{
    return static function (int $after) use ($ids): array {
        $rows = [];
        foreach ($ids as $id) {
            if ($id > $after && count($rows) < 2) {
                $rows[] = [$id, VerifierCli::fixture('fixture-signed.jpg')];
            }
        }

        return $rows;
    };
}

it('AC7: a store that throws leaves only the module\'s own words in the log', function (): void {
    $store = new Spec005Store(throwFor: 2);
    $log = [];
    $check = new MediaCheck(
        static fn (): string => Verifier::VERSION,
        static fn ($stream) => (new Verifier)->verify($stream, null),
    );

    $completed = (new Recheck($check, $store, spec005Batches([1, 2, 3]), static function (string $line) use (&$log): void {
        $log[] = $line;
    }, static fn (): bool => false))->run(3, '8.3.0');

    $text = implode("\n", $log);
    expect($completed)->toBeTrue()
        ->and(array_keys($store->rows))->toBe([1, 3])
        ->and($store->rows[1]->state)->toBe('Valid')
        ->and($log)->toContain('The check of media 2 was not stored.')
        ->and($log)->toContain('Checked 3 media: 0 Trusted, 2 Valid, 0 Invalid, 0 none, 0 error.')
        ->and($text)->not->toContain('/var/www')
        ->and($text)->not->toContain('SQLSTATE')
        ->and($text)->not->toContain('RuntimeException')
        ->and($text)->not->toContain('secret');
})->group('SPEC-005');

it('AC13: below PHP 8.3 in the job process no verifier is loaded: error / requirements', function (): void {
    $store = new Spec005Store;
    $loaded = false;
    $check = new MediaCheck(
        static function () use (&$loaded): string {
            $loaded = true;

            return Verifier::VERSION;
        },
        static fn ($stream) => (new Verifier)->verify($stream, null),
    );

    (new Recheck($check, $store, spec005Batches([7]), static fn (string $line): null => null, static fn (): bool => false))->run(1, '8.2.35');

    expect($loaded)->toBeFalse()
        ->and($store->rows[7]->state)->toBe('error')
        ->and($store->rows[7]->reason)->toBe('requirements');
})->group('SPEC-005');
