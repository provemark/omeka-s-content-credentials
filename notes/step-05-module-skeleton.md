# Step 05: the empty module skeleton (M0.5)

2026-11-03. The smallest module Omeka S accepts, and what it takes to
install and uninstall it in the pinned Omeka S 4.2.1. The module does
nothing yet.

## What was built

| file | what |
|---|---|
| `config/module.ini` | `[info]`: name "Content Credentials (C2PA)", version `0.1.0`, author, description, `omeka_version_constraint = "^4.2.1"` |
| `Module.php` | `ContentCredentials\Module extends Omeka\Module\AbstractModule`; only `getConfig()` |
| `config/module.config.php` | returns `[]` |
| `src/.gitkeep` | `src/` exists, empty |

`omeka_version_constraint` is `^4.2.1` because 4.2.1 is the only version
this was measured on.

## Read in the Omeka S 4.2.1 source

- `Omeka\Service\ModuleManagerFactory`: a module is a directory under
  `modules/` with `config/module.ini` (an `[info]` section with at least
  `name` and `version`, `Manager::iniIsValid()`) and `Module.php`; the
  `omeka_version_constraint` is checked with `Composer\Semver`. For an
  active module the class looked for is `<directory>\Module`, and it must
  extend `Omeka\Module\AbstractModule`.
- `Omeka\Module\Manager::getModuleObject()`: `new ($module->getId() . "\Module")`,
  the id being the directory name.
- `AbstractModule::getAutoloaderConfig()`: Omeka S autoloads
  `modules/<namespace>/src` for the module's namespace (Laminas
  `StandardAutoloader`). It does not load the module's `vendor/`.
- The `[info]` keys Omeka S reads anywhere in `application/`: `name`,
  `version`, `description`, `author`, `author_link`, `module_link`,
  `configurable`, `has_translations`, `helpers`,
  `omeka_version_constraint`. **No key for a PHP version.**
- `Manager::install()` calls the module's `install()` first and writes the
  `module` row only afterwards; `ModuleController::installAction()` turns a
  `ModuleCannotInstallException` into an error message on the modules page.
  That is the way to refuse installation on PHP below 8.3 (ADR-0001); it
  is behaviour, so it gets a spec once the spec template exists (M0.6).
- Uninstall is offered in a confirmation sidebar
  (`uninstall-confirm`, field `confirmform_csrf`), not on the browse page.

## Measured

In the environment of step 03 (Omeka S 4.2.1, PHP 8.3.36, MariaDB
11.4.13), driving the admin interface with `curl` as the test admin. A
database snapshot is `mariadb-dump --no-data` (auto-increment values
removed), the row count of every table, every `setting` row and the
`module` table.

1. **Syntax.** `php -l` on `Module.php`: no errors on the host's PHP 8.5.12,
   in `php:8.2-cli` and in `php:8.1-cli`. (Omeka S 4.2 loads it on 8.1
   before the module can say it needs 8.3.)
2. **Recognised.** `/admin/module` lists "Content Credentials (C2PA)
   version 0.1.0 by Maurice van Loon" with an Install button (state not
   installed); before this step it listed "invalid config/module.ini".
3. **Install.** POST to `/admin/module/install?id=ContentCredentials` with
   the form's `csrf`: 302, "successfully installed". Compared with the
   snapshot before: one row in `module` (`ContentCredentials`, active,
   `0.1.0`), and session rows from the measurement's own logins; nothing
   else.
4. **Active, nothing broken.** `/admin`, `/admin/item`, `/admin/media`,
   `/admin/module` and the public `/` answer 200; the container log shows
   no PHP error.
5. **Deactivate and activate.** Each 302; `module.is_active` goes to 0 and
   back to 1.
6. **Uninstall.** Through the confirmation form: 302, "successfully
   uninstalled". The snapshot equals the one before the install, except
   for the session rows.
7. **Namespace must equal the directory.** A probe module in the container,
   directory `CcProbe`, valid `module.ini`, `Module.php` in namespace
   `OtherName`: listed as installable; installing it answers HTTP 500 and
   writes no `module` row. Removed afterwards. That the 500 is "class
   `CcProbe\Module` not found" is read from `getModuleObject()`, not seen:
   the production error page shows no detail.
8. **The module's `vendor/` and Omeka S's.** Comparing the two
   `composer.lock` files: 13 of this repository's development packages are
   also runtime packages of Omeka S 4.2.1, several at another major
   version (`psr/container` 2.0.2 against 1.1.2, `psr/log` 3.0.2 against
   1.1.4, `symfony/console` 7.4 against 6.4, `webmozart/assert` 2.5 against
   1.12). The verifier itself has no dependencies.

## Consequences

- **The module must never `require vendor/autoload.php` in a development
  checkout**: Composer's autoloader is registered in front, so its
  `psr/container` 2 would be found before Omeka S's 1.1.2. M1 loads only
  the verifier (its own source, or the prefixed copy of the release build);
  that is for M1's spec. Not measured: what actually breaks.
- ADR-0001 expected `module.ini` might state the PHP floor; it cannot. The
  refusal will be an install-time check (above), in a spec.

## Reasoned, not measured

- That Omeka S's `src/` autoloading and the composer `psr-4` entry
  (`ContentCredentials\` → `src/`) map the same names: `src/` is empty.
- How the module behaves when installed on PHP 8.1 or 8.2: not run.
