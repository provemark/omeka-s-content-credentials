# Step 21 (M4.4): checking existing media again (SPEC-005)

2026-11-04. SPEC-005 was approved by Maurice with every recommendation.
This step built it: tests first, seen red, then the code.

## What was built

- `src/Check/Recheck.php`: the run's step over media, with no Omeka in
  it. It records each media item through `Recorder`, as an upload does,
  in ascending id:
  - it asks `shouldStop()` every 50 items;
  - it writes "Checked N of M media." every 500 items;
  - it writes an end line with a count per state.
- `src/Check/RecheckCounts.php`: a `VerdictStore` around the real one
  that counts states; Recorder's `interrupted` placeholder is not
  counted.
- `src/Omeka/RecheckJob.php`: an `AbstractJob`.
  - It builds the trust settings once.
  - It reads the checkable media (the listener's `MEDIA_TYPES`, with an
    original) through DBAL, 200 at a time, after the last id, and only
    those without a row for "without a result".
  - It catches everything, so that Omeka never writes an exception into
    the job log (step 18), and writes "The run ended early because of an
    error." instead.
- `src/Omeka/RecheckRuns.php`:
  - the latest run, read from Omeka's `job` table by class;
  - `start()`, which refuses while a run is `starting`, `in_progress` or
    `stopping`, and reports a run that is `error` at once (or a `null`
    from `dispatch()`) as not started;
  - the section's HTML.
- `src/Display/RecheckSection.php`:
  - the section of the configure page, with Omeka's statuses in the
    module's words (`error` is "Failed");
  - two start buttons (`content_credentials_recheck` = `all` or
    `without_result`), left out while a run is going on.
- `Module.php`: `getConfigForm()` appends the section. `handleConfigForm()`
  starts a run when a start button sent the form, and saves nothing else
  (AC11).

## Decided while building (reasoned, not in the spec)

- **After a start, Omeka shows the modules page.**
  `handleConfigForm()` returns `true`, so Omeka adds "The module was
  successfully configured" and redirects to the modules page
  (`ModuleController::configureAction()`, read). The module's own
  message comes with it: "Checking existing media has started. Its
  status is on this module's configure page.", or the reason when no run
  started.

  Returning `false` would keep the configure page, but Omeka then adds
  "There was a problem during configuration", which would be false. It
  would also leave a POST that a reload repeats.
- **Omeka's Submit button stays the default.** It comes before the
  module's form in `configure.phtml` (read), so Enter still saves the
  settings and does not start a run.
- **Stopping** is Omeka's Stop on the job page. The section links to the
  job page and says so while a run is going on.

## Tests

- `tests/Unit/RecheckTest.php` (AC7, the throwing store; AC13): red, with
  `Class "ContentCredentials\Check\Recheck" not found` (2 failed), then
  green.
- `tests/Integration/RecheckTest.php` (AC1–AC12, 14 cases).
  - Before the code: 11 failed, 3 passed. The three were AC10's roles,
    which Omeka's configure ACL refuses with or without the module's
    code.
  - AC10 falsified: with a `global_admin` case added, that case failed
    (the page is reachable), and the test was restored. The job-count
    assertion of AC10 cannot be falsified this way: a refused user
    cannot get the form's CSRF token, so its post fails on CSRF first.
  - AC11's red run waited 300 s for a job id of 0. The helper now
    returns at once when no job exists; it did not change the result.
- Runs over many media use clones: media rows inserted with SQL
  (MariaDB's `seq_1_to_N`), whose originals are hard links to one
  uploaded fixture. They are removed with their `resource` rows
  (`ON DELETE CASCADE` to `media` and to the verdict table) and the
  links.
- After the code: 12 of 14 green. AC8 and AC9 failed, which I traced to
  opcache.

### Measured: opcache delays a change to `local.config.php`

AC8 sets `cli.phpcli_path` to a missing PHP in the container's
`local.config.php` and restores it afterwards. Run by itself, it got a
`completed` job: the change had not been seen. In the full run, AC9
started right after the restore and got a `Failed` job: the restore had
not been seen.

The container's PHP has `opcache.validate_timestamps = On` and
`opcache.revalidate_freq = 2` (`php -i`, PHP 8.3.36), so Apache may serve
a cached `local.config.php` for up to 2 s after it changes. The test now
waits 3 s after the change and after the restore. Then all 14 cases
passed.

Step 18's measurement of the wrong PHP path got `error`, so the change
had been seen there; the timing happened to allow it.

## Not covered

- Omeka's `job` rows of this module stay after uninstalling, as Omeka's
  own job rows do. Whether uninstall should remove them is for M7
  ("uninstall removes everything").
- The configure page still says "Changes apply to new uploads; media
  checked before keep their verdict." (SPEC-004). Since this step, a run
  can check them again. Changing that text is an amendment to SPEC-004,
  to be proposed.
- A browser look at the section.
