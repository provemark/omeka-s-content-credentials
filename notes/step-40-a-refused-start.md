# Step 40 (M7.14, finding 5): a refused start or end does not say the module was configured

2026-11-07. Finding 5 of the review (step 35): a refused start also said "The module was successfully
configured". The refused end of AC14 had the same defect in the same method, which the review did not
name. SPEC-005 amendment 4 adds AC16, approved by Maurice with both choices: the refused end too, and
the success message stays on a start or end that succeeds.

## What was built

`Module::handleConfigForm()` returns false on a refused start or end, true only when it succeeded.
Omeka then adds "There was a problem during configuration" and renders the configure page, where the
run's status is, instead of redirecting (read, `ModuleController::configureAction()`). A false is the
only way to withhold Omeka's success message, short of taking it out of the messenger afterwards.

The submitted settings are not given back on this path: the button saves nothing (AC11), so the form
shows what is stored.

## Tests seen red (measured, Omeka S 4.2.1 on PHP 8.3)

```
vendor/bin/pest --testsuite=Integration --filter='AC16|AC9: one run|AC14: a run in any|AC14: a job of another'
Tests:    16 failed (36 assertions)
```

15 on `302 is identical to 200`, and the test with a job of another class on its reason not being on
the page that came back. After building, with AC1, AC11 and the successful end of AC14 in the filter:
19 passed (155 assertions).

The tests of AC9 and AC14 that expected a 302, or read the message from the next page, changed with
the amendment.

## Falsified (measured)

Each a temporary change, restored after its run. The first two ran on the 19 tests above. That took
over five minutes a change, as those tests start real runs, so the run was stopped with an interrupt,
`Module.php` read back as built, and the other eight ran on 11 faster tests:
`AC16: (a kind|an end|a refusal)|AC1: a run checks|AC14: a run whose`.

| change | failed |
|---|---|
| a refused end returns true | 10 of 19 |
| a refused start returns true | 6 of 19 |
| a refused end without its reason | 4 of 11 |
| a refused start without its reason | 3 of 11 |
| a refused end goes on to save the form | 5 of 11 |
| a refused start goes on to save the form | 4 of 11 |
| a refused start gives back the submitted text | 1 of 11 |
| a refused start gives back the submitted DigiCert choice | 1 of 11 |
| a start that succeeds returns false | 2 of 11 |
| an end that succeeds returns false | 1 of 11 |

## Known limit, decided

A start or end that succeeds still says "The module was successfully configured" next to the module's
own message, and goes to the modules page. It is Omeka's message; the alternatives are reporting a
problem, or removing it from the messenger.

## A case the falsification missed (measured)

`composer check` failed on AC8 after building: `Failed asserting that 200 is identical to 302`,
1 failed, 140 passed. A start whose job fails at once ("The run could not start; see its job page.")
now returns false too, and the AC8 test expected a redirect. The step's own filter had left AC8 out,
and this note had listed the case as not measured. Maurice extended amendment 4: such a start is
answered as a refusal, though a job was dispatched, as "successfully configured" next to a failed run
is untrue. The AC8 test now expects 200, the reason, Omeka's problem message and no success message.
It passes on the built code (11 assertions), and fails on `302 is identical to 200` when a refused
start returns true again.

## Not measured

- The page in a browser, by the assistant.
