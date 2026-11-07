# Step 38 (M7.13): the AI label reads instance labels

2026-11-07. Finding 3 of the review (step 35): `AiStatement` compared an assertion's label exactly, so
`c2pa.actions.v2__1` gave no label, while the verifier itself removes a final `__n` before it reads a
label (read, `Verifier.php:222`, `ActionsCheck.php:325`). SPEC-007 amendment 3 adds AC15, approved by
Maurice.

## What was built

`AiStatement::isActions()`: a label that is a string, with a final `/__\d+\z/` removed, compared
exactly with the two actions labels. The pattern is the verifier's. Nothing else in `src/` or `view/`
names these labels (`grep`). A verdict already stored keeps its answer until it is checked again.

## Tests seen red (measured, PHP 8.3)

```
vendor/bin/pest tests/Unit/AiStatementTest.php --filter=AC15
Tests:    13 failed, 11 passed (36 assertions)
```

The 11 that passed hold what must not change: labels that only look like an instance answer null, and
the reports without `__1` keep their answer. They are falsified below.

After building: `tests/Unit/AiStatementTest.php`, 136 passed (267 assertions).

## Falsified (measured)

Each a temporary change, restored after the run; `shasum` confirmed `AiStatement.php` at the end. Run
on `tests/Unit/AiStatementTest.php`.

| change | failed |
|---|---|
| no stripping at all | 13 |
| `\d*` instead of `\d+` | 1: `c2pa.actions__` |
| one digit only | 4: `__12` |
| no end anchor | 1: `c2pa.actions.v2__1__2` |
| `str_starts_with` | 5 |
| no guard for a label that is not a string | 3, a `TypeError` |
| instance labels only for `c2pa.actions.v2` | 4 |
| instance labels only for `c2pa.actions` | 9 |
| any case | 1 |
| any version | 1 |
| only the first actions assertion read | 1: an instance next to the base label |
| `$` instead of `\z` | **none** |
| a cast of scalars instead of the guard | **none** |

The two that survive change no answer, so no test can tell them apart:

- **`$`** matches before a final newline, but only `__1` is removed and the newline stays:
  `preg_replace('/__\d+$/', '', "c2pa.actions__1\n")` gives `"c2pa.actions\n"` (measured), which the
  exact compare refuses. `\z` stays, as the verifier's pattern and the plainer statement.
- **The cast** turns an integer, float or boolean into digits or an empty string, none of which is an
  actions label (reasoned). The guard stays, as it says what is meant.

## Real files (measured)

`tools/probes/ReleaseCheck/review-ai-instance-labels.php`, run before building: 802 distinct files,
607 with a manifest, none with an instance label on an actions assertion. So on these files the
answer cannot change, and the rule rests on constructed reports, as AC2 to AC6 do (the known limit in
amendment 3).

## Read, not measured

- **Tracefern has the same exact compare** (`C2PA_Verifier_WP/src/Outcome.php:289`, from the earlier
  session's reading; not opened again here). It is not changed by this step.
