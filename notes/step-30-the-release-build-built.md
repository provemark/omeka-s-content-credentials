# Step 30 (M7.5): the release build and the uninstall, built

2026-11-06. SPEC-008, on the measurements of steps 28 and 29 and ADR-0006. There is now
a zip, tested in a clean Omeka S, and an uninstall that removes everything.

## What was built

- `tools/build.sh [tree-ish]`: `build/ContentCredentials-<version>.zip` from HEAD by
  default, with Strauss 0.31.1 checked by SHA-256 before it runs.
- `tools/env/release.sh [--integration]` and `tools/env/compose.release.yaml`: the zip of the
  working tree, unpacked in a clean Omeka S (its own project, ports 8090 and 8091, rebuilt at
  each run and taken down afterwards), with `tests/Release` or, with `--integration`,
  `tests/Integration` run on it.
- `VerifierLoader::sourceDirectory()`: `vendor-prefixed/` when it exists, otherwise `vendor/`. The
  three call sites pass the module's directory. The build needs no `sed`.
- `uninstall()` deletes the rows of `Schema::JOB_CLASS` before it drops the tables.
- One sentence in the description of `config/module.ini`.
- `composer test:release` as the last step of `composer check`; a job `release` in CI;
  `extra.strauss` in `composer.json`, the lock's content hash refreshed.

## Tests seen red (measured)

Before any of the code, with Omeka S 4.2.1 on PHP 8.3:

```
tests/Unit, AC9 and AC13:            Tests:    5 failed (0 assertions)
tests/Integration, group SPEC-008:   Tests:    3 failed (7 assertions)
tests/Release, AC1 to AC4 and AC8:   Tests:    5 failed (4 assertions)
```

With `build.sh` and the loader in place and `uninstall()` not yet changed, on a real zip:

```
⨯ it AC11: uninstalling after use leaves nothing of the module
⨯ it AC14: uninstalling during a run
Tests:    2 failed, 18 passed (625 assertions)
```

AC14 failed on its job row still being there. AC5, AC6, AC7 and AC10 were green as soon as the
zip existed, as step 28 had measured that behaviour, so these rest on the table below.

## Green (measured)

```
composer check:   255 unit tests (582 assertions), 109 integration tests (834 assertions, 457.34 s),
                  20 release tests (630 assertions, 29.52 s)
tools/env/release.sh --integration: 109 passed (834 assertions), 180.86 s
```

The integration suite on the zip includes SPEC-002 AC13 as amended, the one that failed in step 28.
It ran before the last two corrections to the tests of `tests/Release` and `tests/Unit`, which
it does not contain. CI runs it at every push.

## Falsified (measured)

Each a temporary change, restored after the run; the release tests of each ran on a fresh zip.

| change | failed |
|---|---|
| `Cli/` stays in the zip | AC3, AC4 |
| `vendor/` stays in the zip | AC3 |
| `tests/` ships too | AC3 |
| the phar's hash is not checked | AC2 |
| built from the working tree, not from the tree-ish | AC1 |
| a file of `trust/` changed in the build | AC4 |
| the verifier's `VERSION` changed in the build | AC4, AC5, AC7 |
| no refusal below PHP 8.3 | AC6 |
| no Strauss: the verifier unprefixed, in the folder the loader looks at | AC4, AC7 |
| AC10 with the verifier left in place | AC10 |
| uninstall leaves the DigiCert setting | AC11 |
| the loader always takes `vendor/` | AC9 |
| `uninstall()` deletes every job row | AC12 |
| a wrong class written out in `Schema::JOB_CLASS` | AC12, AC13 |

Not falsified: AC8's test, which only holds that CI and the script contain the run. AC15 was
seen red and needs no more.

## Amendments

Three, all approved by Maurice, in the spec: `tools/build.sh` takes an optional tree-ish, so that
`composer check` tests what is about to be committed; AC4 holds for PHP source, since the
verifier's `composer.json` still names its namespace; AC11 and AC14 are in `tests/Release`.

## The assistant's own errors

- AC11 failed first on a difference in `api_key`, whose last-used time moves with each call: the
  test compared the helper's own row. It now leaves that table out, wider than the spec's
  wording, and said so before the amendments were approved.
- PHPStan found seven errors in the new tests, over two rounds. None was in the module.
- A command of the assistant's hung on a `grep` that got no file, before it had written anything,
  and was stopped and run again.
- The first falsification script stopped at a signature it had guessed; the last of the
  development-layout changes did not apply at first and had to be done again.

## The first push (measured, CI run 42191099086)

The build on Linux worked, on PHP 8.3: the zip was made, and 18 of the 20 release tests passed.
AC1 and AC2 failed in their own setup: `fatal: unable to read tree`. CI's checkout is shallow, git
then ignores `--shared`, and the clone lacks the commit made for the test. Reproduced with a
`--depth=1` clone. It is the assistant's error, in the test, not in the build; they now use a
repository of their own, and AC1's falsification was run again on that setup. As that job
stopped there, `--integration` had not yet run on Linux at that point.

## Not measured

- The zip on PHP 8.4 and 8.5, Apache only, MariaDB 11.4 only.
- The race at the end of a run, and a reinstall within a run's next 50 media (SPEC-008, Known
  limits).
- How the sidebar and the zip's pages look: no browser.
- The reason a run ends without its row was read, not captured.
