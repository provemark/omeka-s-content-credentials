# Step 18 (M4.1): jobs in Omeka S

2026-11-04. M4 checks existing media again, from the admin interface, in
a run that can be interrupted and repeated. Before a spec rests on it:
how Omeka S 4.2.1 runs background work, how a job ends when it fails or
is stopped, who can see a job, and where an administrator could start
one.

## Read (Omeka S 4.2.1 source)

- `Omeka\Job\Dispatcher::dispatch($class, $args, $strategy)`:
  - writes a `job` row with status `starting`, the job class, its
    arguments (JSON) and the current user as owner;
  - hands it to a strategy, by default the service
    `Omeka\Job\DispatchStrategy`, an alias of `PhpCli`.

  `send()` catches every `Throwable` from the strategy, writes
  `(string) $e` (message and stack trace) to the job's log, and sets
  status `error`.
- `PhpCli::send()` runs `php application/data/scripts/perform-job.php
  --job-id …` in the background (`> /dev/null 2>&1 &`). It does so
  through `Omeka\Stdlib\Cli::execute()`, which uses `exec` or `proc_open`
  (config `cli.execute_strategy`, `auto`). The PHP binary comes from
  config `cli.phpcli_path`, or is looked up on the path.
  `perform-job.php` boots Omeka, logs in as the job's owner, records its
  PID, and runs the job with the `Synchronous` strategy.
- `Synchronous::send()`:
  - sets `in_progress` and calls `perform()`;
  - then sets `stopped` if the status had become `stopping`, otherwise
    `completed`;
  - calls `$entityManager->clear()` and merges the job back.

  A shutdown function turns a fatal error into `error`.
- `AbstractJob::shouldStop()` reads the job's status from the database.
  `Dispatcher::stop()` (the admin's Stop button, `/admin/job/{id}/stop`)
  only sets `stopping`; a job that never asks keeps running.
- `JobController::logAction()` serves the log as `text/plain`. The job
  page does not show the log itself.

## Measured

A measurement-only module, `tools/probes/JobProbe/` (never shipped), was
copied into the PHP 8.3 container (Omeka S 4.2.1, PHP 8.3.36, 411 media in
the database) and installed. It dispatches `ProbeJob` from its configure
page, with a chosen strategy and arguments:
- `loops` and `sleep_ms`, with `shouldStop()` checked every loop;
- `throw`;
- `fatal` (calling an undefined function);
- `media` passes over every media row with `EntityManager::find()`, with
  or without `clear()` every 100.

The job writes markers to `/tmp/job-probe.log`. The scripts are
`tools/probes/JobProbe/scripts/measure.php` and `acl.php`.

| case | result |
|---|---|
| `PhpCli`, 5 loops of 200 ms | the configure POST answered 302 in 0.15 s; `dispatch()` returned after 0.055 s with status `starting`; the job ran as its own process (`PHP_SAPI` `cli`, PHP 8.3.36), status `in_progress` then `completed`, PID recorded, `started` and `ended` set |
| `PhpCli`, the job throws `RuntimeException('JOBPROBE thrown /var/www/html/secret/path')` | status `error`; the job log holds the exception's class, **its message with the path**, and the stack trace with file paths (643 bytes) |
| `PhpCli`, a fatal error (undefined function) | status `error`; caught as `Error` by `Dispatcher::send()` and logged the same way, not by the shutdown function |
| `PhpCli`, 300 loops of 100 ms, Stop from `/admin/job/{id}` after 1.5 s | the job page has a Stop form (`confirmform_csrf`); POST 302, "Attempting to stop the job."; the job saw `stopping` at loop 15 and returned; status `stopped` |
| `PhpCli`, three passes over 411 media, without and with `clear()` | memory 12.0 MB at the start, 16.0 MB at the end and peak, either way: too few media to tell (M4.2) |
| `Synchronous` from the configure POST, 5 loops of 200 ms | the request waited 1.14 s; status `completed`, no PID |
| `Synchronous`, the job throws | the request answered 302 with "The module was successfully configured"; the job's status `error` and its log (2 423 bytes) are the only sign |
| `cli.phpcli_path` set to `/nonexistent/php` in the container's `local.config.php` (restored afterwards) | the request answered 302; the job went straight to `error` with "PHP-CLI error: invalid PHP path." and a stack trace in its log; nothing ran |
| a logger line with `<b>markup</b>` | in the log as written; `/admin/job/{id}/log` serves it (as `text/plain`, read in `logAction()`), and the job page shows neither the raw nor an escaped form, since it does not show the log |
| roles opening `/admin/job`, a global admin's job page and its log (test users made and deleted) | `site_admin`: 200, 200, 200; it can read another user's job log. `editor` and `author`: 500 on all three (Omeka's error page, as for denied pages in step 15) |

Afterwards the probe was uninstalled and removed, its job rows deleted,
and `local.config.php` restored. The container's `disable_functions` is
empty: `exec` and `proc_open` are available here, which is not true of
every host.

## What follows for M4 (reasoned)

- **Omeka's dispatcher, not our own process.** A re-check run can be an
  `AbstractJob` dispatched with Omeka's configured strategy. The module
  then calls no `exec` itself; the background process is Omeka's, the same
  one Omeka uses for its own batch edits. The module's rule (no `exec` in
  the verification path) holds: the job calls the same `MediaCheck` as an
  upload.
- **Where PhpCli cannot start** (no `exec`, a wrong PHP path), the job is
  `error` at once and the page that started it still reports success.
  The module must look at the job's status itself and say so, instead of
  trusting the redirect.
- **The job log is readable by site admins**, and Omeka writes an
  exception's message and paths into it. So the job must log only the
  module's own words and counts. It catches its own failures per item, as
  the upload path does (SPEC-002 AC9), and lets nothing from a file or
  from custom trust settings reach the log.
- **Stopping works only if the job asks.** `shouldStop()` costs a query,
  so the job asks between items or every few items. A stopped run that
  is started again must carry on where it can; how is for M4.2 and the
  spec.
- **Synchronous** runs the whole job inside one request and clears the
  entity manager at the end. It suits tests and small runs, not an
  archive.
- **Starting a run:** the configure page is global admin only and
  CSRF-checked (step 15), and `handleConfigForm()` can dispatch (measured
  here). A page of the module's own (route, controller, ACL) is the
  alternative; it was not measured.

## Not measured

- Memory and time over thousands of media, two runs at once, an upload
  during a run, starting again after a stop (M4.2).
- A host with `exec` disabled (simulated only by a wrong PHP path).
- A route and controller of the module's own.
