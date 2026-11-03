# SPEC-000: Spec traceability tooling

| Field      | Value                                             |
|------------|---------------------------------------------------|
| Status     | draft                                             |
| Author     | Maurice van Loon                                  |
| Approved   | —                                                 |
| Supersedes | —                                                 |

> Lifecycle: `draft` → maintainer approves → `approved` → tests-first →
> `implemented` (Traceability filled). No implementation code while `draft`.
> Only the Traceability section of an `approved` spec may change without a new
> approval; everything else needs a proposed amendment.

## Problem

This project is spec-driven: every feature starts as a `specs/SPEC-###-*.md`
file, no code is written while that file is `draft`, tests come before the
implementation and carry the spec's ID as a Pest group, and a spec becomes
`implemented` only when its Traceability table names the tests that prove
each acceptance criterion. Those are rules on paper. Without a check, a
test without a spec, a spec marked `implemented` without a test, or tests
written against a `draft` would all pass unseen.

`bin/spec-check.php` closes that gap: one script that reads `specs/` and
`tests/` and fails when they disagree. It is tooling, not product code: it
lives in `bin/`, outside the Deptrac layers and outside the module Omeka S
loads, but it is analysed by PHPStan at level max and formatted by Pint
like everything else, because a checker that is itself unchecked is a bug
waiting.

It is a port of SPEC-000 of `provemark/c2pa-verifier` (as of v0.10.0,
including its amendments 1 and 2), where the same rules have run since
2026-09-19. The rules those amendments added (here AC11 and AC12) are
plain criteria in this spec, because they hold from the start. One
criterion is new (AC13).

SPEC-000 is numbered zero on purpose: it is not a feature of the module but
the tool that makes every later spec's promises checkable.

## Scope

**In scope**

- Reading every `specs/SPEC-*.md` and its Status field (`specs/TEMPLATE.md`
  is not a spec).
- Reading every `*Test.php` under `tests/`, except `tests/Fixtures/`, for
  `->group('SPEC-###')` calls, including multi-argument calls such as
  `->group('SPEC-001', 'SPEC-004')`.
- The cross-checks AC1–AC13 below, reported one finding per line, exit code
  0 only when there are no findings.
- Running as the first step of `composer check`.
- Being testable on a directory other than the repository root, so the tests
  can feed it small fixture trees under `tests/Fixtures/spec-check/`.

**Out of scope** (each needs its own spec before it may be built)

- Checking that a test actually exercises the criterion it is listed under.
  Traceability rows are declarations; whether a test tests anything is the
  "seen red" rule, which is a human check.
- Parsing acceptance criteria out of the Behavior section, or counting them.
- Any check on `src/` or `Module.php` (Deptrac and PHPStan own that).
- Warnings. Every finding is an error; there is no advisory category, because
  an advisory is an error the reader learns to skip.

## Behavior

Acceptance criteria as Given/When/Then. Each is individually testable and is
covered by a Pest test in `tests/Unit` tagged `->group('SPEC-000')`; none
needs Omeka S. Unknown input is an error, never an assumption: this
project fails closed.

- **AC1 — every spec file has a valid status**
  - Given a `specs/` directory whose every `SPEC-*.md` file has a Status row
    whose value is exactly one of `draft`, `approved`, `implemented`,
    `superseded`
  - When the checker runs
  - Then it reports no finding for status and lists each spec with its
    status in its output

- **AC2 — a test group must name an existing spec**
  - Given a test file carrying `->group('SPEC-042')` and no file
    `specs/SPEC-042-*.md`
  - When the checker runs
  - Then it reports one finding naming `SPEC-042` and the test file, and
    exits 1

- **AC3 — every test file carries a spec group**
  - Given a `*Test.php` file under `tests/` that contains no
    `->group('SPEC-###')` call at all
  - When the checker runs
  - Then it reports one finding naming that file, and exits 1

