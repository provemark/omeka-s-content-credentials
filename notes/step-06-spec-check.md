# Step 06: SPEC-000, the spec traceability check (M0.6)

2026-11-03 (template and draft, M0.6a) and 2026-11-04 (tests and script,
M0.6b). SPEC-000 was approved by Maurice van Loon on 2026-11-04 and is now
`implemented`.

## What was built

| file | what |
|---|---|
| `specs/TEMPLATE.md` | Tracefern's template, pointed at Omeka S |
| `specs/SPEC-000-spec-traceability.md` | the spec: AC1–AC12 from the verifier, AC13 new |
| `tests/Unit/SpecCheckTest.php` | 16 tests, group `SPEC-000` |
| `tests/Fixtures/spec-check/` | the verifier's 13 fixture trees, unchanged, plus `duplicate-id` and `malformed-name` (AC13) |
| `bin/spec-check.php` | the verifier's script (v0.10.0) with AC13 |
| `tests/Pest.php`, `phpunit.xml` | Pest with one suite, `Unit` |
| `composer.json` | scripts `spec-check`, `test`, `check` (spec-check first) |

What differs from the verifier's script: spec files are grouped by ID
first, two files with one ID are one finding and the ID is left out of
every other check (also of AC2, so a test carrying it does not add a
second, misleading finding); filenames must start with `SPEC-###-`; the
AC12 message no longer says "(amendment 2)". The verifier's test that
every `toContain` takes one needle was not ported: it is not one of
SPEC-000's criteria.

## Seen red

Host PHP 8.5.12, Pest 4.7.8.

1. Before `bin/spec-check.php` existed: `vendor/bin/pest --testsuite=Unit`
   exit 1, "Failed opening required '…/bin/spec-check.php'". A crash at
   load time, which shows nothing per criterion, so:
2. Against a stub with the sketch's API (`specCheck()` returns no findings,
   no specs, no test files; `exitCode()` 0; `render()` empty): exit 1, 15
   of 16 tests failed. AC10 passed, because `composer.json`'s scripts had
   been written before the test ran.
3. AC10 on its own, with `scripts.check` reordered to
   `["@test", "@spec-check"]`: failed ("Failed asserting that two strings
   are identical"). With the order restored: passed. `composer.json` was
   copied back from a saved copy.

## Green, and measured

1. With the script: `vendor/bin/pest --testsuite=Unit` 16 passed (43
   assertions). With SPEC-000 `implemented` and its Traceability filled,
   `composer check` runs spec-check ("OK: 1 spec(s), 1 test file(s)") and
   then the tests, all passing.
2. In `php:8.3-cli` and `php:8.4-cli` (the repository piped in with
   `tar`): spec-check OK and 16 passed on both.
3. **AC10 by hand**, as in the verifier (a test that runs `composer check`
   from inside `composer check` would loop): on a copy of the tree in the
   scratch directory with SPEC-000 set to `implemented` and its
   Traceability still empty, `composer check` printed 13 findings ("status
   implemented but Traceability row ACn names no test"), "Script @php
   bin/spec-check.php handling the spec-check event returned with error
   code 1", exit 1; Pest's summary never appeared.

## Reasoned, not measured

- That the fixture trees are never collected by Pest: `phpunit.xml` lists
  `tests/Unit` only, and the run counts 16 tests, all from
  `SpecCheckTest.php`. Inferred from the count, not from a listing.
