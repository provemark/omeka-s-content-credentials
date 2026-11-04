# Step 08: CI (GitHub Actions)

2026-11-04. The private repository `provemark/omeka-s-content-credentials`
exists (see `NOTES.md`, decision 6), so CI runs on every push and pull
request: `.github/workflows/ci.yml`.

## What was built

Three jobs, all on `ubuntu-24.04` (pinned, as in the sister projects), no
`continue-on-error`:

| job | what |
|---|---|
| static checks and unit tests, PHP 8.3 / 8.4 / 8.5 | `composer validate --strict`, `composer install` from the committed lock, then the steps of `composer check` but the last: spec-check, Pint, PHPStan (with `tools/fetch-omeka.sh`), Deptrac, the unit tests |
| integration tests | PHP 8.3 on the runner, `composer test:integration`: builds both images, installs Omeka S 4.2.1 on PHP 8.3 and 8.2, runs `tests/Integration` (SPEC-001); on failure, the compose logs |
| parses on PHP 8.1 / 8.2 | `php -l` on `Module.php`, `config/module.config.php` and `src/Requirements.php`, the files Omeka S loads before the module can refuse (ADR-0001) |

## Measured

The first run, 41792666292, on commit `949afc4` (`gh run watch`, then
`gh run view --log`): all six jobs succeeded.

| job | time | evidence in the log |
|---|---|---|
| PHP 8.3 | 23 s | PHP 8.3.36; spec-check "OK: 2 spec(s), 3 test file(s)"; PHPStan "No errors"; 29 tests passed |
| PHP 8.4 | 26 s | PHP 8.4.27; the same |
| PHP 8.5 | 20 s | the same |
| integration | 139 s | runner PHP 8.3.36; "Omeka S installed." twice; 5 tests passed |
| parses on 8.1 / 8.2 | 7 s / 10 s | "No syntax errors detected" three times each; 8.2 is PHP 8.2.35 |

Two things reasoned until now are measured by this run:

- The integration support code (`tests/Integration/Support/`) works on PHP
  8.3, not only on the host's 8.5 (step 07 had it reasoned).
- The images pinned by digest build and run on the runner, and the
  integration tests give the same results there as on the host (arm64).

## Reasoned, not measured

- That the runner is x64: GitHub documents `ubuntu-24.04` as an x64
  image; the log does not print the architecture.
- That a failing step turns the run red: each step is a command whose exit
  code GitHub Actions honours, and nothing sets `continue-on-error`. No
  deliberately red commit was pushed to show it.
