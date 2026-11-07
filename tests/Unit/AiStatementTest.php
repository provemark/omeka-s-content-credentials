<?php

declare(strict_types=1);

/*
 * SPEC-007, the rule: AC2 to AC6 on arrays in the shape of toArray(), built
 * here, and AC14 on the module's source. The real files are in
 * tests/Integration/AiLabelTest.php, and what is measured about the shape in
 * notes/step-26-ai-statement.md.
 */

use ContentCredentials\Check\AiStatement;
use ContentCredentials\Display\AiFilterValue;
use ContentCredentials\Display\Presentation;

const SPEC007_PREFIX = 'http://cv.iptc.org/newscodes/digitalsourcetype/';
const SPEC007_TRAINED = SPEC007_PREFIX.'trainedAlgorithmicMedia';
const SPEC007_COMPOSITE = SPEC007_PREFIX.'compositeWithTrainedAlgorithmicMedia';
const SPEC007_UNTRUSTED = 'signingCredential.untrusted';

/**
 * An edge of a report: the manifest it leads to, its relationship, and the
 * codes recorded and in the verifier's delta (null leaves either out).
 *
 * @param  list<string>|null  $recorded
 * @param  list<string>|null  $delta
 * @return array{to: string, relationship: mixed, recorded: list<string>|null, delta: list<string>|null}
 */
function spec007Edge(string $to, mixed $relationship = 'parentOf', ?array $recorded = [], ?array $delta = []): array
{
    return ['to' => $to, 'relationship' => $relationship, 'recorded' => $recorded, 'delta' => $delta];
}

/**
 * @param  list<string>  $codes
 * @return list<array{code: string}>
 */
function spec007Codes(array $codes): array
{
    return array_map(static fn (string $code): array => ['code' => $code], $codes);
}

/**
 * A report in toArray()'s shape.
 *
 * @param  array<int|string, array{types?: list<mixed>, edges?: list<array{to: string, relationship: mixed, recorded: list<string>|null, delta: list<string>|null}>}>  $manifests  the first is the active one
 * @return array<string, mixed>
 */
function spec007Report(array $manifests): array
{
    $built = [];
    $deltas = [];
    foreach ($manifests as $label => $manifest) {
        $actions = array_map(static fn (mixed $type): array => ['action' => 'c2pa.created', 'digitalSourceType' => $type], $manifest['types'] ?? []);
        $ingredients = [];
        foreach ($manifest['edges'] ?? [] as $i => $edge) {
            $name = 'c2pa.ingredient.v3'.($i === 0 ? '' : '__'.$i);
            $ingredient = ['label' => $name, 'relationship' => $edge['relationship'], 'active_manifest' => $edge['to']];
            if ($edge['recorded'] !== null) {
                $ingredient['validation_results'] = ['activeManifest' => ['success' => [], 'failure' => spec007Codes($edge['recorded'])]];
            }
            if ($edge['delta'] !== null) {
                $deltas[] = [
                    'ingredientAssertionURI' => 'self#jumbf=/c2pa/'.$label.'/c2pa.assertions/'.$name,
                    'validationDeltas' => ['success' => [], 'failure' => spec007Codes($edge['delta'])],
                ];
            }
            $ingredients[] = $ingredient;
        }
        $built[$label] = ['assertions' => [['label' => 'c2pa.actions.v2', 'data' => ['actions' => $actions]]], 'ingredients' => $ingredients];
    }

    return [
        'active_manifest' => array_key_first($manifests),
        'manifests' => $built,
        'validation_results' => ['ingredientDeltas' => $deltas],
    ];
}

/**
 * A copy with the value at a path replaced.
 *
 * @param  array<mixed>  $values
 * @param  non-empty-list<int|string>  $path
 * @return array<mixed>
 */
function spec007At(array $values, array $path, mixed $value): array
{
    $key = array_shift($path);
    if ($path === []) {
        $values[$key] = $value;

        return $values;
    }
    $inner = $values[$key] ?? [];
    $values[$key] = spec007At(is_array($inner) ? $inner : [], $path, $value);

    return $values;
}

/**
 * A copy with the key at a path removed.
 *
 * @param  array<mixed>  $values
 * @param  non-empty-list<int|string>  $path
 * @return array<mixed>
 */
function spec007Without(array $values, array $path): array
{
    $key = array_shift($path);
    if ($path === []) {
        unset($values[$key]);

        return $values;
    }
    $inner = $values[$key] ?? [];
    $values[$key] = spec007Without(is_array($inner) ? $inner : [], $path);

    return $values;
}

