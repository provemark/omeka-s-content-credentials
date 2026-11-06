<?php

declare(strict_types=1);

/*
 * SPEC-005, the parts that need a stand-in, which cannot reach a job that
 * Omeka runs as its own PHP process: the run's step over media (Recheck)
 * with a store that throws (AC7) and a PHP version below 8.3 (AC13).
 */

use ContentCredentials\Check\Evidence;
use ContentCredentials\Check\MediaCheck;
use ContentCredentials\Check\Recheck;
use ContentCredentials\Check\RunFailed;
use ContentCredentials\Check\Verdict;
use ContentCredentials\Check\VerdictStore;
use ContentCredentials\Display\RecheckSection;
use ContentCredentials\Tests\Support\VerifierCli;
use Provemark\C2paVerifier\Verifier\Verifier;

/** A store that remembers its writes, and throws for one media item. */
final class Spec005Store implements VerdictStore
{
    /** @var array<int, Verdict> the last verdict written per media item */
    public array $rows = [];

    public function __construct(private readonly ?int $throwFor = null) {}

    public function write(int $mediaId, Verdict $verdict, ?Evidence $evidence = null): void
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

/** The section with marked text, so that what went through $escape shows. */
function spec005SectionFor(string $status): string
{
    return RecheckSection::html(
        static fn (string $source): string => $source,
        static fn (string $value): string => '['.$value.']',
        ['id' => 7, 'status' => $status, 'started' => null, 'ended' => null],
        null,
    );
}

it('AC14: the button belongs to a run that is starting or stopping', function (string $status, bool $button): void {
    $html = spec005SectionFor($status);

    expect(str_contains($html, 'name="[content_credentials_end_run]"') || str_contains($html, 'name="content_credentials_end_run"'))->toBe($button)
        ->and(str_contains($html, '[Mark this run as ended]'))->toBe($button)
        ->and(str_contains($html, 'no longer has a process'))->toBe($button);
    if ($button) {
        expect($html)->toMatch('#name="\\[?content_credentials_end_run\\]?" value="\\[7\\]"#')
            ->and($html)->not->toContain('name="content_credentials_recheck"');
    }
})->with([
    'starting' => ['starting', true],
    'stopping' => ['stopping', true],
    'in_progress' => ['in_progress', false],
    'completed' => ['completed', false],
    'stopped' => ['stopped', false],
    'error' => ['error', false],
])->group('SPEC-005');

it('AC14: in progress, the way to Stop stays', function (): void {
    expect(spec005SectionFor('in_progress'))->toContain('use Stop on its job page')
        ->and(spec005SectionFor('stopping'))->not->toContain('use Stop on its job page');
})->group('SPEC-005');

/** A run over three media with a store that fails for media 2, if $fails. */
function spec005Counted(bool $fails, bool $stopAtOnce = false): Recheck
{
    $check = new MediaCheck(
        static fn (): string => Verifier::VERSION,
        static fn ($stream) => (new Verifier)->verify($stream, null),
    );

    return new Recheck($check, new Spec005Store(throwFor: $fails ? 2 : null), spec005Batches([1, 2, 3]), static fn (string $line): null => null, static fn (): bool => $stopAtOnce);
}

it('AC15: a run counts the checks it could not store', function (bool $fails, int $count): void {
    $recheck = spec005Counted($fails);
    $recheck->run(3, '8.3.0');

    expect($recheck->notStored())->toBe($count);
})->with(['one fails' => [true, 1], 'none fails' => [false, 0]])->group('SPEC-005');

it('AC15: the count line reads well for one and for more', function (int $count, string $line): void {
    expect(Recheck::notStoredLine($count))->toBe($line);
})->with([[1, '1 check was not stored.'], [2, '2 checks were not stored.'], [4048, '4048 checks were not stored.']])->group('SPEC-005');

it('AC15: what Omeka logs of a failed run is the module\'s own line, whatever caused it', function (): void {
    $failed = new RunFailed;

    expect((string) $failed)->toBe('The run failed.')
        ->and((string) $failed)->not->toContain('/')
        ->and((string) $failed)->not->toContain('#0')
        ->and((string) $failed)->not->toContain('RunFailed')
        ->and($failed)->toBeInstanceOf(RuntimeException::class);
})->group('SPEC-005');
