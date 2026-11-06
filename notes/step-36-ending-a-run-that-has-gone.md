# Step 36 (M7.11): ending a run whose process has gone

2026-11-06. Finding 1 of the review (step 35): a run whose process was killed stayed `in_progress`
and blocked every new run. SPEC-005 amendment 2 adds AC14, approved by Maurice with both choices: in
two steps, Stop first; the status becomes `error`, shown as "Failed".

## What was built

- `RecheckSection`: at `starting` and `stopping` an explanation and a button "Mark this run as ended",
  in a field of its own, `content_credentials_end_run`, whose value is the job's id. A field of its
  own keeps AC9 true, and the id means a page left open cannot end a later run. The hint on Stop now
  shows only at `in_progress`, where the job page offers it.
- `RecheckRuns::end()`: one `UPDATE … WHERE id = ? AND class = ? AND status IN ('starting', 'stopping')`,
  so a run that moved on, or another class's job, is left alone. A failure of that statement is
  refused like the others.
- `Module::handleConfigForm()`: the button does that alone and saves nothing. Like a refused start,
  a refusal still also shows Omeka's "successfully configured" (finding 5, M7.14).

## Tests seen red (measured, Omeka S 4.2.1 on PHP 8.3)

```
tests/Unit, filter AC14:        Tests:    3 failed, 5 passed (55 assertions)
tests/Integration, filter AC14: Tests:    7 failed, 6 passed (76 assertions)
```

Of the unit tests that passed, four hold that there is no button where none belongs. Of the integration
tests that passed, three are this AC's access test, as Omeka already refuses the page, and three are
SPEC-002's own AC14, caught by the filter. The falsifications below cover the former two.

The first red run hung: a failing test left its job at `starting`, and the next test's set-up waits
five minutes for such a row. That was the assistant's error in the new test, which now cleans up.

The real `kill -9` test ran up to the missing button, so it measured again what step 35 found: killed,
the row stays `in_progress`, and Stop leaves it at `stopping`.

## Falsified (measured)

Each a temporary change, restored after the run; `shasum` confirmed the three source files at the end.

| change | failed |
|---|---|
| no `class` condition | "a job of another class" |
| no `status` condition | "any other status", all 4 |
| the configure page opened to every role (`allow`) | access test, `editor` and `author` |
| Omeka's `deny` for `site_admin` removed | access test, `site_admin` |
| the button also saves the settings | the `kill -9` test |
| `stopped` instead of `error` | the `kill -9` test |
| no line in the log | the `kill -9` test |
| a button at `in_progress` | the section's unit test |
| no button at `starting` | the section's unit test, and "stuck at starting" |
| the id not escaped | the section's unit test, both datasets |

In the third row `site_admin` stayed green, as Omeka denies it `configure` explicitly (`AclFactory`,
read), hence the fourth row.

## Not measured

- A run that is alive when the button is pressed: reasoned, SPEC-005's known limit in amendment 2.
- The page in a browser, by the assistant.
- `starting` is reached in the tests by setting the row, not by a real process killed that early.