it('AC2: generated, in the active manifest and down a parent chain', function (array $report): void {
    expect(AiStatement::read($report))->toBe('generated');
})->with([
    'active' => [spec007Report(['a' => ['types' => [SPEC007_TRAINED]]])],
    'another action' => [(static function (): array {
        $report = spec007Report(['a' => ['types' => [SPEC007_PREFIX.'digitalCapture', SPEC007_TRAINED]]]);

        return $report;
    })()],
    'claim v1 label' => [(static function (): array {
        $actions = [['action' => 'c2pa.edited', 'digitalSourceType' => SPEC007_TRAINED]];

        return ['active_manifest' => 'a', 'manifests' => ['a' => ['assertions' => [['label' => 'c2pa.actions', 'data' => ['actions' => $actions]]]]]];
    })()],
    'parent chain' => [spec007Report([
        'a' => ['edges' => [spec007Edge('b')]],
        'b' => ['edges' => [spec007Edge('c')]],
        'c' => ['types' => [SPEC007_TRAINED]],
    ])],
])->group('SPEC-007');

it('AC3: edited', function (array $report): void {
    expect(AiStatement::read($report))->toBe('edited');
})->with([
    'composite, active' => [spec007Report(['a' => ['types' => [SPEC007_COMPOSITE]]])],
    'composite, parent' => [spec007Report(['a' => ['edges' => [spec007Edge('b')]], 'b' => ['types' => [SPEC007_COMPOSITE]]])],
    'composite, component' => [spec007Report(['a' => ['edges' => [spec007Edge('b', 'componentOf')]], 'b' => ['types' => [SPEC007_COMPOSITE]]])],
    'trained, component' => [spec007Report(['a' => ['edges' => [spec007Edge('b', 'componentOf')]], 'b' => ['types' => [SPEC007_TRAINED]]])],
    'trained, input' => [spec007Report(['a' => ['edges' => [spec007Edge('b', 'inputTo')]], 'b' => ['types' => [SPEC007_TRAINED]]])],
])->group('SPEC-007');

it('AC4: generated wins, and needs the parent line', function (array $report, string $answer): void {
    expect(AiStatement::read($report))->toBe($answer);
})->with([
    'composite active, trained parent' => [spec007Report(['a' => ['types' => [SPEC007_COMPOSITE], 'edges' => [spec007Edge('b')]], 'b' => ['types' => [SPEC007_TRAINED]]]), 'generated'],
    'reached as a component first, and as a parent' => [spec007Report([
        'a' => ['edges' => [spec007Edge('b', 'componentOf'), spec007Edge('b')]],
        'b' => ['types' => [SPEC007_TRAINED]],
    ]), 'generated'],
    'a component reached first keeps a later parent line open' => [spec007Report([
        'a' => ['edges' => [spec007Edge('c', 'componentOf'), spec007Edge('b')]],
        'b' => ['edges' => [spec007Edge('c')]],
        'c' => ['types' => [SPEC007_TRAINED]],
    ]), 'generated'],
    'a component, then a parent' => [spec007Report([
        'a' => ['edges' => [spec007Edge('b', 'componentOf')]],
        'b' => ['edges' => [spec007Edge('c')]],
        'c' => ['types' => [SPEC007_TRAINED]],
    ]), 'edited'],
])->group('SPEC-007');

/**
 * The parent chain of AC2, with its first ingredient's codes as given.
 *
 * @param  list<string>|null  $recorded
 * @param  list<string>|null  $delta
 * @return array<string, mixed>
 */
function spec007Chain(?array $recorded, ?array $delta): array
{
    return spec007Report([
        'a' => ['edges' => [spec007Edge('b', 'parentOf', $recorded, $delta)]],
        'b' => ['edges' => [spec007Edge('c')]],
        'c' => ['types' => [SPEC007_TRAINED, SPEC007_COMPOSITE]],
    ]);
}

it('AC5: an ingredient that is not followed', function (array $report): void {
    expect(AiStatement::read($report))->toBeNull();
})->with([
    'recorded failure' => [spec007Chain(['assertion.dataHash.mismatch'], [])],
    'recorded, untrusted and another' => [spec007Chain([SPEC007_UNTRUSTED, 'claimSignature.mismatch'], [])],
    'no recorded results' => [spec007Chain(null, [SPEC007_UNTRUSTED])],
    'the delta fails' => [spec007Chain([], ['claimSignature.mismatch'])],
    'the delta, untrusted and another' => [spec007Chain([], [SPEC007_UNTRUSTED, 'ingredient.manifest.mismatch'])],
    'no delta' => [spec007Chain([], null)],
])->group('SPEC-007');

