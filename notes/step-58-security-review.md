# Step 58: an independent security review

2026-11-08. The second half of Maurice's question (step 57). A review agent, started apart from the
work, read `Module.php`, `config/`, `src/`, `view/`, `tools/build.sh`, `composer.json` and
`composer.lock`, and the Omeka S 4.2.1 source the module relies on. It was told not to read
`notes/`, `specs/`, `NOTES.md`, `AI-LOG.md` or `docs/`, so as not to be anchored on earlier
conclusions. It changed nothing, ran nothing at runtime and did not build the zip. Nothing in the
module was changed in this step.

## Result

No high or medium findings. Three low findings and seven notes. The assistant checked L1 in the code
itself; L2 and L3 are as the reviewer wrote them, not measured.

| # | finding | assistant's check | proposal |
|---|---|---|---|
| L1 | The evidence controller is allowed for every role: `$acl->allow(null, …)` (`Module.php:82`). Anonymous visitors are sent to the login page (the routes are under `admin`), and the controller reads the media item through the API, so only media the user may see. But a role another module adds, such as the Guest module's `guest`, which Omeka keeps out of its admin pages, can open the evidence pages in the admin layout | confirmed by reading: `allow(null, …)` covers every role; Omeka's own six roles are added in `AclFactory.php:57-62`. Not measured: it needs a module that adds a role | allow Omeka's six roles only; a spec amendment (SPEC-006) and a test |
| L2 | The check runs inside the upload request. Every `Throwable` is caught, but a PHP fatal (time or memory limit) is not: the upload is kept, the verdict stays "did not finish" (fail closed), but the request answers 500 and a batch import (CSV Import) would stop at that file | not checked; the reviewer found no file that causes it, given the verifier's limits | measure the time and memory of the largest fixtures against PHP's defaults; decide after |
| L3 | If storing the evidence fails (a document above MariaDB's `max_allowed_packet`, 16 MiB by default; or a signer name that is not UTF-8), the row stays "did not finish" and no evidence is kept; "Check media without a result" then skips it | not checked | record as a known limit, or measure with a constructed file |

Notes (no scenario that harms anyone): evidence grows with every "Check all media" run, with no
pagination of a media item's list (I1); custom trust settings are parsed again for every check (I2);
two runs can be started by two simultaneous requests of a global administrator (I3); one file that
always causes a fatal stops every "Check all" run there (I4); the verifier's GitHub archive has no
checksum in `composer.lock`, as usual for GitHub (I5); with a non-local file store every check
answers "Could not be checked" (I6); the first schema change will need `upgrade()` (I7, also step 57).

## Found sound, with where (the reviewer's reading)

- **Access:** anonymous visitors are redirected to the login (`__ADMIN__`, Omeka's
  `MvcListeners.php:253-277`); every evidence action reads the media item through the API first, so
  Omeka's visibility rules apply, and a missing row and an invisible media item both answer 404. The
  configure page is for global administrators only (Omeka's `AclFactory.php:937-942`).
- **CSRF:** the only state-changing request is the configure form, whose token Omeka checks before
  `handleConfigForm()` (`ModuleController.php:278-292`); the evidence routes only read.
- **Escaping:** text from files is cleaned (`Text::visible`) and escaped once; the remote manifest
  URL is shown as text, never as a link; the evidence document is escaped in a `<pre>`; the download's
  file name holds numbers only, with `nosniff`.
- **SQL:** every value bound; the browse filters accept only listed values.
- **Files, network, exec:** reads the original through Omeka's local store and the three bundled
  lists (hash-checked); writes no file; no network, `exec` or temporary file in `src/` or the
  verifier's shipped code.
- **Install and uninstall:** only the module's two tables, its job rows and its two settings.
- **The zip:** an allowlist in `git archive`; no tests, notes, keys or Composer autoloader ship.

## Dependencies (measured)

`composer audit` (2026-11-08): three advisories, in `symfony/config`, `symfony/filesystem` and
`symfony/yaml`, all development tools (PHPStan, Deptrac, Pest). `composer audit --no-dev`: none. The
zip holds none of them.

## Limits of this review

One reviewer, reading only; no penetration test; the verifier's own parsers were reviewed only for
their limits and for I/O. The verifier has its own security policy
(`provemark/c2pa-verifier/SECURITY.md`).