- **AC4 — a draft spec has no tests yet**
  - Given a spec with Status `draft` and a test file carrying its group
  - When the checker runs
  - Then it reports one finding naming the spec and the file ("tests precede
    approval"), and exits 1

- **AC5 — an implemented spec has at least one test**
  - Given a spec with Status `implemented` and no test file carrying its
    group
  - When the checker runs
  - Then it reports one finding naming the spec, and exits 1

- **AC6 — an implemented spec has a filled Traceability table**
  - Given a spec with Status `implemented` whose Traceability table has at
    least one row whose Test cell is `—` (or empty)
  - When the checker runs
  - Then it reports one finding naming the spec and the criterion of that row,
    and exits 1

- **AC7 — a spec without a recognisable status is an error** *(required:
  error / malformed input)*
  - Given a `specs/SPEC-*.md` file with no Status row, or a Status row whose
    value is not one of the four (for example `Draft`, `approved?`, `done`)
  - When the checker runs
  - Then it reports one finding naming the file and the offending value (or
    "missing"), exits 1, and does not treat the spec as any status for the
    other checks

- **AC8 — clean tree, exit 0; one line per finding otherwise**
  - Given a tree that triggers none of AC2–AC7 and AC11–AC13
  - When the checker runs
  - Then it exits 0 and its output contains the word `OK` followed by the
    number of specs and test files scanned
  - And given a tree that triggers several of them
  - Then every finding is one line of the form `SPEC-###: <reason>` (or
    `<file>: <reason>` when no spec applies), and the exit code is 1
  - And given this repository itself
  - Then it exits 0, with the fixture trees under `tests/Fixtures/` not
    counted as test files

- **AC9 — multi-argument group calls are read completely**
  - Given a test file carrying `->group('SPEC-001', 'SPEC-004')` and both
    specs exist and are `approved`
  - When the checker runs
  - Then both specs count as having a test, and no finding is reported

- **AC10 — the check is the first step of `composer check`**
  - Given the repository root
  - When `composer check` runs
  - Then `bin/spec-check.php` runs before every other step, and a non-zero
    exit stops the chain

- **AC11 — a test that names a criterion finds it in its spec's Traceability**
  - Given a test file whose declarations are named `AC1: …`, `AC2: …` (both
    with a Traceability row, one labelled `AC2 (amendment 1)`), a
    declaration with no criterion in its name, `AC3: …` with no row, and
    `test('SPEC-001 AC9: …')` with no row. All of them carry
    `->group('SPEC-001')`, and one holds a `"{$var}"` string
  - When the checker runs
  - Then it reports exactly two findings, one per orphan, each naming the
    spec, the file and line of the declaration, and the criterion. The
    spec is the one in the test's own name if it names one, else the
    groups of that declaration's own statement. The file is read with
    PHP's tokenizer, so a comment or a string cannot fake a declaration.

- **AC12 — every open question in an approved or implemented spec has a status**
  - Given a spec with status `approved` or `implemented` whose `## Open
    questions` section holds an item (a line starting `- ` or `N. `) that
    is not struck through (`- ~~`) and has no line starting `*Status`
    before the next item, the next heading or the end of the section
  - When the checker runs
  - Then it reports one finding per such item, naming the spec and the
    line, and exits 1. A `draft` spec may have open questions without a
    status. The status line's content is not checked; its words are the
    reviewer's.

- **AC13 — one file per spec ID** *(new here; error / malformed input)*
  - Given two files `specs/SPEC-007-a.md` and `specs/SPEC-007-b.md`, or a
    file such as `specs/SPEC-7-x.md` or `specs/SPEC-0071-x.md` whose name
    does not start with `SPEC-`, three digits and `-`
  - When the checker runs
  - Then it reports one finding naming the ID and both files (or the file
    whose name is malformed), exits 1, and the duplicated ID takes part in
    no other check

## References

- Specification: none — this spec governs the project's own process, which is
  described in `NOTES.md`, `docs/milestones.md` and the spec template.
- Origin: `provemark/c2pa-verifier` v0.10.0, `specs/SPEC-000-spec-traceability.md`
  (AC1–AC12, amendments 1 and 2) and `bin/spec-check.php`.
- Oracle: none external. The acceptance criteria are measured against fixture
  trees under `tests/Fixtures/spec-check/`, each a minimal `specs/` +
  `tests/` pair built for one criterion.
- Why AC13: the verifier's script, read on 2026-11-03, keys the specs by
  ID, so of two files with the same ID only the last counts and the first
  takes part in nothing; and its filename pattern `^(SPEC-\d{3})` reads
  `SPEC-0071-x.md` as SPEC-007. Measured on the pattern and the keying alone
  (`php -r`, PHP 8.5.12): `SPEC-0071-x.md` gives `SPEC-007`, and two files
  `SPEC-007-a.md` (draft) and `SPEC-007-b.md` (implemented) leave
  `{"SPEC-007":"implemented"}`. The whole script was not run on such a
  tree.

## API sketch

```php
// bin/spec-check.php — a script, not a namespaced class. It defines the
// functions below and, when invoked from the command line, runs them on the
// repository root and exits with the result's code.

declare(strict_types=1);

final readonly class SpecCheckResult
{
    /** @param list<string> $findings  @param array<string, string> $specs */
    public function __construct(public array $findings, public array $specs, public int $testFiles) {}
    public function exitCode(): int;
    public function render(): string;
}

function specCheck(string $root): SpecCheckResult;

// The CLI entry point prints each spec with its status and each finding on
// its own line, then either "OK: N spec(s), M test file(s)" (exit 0) or
// "FAIL: K finding(s)" (exit 1). Tests require_once the file and call
// specCheck() on fixture roots; the CLI part is guarded so requiring the
// file has no side effects.
```

## Open questions

- Non-blocker: whether `superseded` specs should still be allowed to have
  tests carrying their group (the tests would be stale). Proposed: allowed,
  no finding; revisit when the first spec is superseded (as in the
  verifier).

## Traceability

Filled when status becomes `implemented`. Every acceptance criterion maps to at
least one test; every source file maps back to this spec.

| Acceptance criterion | Test (file :: name / group) | Source (file/symbol) |
|----------------------|-----------------------------|----------------------|
| AC1                  | —                           | —                    |
| AC2                  | —                           | —                    |
| AC3                  | —                           | —                    |
| AC4                  | —                           | —                    |
| AC5                  | —                           | —                    |
| AC6                  | —                           | —                    |
| AC7                  | —                           | —                    |
| AC8                  | —                           | —                    |
| AC9                  | —                           | —                    |
| AC10                 | —                           | —                    |
| AC11                 | —                           | —                    |
| AC12                 | —                           | —                    |
| AC13                 | —                           | —                    |