it('AC5: a branch that is not followed leaves another branch open', function (): void {
    $report = spec007Report([
        'a' => ['edges' => [spec007Edge('b', 'parentOf', ['claimSignature.mismatch'], []), spec007Edge('c')]],
        'b' => ['types' => [SPEC007_COMPOSITE]],
        'c' => ['types' => [SPEC007_TRAINED]],
    ]);

    expect(AiStatement::read($report))->toBe('generated');
})->group('SPEC-007');

it('AC5: an unknown signer on an ingredient is followed', function (): void {
    $report = spec007Report([
        'a' => ['edges' => [spec007Edge('b', 'parentOf', [SPEC007_UNTRUSTED], [SPEC007_UNTRUSTED])]],
        'b' => ['types' => [SPEC007_TRAINED]],
    ]);

    expect(AiStatement::read($report))->toBe('generated');
})->group('SPEC-007');

it('AC6: source types that do not count', function (mixed $type): void {
    expect(AiStatement::read(spec007Report(['a' => ['types' => [$type]]])))->toBeNull();
})->with([
    'algorithmicMedia' => [SPEC007_PREFIX.'algorithmicMedia'],
    'digitalCapture' => [SPEC007_PREFIX.'digitalCapture'],
    'compositeSynthetic' => [SPEC007_PREFIX.'compositeSynthetic'],
    'https' => ['https://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia'],
    'leading space' => [' '.SPEC007_TRAINED],
    'trailing space' => [SPEC007_TRAINED.' '],
    'another case' => [SPEC007_PREFIX.'TrainedAlgorithmicMedia'],
    'the short name' => ['trainedAlgorithmicMedia'],
    'an integer' => [1],
    'an array' => [[SPEC007_TRAINED]],
    'null' => [null],
])->group('SPEC-007');

it('AC6: malformed reports read as nothing, without a warning', function (array $report): void {
    $warnings = [];
    set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
        $warnings[] = $message;

        return true;
    });
    try {
        $answer = AiStatement::read($report);
    } finally {
        restore_error_handler();
    }

    expect($answer)->toBeNull()->and($warnings)->toBe([]);
})->with(function (): array {
    $good = spec007Report(['a' => ['edges' => [spec007Edge('b')]], 'b' => ['types' => [SPEC007_TRAINED]]]);
    $ingredient = ['manifests', 'a', 'ingredients', 0];
    $recorded = [...$ingredient, 'validation_results', 'activeManifest', 'failure'];
    $delta = ['validation_results', 'ingredientDeltas', 0, 'validationDeltas', 'failure'];
    $firstDelta = $good['validation_results'];
    $twoDeltas = is_array($firstDelta) && is_array($firstDelta['ingredientDeltas'] ?? null) ? $firstDelta['ingredientDeltas'][0] : [];

    return [
        'empty' => [[]],
        'no active manifest' => [spec007Without($good, ['active_manifest'])],
        'active manifest is an array' => [spec007At($good, ['active_manifest'], ['a'])],
        'active manifest is null' => [spec007At($good, ['active_manifest'], null)],
        'manifests is a string' => [spec007At($good, ['manifests'], 'a')],
        'a manifest is a number' => [['active_manifest' => 'a', 'manifests' => ['a' => 1]]],
        'assertions is a string' => [['active_manifest' => 'a', 'manifests' => ['a' => ['assertions' => 'x']]]],
        'an assertion is a number' => [['active_manifest' => 'a', 'manifests' => ['a' => ['assertions' => [1]]]]],
        'data is a string' => [['active_manifest' => 'a', 'manifests' => ['a' => ['assertions' => [['label' => 'c2pa.actions', 'data' => 'x']]]]]],
        'actions is a string' => [['active_manifest' => 'a', 'manifests' => ['a' => ['assertions' => [['label' => 'c2pa.actions', 'data' => ['actions' => 'x']]]]]]],
        'an action is a number' => [['active_manifest' => 'a', 'manifests' => ['a' => ['assertions' => [['label' => 'c2pa.actions', 'data' => ['actions' => [1]]]]]]]],
        'another assertion says it' => [['active_manifest' => 'a', 'manifests' => ['a' => ['assertions' => [['label' => 'c2pa.other', 'data' => ['actions' => [['digitalSourceType' => SPEC007_TRAINED]]]]]]]]],
        'ingredients is a string' => [spec007At($good, ['manifests', 'a', 'ingredients'], 'x')],
        'an ingredient is a number' => [spec007At($good, $ingredient, 1)],
        'an ingredient names no manifest' => [spec007Without($good, [...$ingredient, 'active_manifest'])],
        'an ingredient names a label not in manifests' => [spec007At($good, [...$ingredient, 'active_manifest'], 'z')],
        'an ingredient names an array' => [spec007At($good, [...$ingredient, 'active_manifest'], ['b'])],
        'an ingredient has no label' => [spec007Without($good, [...$ingredient, 'label'])],
        'validation_results is a string' => [spec007At($good, [...$ingredient, 'validation_results'], 'x')],
        'a recorded failure is a string' => [spec007At($good, $recorded, ['x'])],
        'recorded failure without a code' => [spec007At($good, $recorded, [[]])],
        'recorded failure is not a list' => [spec007At($good, $recorded, 'none')],
        'validation_results of the report is a string' => [spec007At($good, ['validation_results'], 'x')],
        'ingredientDeltas is a string' => [spec007At($good, ['validation_results', 'ingredientDeltas'], 'x')],
        'a delta is a number' => [spec007At($good, ['validation_results', 'ingredientDeltas'], [1])],
        'a delta without failures' => [spec007Without($good, $delta)],
        'two deltas, the second fails' => [spec007At(
            spec007At($good, ['validation_results', 'ingredientDeltas', 1], $twoDeltas),
            ['validation_results', 'ingredientDeltas', 1, 'validationDeltas', 'failure'],
            [['code' => 'claimSignature.mismatch']],
        )],
    ];
})->group('SPEC-007');

