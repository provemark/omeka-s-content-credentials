# Step 29 (M7.2): what uninstall leaves behind

2026-11-05. M7's aim is that uninstall removes everything. Step 21 recorded that
the module's rows in Omeka's `job` table stay, and ADR-0005 that the uninstall
confirmation does not warn that the evidence goes. This step compares the whole
installation before and after, measures what a run does when the module is
uninstalled under it, and which hook the confirmation offers. Nothing in the module
changed.

## Setup (measured)

- BuildProbe's clean environment (step 28): Omeka S 4.2.1, PHP 8.3.36, MariaDB 11.4,
  the zip of `tools/probes/BuildProbe/build.sh 0.30.0` built at HEAD `c2bb706` and
  unpacked in `modules/`.
- `tools/probes/UninstallProbe/scripts/measure.php`, never shipped: `before`, `use`,
  `after`, `jobs`, `midrun <mode>`, `hook`.
- A snapshot is `mariadb-dump --skip-extended-insert` (schema and every row, without
  `AUTO_INCREMENT` values and the `session` table) and `find /var/www/html /tmp -type f`
  with paths and sizes (9 450 files; contents not hashed), compared with `diff`.
- "Use" is: install; three uploads (`Valid`, `Trusted`, `none`); the DigiCert setting
  saved; a re-check, completed; the evidence page opened. Then uninstall, and Omeka's
  three items deleted through the API.
- `tools/probes/UninstallProbe/` is also a module writing a marker at `view.details`,
  with `PROBE-DESCRIPTION` as its description in `module.ini`.

## Measured

### Before, against after everything

The schema and all rows of all tables but `session`: two added lines.

| line | whose |
|---|---|
| `job`: id 1, owner, pid, `completed`, `ContentCredentials\Omeka\RecheckJob`, `{"media":"all"}`, its log, two dates | the module's |
| `api_key`: "SPEC-002 tests" | the measuring script's own (reasoned: each run of the helper makes one) |

Files: two added `/tmp/sess_*`, PHP's session files of the script's logins. No Doctrine
proxy, no log, no file in `files/` is left. The module's folder itself is unchanged,
as Omeka does not delete it. Before the items were deleted, the additional rows were the
items' own (`item`, `media`, `resource`, `fulltext_search`) and the job.

### The job rows

| question | result |
|---|---|
| what the log holds | "Checking 3 media." and "Checked 3 media: 1 Trusted, 1 Valid, 0 Invalid, 1 none, 0 error." (155 bytes); no file text, no verdict per media |
| which table refers to `job`, by `information_schema` | none |
| with the module gone | `/admin/job`, `/admin/job/1` and `/api/jobs/1` 200, each naming the class; the job's `log` 200 |
| `DELETE FROM job WHERE class = …` | one row; `/admin/job` 200; the job's page and API 404 |

### Uninstalling during a run

8 001 media (hard-linked clones), a run started, uninstall by the admin's form while
the job is `in_progress`; the job's process followed by its pid.

| case | process | job row | what stays |
|---|---|---|---|
| uninstall only | gone in about 1 s | `completed`; log "not stored" 7 864 times, last line "Checked 8001 media: 0 Trusted, 137 Valid, 0 Invalid, 0 none, 0 error." | no table; admin 200 |
| uninstall, job rows deleted | gone in 1.0 s | none | no table; admin 200 |
| uninstall, reinstall | runs on, gone after 13.2 s | `completed`; "not stored" 2 361 times | **the old run fills the new table:** 5 199 `Valid` with evidence, 2 `error` `interrupted` |
| uninstall, job rows deleted, reinstall | gone in 0.6 s | none | new tables with 0 rows |

Times count from the start of the uninstall. `logs/application.log` stayed 0 bytes
throughout; why was not looked into. Why the process ends when its row is deleted was not
read either, and its standard error was not captured.

### The uninstall confirmation

| case | result |
|---|---|
| the page | `/admin/module/uninstall-confirm?id=…`, 200, a fragment without layout; the module page loads it in a sidebar (`browse.phtml`, read) |
| `view.details` | fires there, with the module as `entity`, once, **after the form**; not on the module list |
| the listening module deactivated | no marker: an inactive module's listeners do not run. The page still answers 200, and uninstalling works |
| `description` in `module.ini` | shown on the module list and on the confirmation of that module, active or deactivated |

The "uninstall only" row is from a run that found rows left by an earlier case, so the
wait for 300 rows ended at once and uninstall came early: hence 137 `Valid`. An earlier run
that waited for its 396 rows also ended `completed`, with "not stored" in its log.

## What follows (reasoned, for M7.4)

- **Job rows.** They are the one thing left. Options:
  - Delete them in `uninstall()`: it meets M7's aim, nothing refers to them, and it also
    ends a run at once, so that a reinstall starts empty. The cost is the run's history,
    which holds only counts. Recommended, with the delete **before** the drop.
  - Leave them, as Omeka leaves its own, and say so. Then a run goes on after a reinstall
    and fills the new table, and a run under an uninstall ends `completed` with thousands
    of "not stored". That is a report nobody would read as a failure.
- **The warning.** A sentence in `description` shows in every state and needs no code;
  `view.details` shows only for an active module, so not for a deactivated one, the
  route an administrator may well take. Both land below the button. Recommended: the
  description. It also appears on the module list, and whatever omeka.org takes from
  the file (M7.6).
- **Nothing else.** Settings, tables, proxies and files are clean; SPEC-001's and SPEC-004's
  uninstall tests already cover tables and settings.

## Not measured

- The `session` table, and the contents of files (paths and sizes only).
- How the sidebar looks; no browser. Where in the sidebar the text falls on screen.
- A run with Omeka's other dispatch strategies than the default.
- A job row deleted without an uninstall; what `Stop` does on a page of a module that is gone.
- The zip's folder deleted without uninstalling first.
- The same on other database versions than MariaDB 11.4.

Cleanup: the probe's project with its volumes taken down, `build/probe/` removed.
