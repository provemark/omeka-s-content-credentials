# Step 37 (M7.12): a run that fails is not `completed`

2026-11-06. Finding 2 of the review (step 35): a run that stored nothing was `completed`, as
`RecheckJob::perform()` caught everything and `Recheck::run()`'s outcome was not read. SPEC-005
amendment 3 adds AC15, approved by Maurice with both choices: failed at the first check not stored,
and a stop stays `stopped`.

## What was built

- `Check\RunFailed`: a `RuntimeException` whose message and `__toString()` are "The run failed."
  alone. Omeka's dispatcher catches any `Throwable`, logs it as a string and sets the job to `error`
  (read, `Dispatcher::send()`). Letting the real exception through would put its message, paths and
  trace in a log that site administrators can read, so this is the one exception that leaves.
- `Recheck::notStored()` counts what the recorder reports as not stored, and `Recheck::notStoredLine()`
  says it, with "1 check was not stored." for one.
- `RecheckJob::perform()`: a caught `Throwable` logs "The run ended early because of an error." and
  throws `RunFailed`; a completed run with checks not stored logs the count and throws `RunFailed`. A
  stopped run returns normally and counts none.

The configure page already showed `error` as "Failed", with the start buttons and the link (AC14).

## Tests seen red (measured, Omeka S 4.2.1 on PHP 8.3)

```
tests/Unit, filter AC15:        Tests:    6 failed (0 assertions)
tests/Integration, filter AC15: Tests:    2 failed, 2 passed (19 assertions)
```

The unit tests failed on `Class "ContentCredentials\Check\RunFailed" not found` and undefined methods.
The two integration failures were `completed` where `error` was expected. The two that passed hold
what must not change: a stopped run stays `stopped`, and an unreadable original leaves the run
`completed`. They are falsified below.

After building: unit 6 passed (10 assertions), integration 4 passed (37 assertions).

## Falsified (measured)

Each a temporary change, restored after the run; `shasum` confirmed the three source files at the end.

| change | failed |
|---|---|
| no throw after checks not stored | "a run with a check not stored" |
| no throw after an error | "a run that throws" |
| the original exception goes on instead of `RunFailed` | "a run that throws" |
| a stopped run counts too | "a run stopped while checks are not stored" |
| no count line | "a run with a check not stored" |
| no "ended early" line | "a run that throws" |
| `RunFailed` keeps PHP's own string | the unit test, "a run that throws", "a run with a check not stored" |
| failures not counted | the unit test, "a run with a check not stored" |
| an unreadable original counts as not stored | "an unreadable original", "a run with a check not stored" |
| always the plural | the unit test for the count line |

## What a failed run looks like (measured)

`php tools/probes/ReleaseCheck/review-failed-run.php`, on the built code, 4 084 checkable media:

| case | status | log |
|---|---|---|
| the run throws (verdict table renamed, "without a result") | `error` | 2 lines, 127 bytes: "ended early", "The run failed." |
| no write is stored (verdict table renamed, "all") | `error` | 4 096 lines, 317 089 bytes, ending in "4084 checks were not stored." and "The run failed." |
| the run's database connection is killed | `error` | 14 lines, 943 bytes: media 914 and 916 not stored, the other 4 082 checked, "2 checks were not stored.", "The run failed." |
| Stop | `stopped` | "Stopped after 150 of 4084 media." |

In all four: no path, no `SQLSTATE`, no "Exception" and no "#0" in the log, and no "Exception" in
`logs/application.log`. No job was left unfinished.

**The killed connection settles what the review left open.** Its attempt, not in step 35's record as
it gave no answer, waited 20 seconds, too short for a run still going. This one waits for the process
to end. Exactly one connection was killed,
and the run went on: DBAL 2.13 closes a connection it finds lost, and the next query connects again
(read, `Connection::handleExceptionDuringQuery()`). So two checks are lost, the run says so, and its
status is true.

## Reasoned, not measured

- **A database that stays away.** Then the final status cannot be written either, so the row stays
  `in_progress` and the process ends. That is the case AC14's button is for.
- **After a failed run, "Check all media" is the way to repeat it**, not "only those without a result":
  a check whose write failed part-way can leave its placeholder `error` row, which counts as a result.
  The README says so.

## Not measured

- The page in a browser, by the assistant.