it('AC6: a relationship that is missing or not a string is not followed', function (mixed $relationship): void {
    $report = spec007Report(['a' => ['edges' => [spec007Edge('b', $relationship)]], 'b' => ['types' => [SPEC007_TRAINED]]]);

    expect(AiStatement::read($report))->toBeNull();
})->with(['an integer' => [1], 'null, as when missing' => [null], 'an array' => [['parentOf']]])->group('SPEC-007');

it('AC3: a relationship the rule does not name counts as any other than a parent', function (): void {
    $report = spec007Report(['a' => ['edges' => [spec007Edge('b', 'childOf')]], 'b' => ['types' => [SPEC007_TRAINED]]]);

    expect(AiStatement::read($report))->toBe('edited');
})->group('SPEC-007');

it('AC6: a cycle ends', function (array $report): void {
    expect(AiStatement::read($report))->toBeNull();
})->with([
    'two manifests' => [spec007Report(['a' => ['edges' => [spec007Edge('b')]], 'b' => ['edges' => [spec007Edge('a')]]])],
    'itself' => [spec007Report(['a' => ['edges' => [spec007Edge('a')]]])],
])->group('SPEC-007');

it('AC6: labels of digits only read as any other label', function (): void {
    // PHP makes these array keys integers; the active manifest may be either.
    $report = spec007Report(['0' => ['edges' => [spec007Edge('5')]], '5' => ['types' => [SPEC007_TRAINED]]]);

    expect(array_keys(is_array($report['manifests']) ? $report['manifests'] : []))->toBe([0, 5])
        ->and(AiStatement::read($report))->toBe('generated');

    $report['active_manifest'] = '0';
    expect(AiStatement::read($report))->toBe('generated');
})->group('SPEC-007');

it('AC9: the label follows the state', function (string $state, ?string $statement, ?string $label): void {
    $row = ['state' => $state, 'codes' => '[]', 'ai_statement' => $statement];
    if ($state === 'error') {
        $row['reason'] = 'exception';
    }

    expect(Presentation::of($row)->aiLabel())->toBe($label);
})->with([
    ['Trusted', 'generated', 'AI-generated (signed)'],
    ['Valid', 'generated', 'AI-generated (signed)'],
    ['Trusted', 'edited', 'AI-edited (signed)'],
    ['Valid', 'edited', 'AI-edited (signed)'],
    ['Invalid', 'generated', null],
    ['Invalid', 'edited', null],
    ['none', 'generated', null],
    ['none', 'edited', null],
    ['error', 'generated', null],
    ['error', 'edited', null],
    ['Valid', null, null],
])->group('SPEC-007');

it('AC9: a value the module never writes makes the row unreadable, whatever its state', function (string $state, mixed $value): void {
    $row = ['state' => $state, 'codes' => '[]', 'ai_statement' => $value];
    if ($state === 'error') {
        $row['reason'] = 'exception';
    }
    $presentation = Presentation::of($row);

    expect($presentation->headline())->toBe('Result unreadable')
        ->and($presentation->aiLabel())->toBeNull()
        ->and($presentation->lines())->toBe([]);
})->with(['Trusted', 'Valid', 'Invalid', 'none', 'error'])
    ->with(['true' => ['true'], 'boolean' => [true], 'empty' => [''], 'hostile' => ['<script>alert(1)</script>'], 'another case' => ['Generated']])
    ->group('SPEC-007');

