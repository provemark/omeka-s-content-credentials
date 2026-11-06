# Step 35: the code review before a release

2026-11-06. Asked by Maurice after M7.10. A review at level "high", run apart from the work, read
`Module.php`, `config`, `src`, `view`, `tools/build.sh` and `tools/env/release.sh`, not the tests.
It returned ten findings and five clean-ups, none run. The assistant then checked each against the
code and, where it could, measured it on the test environment (Omeka S 4.2.1, PHP 8.3, 3 798
checkable media). Probes: `tools/probes/ReleaseCheck/review-*.php`.

## To fix before a release

| # | finding | evidence |
|---|---|---|
| 1 | A run whose process is killed stays `in_progress`. The start buttons go, a new start says "A run is already in progress". Only editing the row or uninstalling helps | measured: `kill -9`, status unchanged, no new job, 0 start buttons |
| 2 | A run that stored nothing is `completed`. `RecheckJob::perform()` catches everything, and `Recheck::run()`'s result is ignored | measured: verdict table renamed, status `completed`, 3 798 "was not stored" in the log, its summary all zeros |
| 3 | `AiStatement` matches the exact label, so `c2pa.actions.v2__1` gives no label, while the verifier strips `__n` (`Verifier.php:222`) and keeps it in the report (`ManifestStore`) | measured on a constructed report: `c2pa.actions.v2` gives `generated`, `c2pa.actions.v2__1` and `c2pa.actions__1` give null. No fixture holds such a label |

Finding 2's `catch` has a reason: step 18 measured that an exception's message reaches the job log
with its path, and SPEC-005 AC7 forbids that. A fix has to keep AC7.

## Smaller

| # | finding | evidence |
|---|---|---|
| 4 | A refused settings form shows the saved DigiCert choice, not the submitted one, so a next save turns it on again | measured for the box; the next save read |
| 5 | A refused start also says "The module was successfully configured" | measured |
| 6 | A damaged bundled list makes `TrustChoice::build()` throw `JsonException`, which it does not catch; the configure page would answer 500 | the exception measured, the 500 read |
| 7 | U+061C, U+200B, U+200D, U+2060 and U+FEFF pass `Text::visible()`, while its comment says nothing can hide. SPEC-003 lists the characters it replaces, so this needs an amendment | measured |
| 8 | A certificate without an organisation gives "Signed by X ()" | read |
| 9 | A partly failed `install()` cannot be retried, as the first table remains | read, not measured; unlikely |

## Decided behaviour, not a defect

"Check media without a result" skips rows in state `error` (SPEC-005 AC2). A placeholder from an
upload that died is retried only by "Check all media", which adds evidence for every media item.

## Clean-ups, not before a release

`TrustConfig::SETTINGS` and `Recheck::STATES` are unused (checked). `tools/build.sh` hides Strauss's
output, so its failure gives no message (read). Not checked: the trust settings built per upload,
one `find` per browse row, `MediaCheck`'s wiring written twice.

## Finding 1: what Omeka's Stop does (measured)

`tools/probes/ReleaseCheck/review-stop.php`:

| run | Stop on its job page | afterwards |
|---|---|---|
| live | 302 | `stopped` after 0.22 s |
| killed | 302 | `stopping`, and still 4 s later: nothing finishes it |

The job page offers Stop only for `in_progress` (`job/show.phtml`, `JobController::stopAction`, read;
none shown for `starting` or `stopping`, measured). `AbstractJob::shouldStop()` copies the row's
status into the running job (read), so a live run would overwrite a status set by hand with
`completed` at its end, unless that status is `stopping`.

So `stopping` is the safe place to act: a live run leaves it by itself within 50 media, and one that
stays there has no process. SPEC-005 is amended on that basis, once approved.

The measurements stopped three runs on the test environment. The job rows were set to `error`, the
table was restored, and no fixture or setting changed. Nothing here touched a real installation.
