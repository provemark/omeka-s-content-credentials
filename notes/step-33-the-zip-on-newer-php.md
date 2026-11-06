# Step 33 (M7.8): the zip on PHP 8.4 and 8.5

2026-11-06. The README says "PHP 8.3 or later"; step 31 found the zip had only run on 8.3. Maurice
decided to add 8.4 and 8.5 to the job that tests it. SPEC-008 amendment 4 adds AC16.

## What was built

- `tools/env/release.sh --php 8.3|8.4|8.5`, with each base image pinned by digest. Any other value
  stops the run with exit 2. The default stays 8.3, so `composer check` does not grow; the refusal
  of AC6 is on PHP 8.2 in each run.
- `tools/env/compose.release.yaml` takes the image from the script and refuses to start without it.
- CI's job `release` is a matrix of the three.

Images (`docker pull`, 2026-11-06): `php:8.4-apache@sha256:884f64b4…`, PHP 8.4.27;
`php:8.5-apache@sha256:08af36df…`, PHP 8.5.12.

## Tests seen red (measured)

`CC_RELEASE_PHP=8.4 sh tools/env/release.sh`, before the script knew `--php`:

```
FAILED  Tests\Release\BuildTest > it AC16: CI runs the zip on every PHP
FAILED  Tests\Release\ZipInOmekaTest > it AC16: it runs on the PHP that was asked for
Tests:    2 failed, 20 passed (632 assertions)
```

The second failure shows that the test tells the versions apart: the container was on 8.3.

## Green (measured, on macOS, Omeka S 4.2.1, MariaDB 11.4)

| PHP | `tests/Release` | `tests/Integration` on the zip |
|---|---|---|
| 8.4.27 | 22 passed (634 assertions), 31.46 s | 109 passed (834 assertions), 200.99 s |
| 8.5.12 | 22 passed (634 assertions), 30.20 s | 109 passed (834 assertions), 199.28 s |

`X-Powered-By: PHP/8.5.12` on the login page: the web server runs that version too.

## Falsifying the promise: what the logs say (measured, PHP 8.5 only)

Tests do not see a deprecation, so the logs were read. It took three attempts; the first two
measured nothing about deprecations:

1. **Container log, 0 deprecations:** void. Omeka's `index.php` sets
   `error_reporting(E_ALL & ~E_DEPRECATED)`, and a control script was answered by Omeka's 404, as
   `.htaccess` sends requests to `index.php`.
2. **`APPLICATION_ENV=development`, 0 deprecations:** void as well. That setting governs
   `display_errors`. The switch is `OMEKA_REPORT_DEPRECATED`.
3. **The switch on (in the container's `index.php`), PHP errors to a file,** with a control: a
   `trigger_error(…, E_USER_DEPRECATED)` in a real request reached the log, once.

The third, over all of `tests/Integration` on the zip (109 passed):

| logged | count | from |
|---|---|---|
| the control | 1, and 2 warnings it caused itself | `index.php` |
| `Using null as an array offset` | 6 | Omeka S: `application/src/Form/Element/AbstractGroupByOwnerSelect.php`, lines 83 and 84 |
| anything from `modules/ContentCredentials`, verifier included | **0** | |

So on 8.5 Omeka S itself has a deprecation, off by default, and that is not this module's to fix. PHP
8.4 was not read this way. This covers what the suite reaches, not every page.

## An observation on AC14 (measured)

With errors logged, `tests/Release` shows how a run ends when its row is deleted: `PHP Fatal error:
Uncaught Doctrine\ORM\EntityNotFoundException: Entity of type 'Omeka\Entity\Job' for IDs id(2) was
not found`, from `merge()` at `Omeka\Job\Dispatcher::send` (`Dispatcher.php:119`). Step 30's "read,
not captured" is thus half answered. The process ends, as AC14 requires, and the message goes to the
PHP error log of the job's process if one is set: an administrator can find it there. The module
cannot catch it, as its code has gone by then. It writes nothing back in that path; whether that also
closes SPEC-008's race at the end of a run is reasoned, so the limit stays as written.

## Not measured

- 8.4 and 8.5 on Linux, until CI's first run of the matrix.
- A real installation's PHP configuration, other than these images' defaults.
