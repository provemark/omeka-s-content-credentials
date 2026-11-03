# Step 03: the integration environment (M0.3)

2026-11-03. The environment the integration tests will run against: the
pinned Omeka S, MariaDB, and this repository mounted as the module
`ContentCredentials`. Nothing of the module exists yet; this step only
makes the place where it will be measured.

## What was built

| file | what |
|---|---|
| `docker/omeka/Dockerfile` | Omeka S 4.2.1 from its release zip, checked against its SHA-256, on `php:8.3-apache` pinned by digest; adds `pdo_mysql`, ImageMagick and `mod_rewrite` |
| `docker/omeka/entrypoint.sh` | writes `config/database.ini` from the environment |
| `docker/omeka/install.php` | installs Omeka S without the web form, with the tasks and variables `InstallController` registers |
| `compose.yaml` | project `content-credentials-test`: `db` (MariaDB 11.4 pinned by digest) and `omeka`, the repository mounted read-only at `modules/ContentCredentials`, port 8088 on 127.0.0.1 only |
| `tools/env/up.sh` | builds, starts, installs once, checks that `/login` answers 200 |
| `tools/env/down.sh` | stops; the database and files are kept |
| `tools/env/reset.sh` | removes the project with its volumes, then `up.sh` |

The test passwords in `compose.yaml` are not secrets: the port listens on
127.0.0.1 only and the environment holds nothing but test data. The
compose project name keeps its containers and volumes apart from any
other Omeka S installation; the scripts touch nothing else.

## Measured

Host: macOS (arm64), Docker 29.6.2, Docker Compose without the buildx
plugin (it warns and builds with the classic builder).

1. **The pin.** `gh api repos/omeka/omeka-s/releases`: v4.2.1, published
   2026-06-18, is the latest 4.2.x and the latest release. Its asset
   `omeka-s-4.2.1.zip` (18,955,412 bytes) has the digest
   `sha256:cc27d1c7…de348e82`; `shasum -a 256` on the download gives the
   same. The tag `v4.2.1` is commit `dbdfcd33…` (`git ls-remote`).
2. **What Omeka S 4.2.1 requires** (read in the release's source, so
   measured as text): `composer.json` requires PHP `>=8.1` and `ext-fileinfo`,
   `ext-mbstring`, `ext-pdo_mysql`, `ext-xml`;
   `Omeka\Stdlib\Environment` checks PHP 8.1.0, MySQL 5.7.9 or MariaDB
   10.2.6, and the extensions `fileinfo`, `mbstring`, `PDO`, `pdo_mysql`,
   `xml` (and `json`). The default thumbnailer is
   `Omeka\File\Thumbnailer\ImageMagick` and the default job dispatch
   strategy `PhpCli` (`config/local.config.php`,
   `application/config/module.config.php`).
3. **The base image.** `php:8.3-apache` (digest `a2d500ab…`, PHP 8.3.36,
   Debian 13) has `fileinfo`, `mbstring`, `PDO`, `xml`, `json`, `openssl`
   and `sodium`, and lacks `pdo_mysql` and ImageMagick. The verifier
   requires `ext-openssl` and `ext-mbstring`, and suggests `ext-sodium`,
   needed on PHP 8.3 for Ed25519 (its `composer.json`): all present.
4. **A fresh environment** (`tools/env/reset.sh`): exit 0 in 7.0 s with
   the image cached. In the containers: PHP 8.3.36 with `pdo_mysql`,
   `openssl`, `sodium`, `mbstring`; ImageMagick 7.1.1-43; MariaDB 11.4.13;
   the `setting` table holds version `"4.2.1"`; one user,
   `admin@example.com`, `global_admin`, active; the `module` table empty.
5. **Logging in.** `GET /login`, then `POST /login` with the form's
   `loginform_csrf`: HTTP 302 to `/admin`; with that session
   `GET /admin/module` answers 200.
6. **The mount.** `/var/www/html/modules/ContentCredentials` is this
   repository. The modules page lists `ContentCredentials` with "Error:
   invalid config/module.ini file", as expected before the skeleton
   (M0.5).
7. **Idempotent.** `tools/env/up.sh` a second time: exit 0, `install.php`
   says "Omeka S is already installed." (exit 2), still one user.
8. **A failed install looks installed.** With the admin address
   `admin@example.test` the installer stopped: Laminas's e-mail validator
   rejects `example.test` ("cannot match TLD against known list"). A next
   run of `install.php` then said "Omeka S is already installed", with no
   user in the database. The installer had created the schema before the
   user task failed, and `Omeka\Mvc\Status` takes the existence of the
   `module` table for installed (its docblock). Changed to
   `admin@example.com` (RFC 2606); after `reset.sh` the install succeeds.
   Consequence: after a failed install, `reset.sh`, not `up.sh`.
9. **Deprecation notices.** In that half-installed state, `install.php`
   printed two PHP deprecations from `composer/semver`
   (`Constraint.php` lines 211 and 226: null passed as a version). They
   did not appear on the fresh install, nor on the second `up.sh`. Not
   investigated further; noted in case they return with the module in
   place.

## Reasoned, not measured

- That PHP 8.3 is the right version for the integration environment: it is
  the floor the verifier sets, so the most likely to show a problem the
  newer versions hide. Only 8.3 runs here; CI (PHP 8.3, 8.4, 8.5) is for
  later and may need the PHP version as a build argument.
- That the image digests, pinned as multi-architecture index digests, give
  the same images on an amd64 CI runner. Measured only on arm64.
- That the Debian packages (ImageMagick 7.1.1-43) stay the same: they are
  not pinned, and a rebuild may take a newer patch release.
