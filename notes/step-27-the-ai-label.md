# Step 27 (M6.3): SPEC-007, the AI label

2026-11-05. SPEC-007 implemented on step 26. A file that verifies and says it
was made or edited with generative AI now shows that, and the browse can filter
on it.

## What was built

- `AiStatement::read()` takes a `toArray()` array and answers `generated`,
  `edited` or null, in two walks with the gate on ingredients. It reads no
  `->store`, and whatever it cannot read counts as no statement.
- `Verdict::fromReport()` keeps the answer only for `Trusted` and `Valid`.
- The column `ai_statement VARCHAR(16)`, in `Schema`, `DbalVerdictStore`,
  `StoredVerdict` and `VerdictReader`. No `upgrade()`: a development installation
  is installed again, which removes its verdicts and evidence.
- `Presentation::aiLabel()`: the label only for `Trusted` and `Valid`; a value the
  module never writes makes the row unreadable. `VerdictHtml` shows the label as
  the block's first line under the headline, and under the headline in the cell.
- `AiFilterValue` and a second filter in `AdminDisplay`, with its own alias on the
  verdict table, so both filters combine. It also requires `Trusted` or `Valid`.
- Amazon Titan and parent-chain as fixtures, with Titan's licence file.

`AiStatement` is a plain `final class` with untyped constants: `Verdict` uses it
and also loads below PHP 8.3 (SPEC-002 AC12).

## Tests, seen red

Measured with Pest on the host (PHP 8.5.12) against the `omeka` container (Omeka
S 4.2.1, PHP 8.3.36):

- **Unit, before the classes existed:** `Tests: 111 failed (43 assertions)`:
  `Class "ContentCredentials\Check\AiStatement" not found`, and the same for
  `AiFilterValue`.
- **Integration, before the code, with the column added by hand** so that the tests
  fail on behaviour rather than on the setup: `Tests: 16 failed, 12 passed (136
  assertions)`. The column was then dropped again.

Of the 16, the reason was in the tests, not the module, for AC13 (`/s/{slug}/media`
is a 404) and one AC9 case (the value did not fit the column). See below.

Twelve were green before the code: the AC1 rows with no statement, AC7, and the AC9
rows without a label, all of which hold trivially while no code writes a label.

## Falsified

The code was changed temporarily, one change at a time, each restored afterwards;
the run gives the criterion's tests only. Each change made tests fail:

| change | run | result |
|---|---|---|
| `fromReport` reads the statement for every state with a manifest | AC7 | 3 failed |
| the label without the state gate | AC9 | unit 6 failed; integration 6 failed |
| the filter without the state gate | AC11 | 1 failed |
| the filter without `isAdminRequest()` | AC13 | 1 failed |
| the recorded results not required | AC5 | 3 failed |
| a missing delta accepted | AC5 | 1 failed |
| the delta's failures ignored | AC5 | 2 failed |
| generated through any relationship | AC3, AC4 | 3 failed |
| edited reading only the parent line | AC3 | 3 failed |
| integer labels not read | AC6 | 1 failed |
| composite ignored | AC3 | 3 failed |
| any value of the column accepted | AC9 | 25 failed |
| the upsert leaves the column | AC8 | 1 failed |
| the reader omits the column | AC1 | 3 failed |
| a `->store` in the source | AC14 | 1 failed |

**Not falsified:** the cycle guard in AC6. Without it the test would not end, and
that was not run.

## What building found

- **`Expr::in()` quotes its values.** With placeholders in an array, Doctrine reads
  them as string literals: `QueryException: Too many parameters`, seen as a 500 on
  the filtered browse. Run inside the container to find it, since the page and the
  logs said nothing. The condition now binds one array parameter in `IN (…)`.
- **SPEC-007 amendment 1.** The AC's `<script>alert(1)</script>` does not fit
  `VARCHAR(16)`: `ERROR 1406 (22001): Data too long for column 'ai_statement'`. The
  page test uses `<i>spec007</i>`; a unit test gives `Presentation` the original 25
  characters, in every state.
- **SPEC-007 amendment 2.** `/s/{slug}/media` answers 404 in Omeka S 4.2.1. AC13 uses
  the public page of a labelled media item.
- **A test against the spec.** The first test for a relationship that is not a
  string expected `edited`, where AC6 asks for no statement. Test changed and seen red
  (`3 failed, 1 passed`), then the code. A string the rule does not name still reads
  as "any relationship" (`edited`), as the Scope says.
- **Composer's time limit.** `composer check` stopped at 300 seconds, in the
  integration tests, which took 421 seconds over 2 387 media gathered in the test
  environment. `process-timeout` is 0 now, by Maurice's choice. A test that hangs is
  noticed later.

## Measured at the end

`composer check` green, on the host with PHP 8.5.12: 250 unit and 106 integration
tests before this record was written; see the commit's CI run for the versions it
covers.

The labels on the pages (HTTP, on the test installation, after "Check all media"):
Titan and parent-chain "AI-generated (signed)", `c2pa-rs-ocsp.jpg` "AI-edited
(signed)", a tampered parent-chain and `fixture-signed.jpg` none.

## Limits

- **Not looked at in a browser by the assistant.** Only the HTML was tested. The
  `<br>` in the cell and the field name "Content Credentials: AI statement", which
  no spec names, are for Maurice to judge.
- **No real file `Trusted` with a label** in the tests: AC9 sets that row by hand,
  as approved. OpenAI's image would do, until 2027-04-23.
- **The evidence does not hold the statement.** What a past check's label was cannot
  be read back from the evidence; the original can be checked again.
- **`c2pasign-logo.png`** is `Valid` and says trained AI two steps down, without a
  label, as approved. The README says this limit.
- **The IPTC wording** was read through a summarising fetch. The README does not
  quote it.