it('AC9: a row without the key has no label', function (): void {
    expect(Presentation::of(['state' => 'Valid', 'codes' => '[]'])->aiLabel())->toBeNull()
        ->and(Presentation::of(null)->aiLabel())->toBeNull();
})->group('SPEC-007');

it('AC12: what a filter value means', function (mixed $value, string|false|null $parsed): void {
    expect(AiFilterValue::parse($value))->toBe($parsed);
})->with([
    'generated' => ['generated', 'generated'],
    'edited' => ['edited', 'edited'],
    'absent' => [null, null],
    'empty' => ['', null],
    'not a label' => ['Maybe', false],
    'another case' => ['Generated', false],
    'an array' => [['generated'], false],
    'a number' => [1, false],
])->group('SPEC-007');

it('AC14: the module reads only the public report', function (): void {
    $root = dirname(__DIR__, 2);
    $files = [$root.'/Module.php'];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    expect(count($files))->toBeGreaterThan(20);
    foreach ($files as $path) {
        expect((string) file_get_contents($path))->not->toMatch('/(?<!\$this)->\s*store\b/', $path);
    }
})->group('SPEC-007');

/**
 * A copy of a report with every assertion's label replaced.
 *
 * @param  array<mixed>  $report
 * @return array<mixed>
 */
function spec007Labelled(array $report, mixed $label): array
{
    $manifests = $report['manifests'];
    assert(is_array($manifests));
    foreach ($manifests as $name => $manifest) {
        assert(is_array($manifest) && is_array($manifest['assertions']));
        foreach (array_keys($manifest['assertions']) as $index) {
            $assertion = $manifest['assertions'][$index];
            assert(is_array($assertion));
            $assertion['label'] = $label;
            $manifest['assertions'][$index] = $assertion;
        }
        $manifests[$name] = $manifest;
    }
    $report['manifests'] = $manifests;

    return $report;
}

it('AC15: an instance label reads as its base label', function (array $report, string $answer, string $label): void {
    expect(AiStatement::read($report))->toBe($answer)
        ->and(AiStatement::read(spec007Labelled($report, $label)))->toBe($answer);
})->with([
    'trained, active' => [spec007Report(['a' => ['types' => [SPEC007_TRAINED]]]), 'generated'],
    'composite, active' => [spec007Report(['a' => ['types' => [SPEC007_COMPOSITE]]]), 'edited'],
    'trained, down a parent chain' => [spec007Report([
        'a' => ['edges' => [spec007Edge('b')]],
        'b' => ['types' => [SPEC007_TRAINED]],
    ]), 'generated'],
    'trained, behind a component' => [spec007Report([
        'a' => ['edges' => [spec007Edge('b', 'componentOf')]],
        'b' => ['types' => [SPEC007_TRAINED]],
    ]), 'edited'],
])->with(['c2pa.actions__1', 'c2pa.actions.v2__1', 'c2pa.actions.v2__12'])->group('SPEC-007');

it('AC15: an instance next to the base label is read too', function (): void {
    $actions = [['action' => 'c2pa.edited', 'digitalSourceType' => SPEC007_TRAINED]];
    $report = ['active_manifest' => 'a', 'manifests' => ['a' => ['assertions' => [
        ['label' => 'c2pa.actions.v2', 'data' => ['actions' => [['action' => 'c2pa.created']]]],
        ['label' => 'c2pa.actions.v2__1', 'data' => ['actions' => $actions]],
    ]]]];

    expect(AiStatement::read($report))->toBe('generated');
})->group('SPEC-007');

it('AC15: a label that only looks like an instance is not read', function (mixed $label): void {
    $report = spec007Report(['a' => ['types' => [SPEC007_TRAINED, SPEC007_COMPOSITE]]]);

    expect(AiStatement::read(spec007Labelled($report, $label)))->toBeNull();
})->with([
    'no number' => ['c2pa.actions__'],
    'more after the number' => ['c2pa.actions__1a'],
    'two instances' => ['c2pa.actions.v2__1__2'],
    'a newline after' => ["c2pa.actions__1\n"],
    'another version' => ['c2pa.actions.v3__1'],
    'another case' => ['C2PA.ACTIONS__1'],
    'another assertion' => ['c2pa.other__1'],
    'an integer' => [1],
    'null' => [null],
    'an array' => [['c2pa.actions__1']],
    'true' => [true],
])->group('SPEC-007');
