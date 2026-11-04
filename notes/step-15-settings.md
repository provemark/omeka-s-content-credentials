# Step 15 (M3.1): settings in Omeka S

2026-11-04. M3 adds trust settings (`NOTES.md` §4): a DigiCert option, a
custom trust-settings JSON pasted by an administrator, an age warning,
and the trust source recorded with each verdict. Before a spec rests on
it: how Omeka S 4.2.1 lets a module have a settings form, where a value
of that size can be stored, whether the after-flush listener can read
it, where a warning can show, and how a module changes its own table
later.

## Read (Omeka S 4.2.1 source)

- `Omeka\Module\AbstractModule`: `getConfigForm(PhpRenderer)` returns
  the form's markup, `handleConfigForm(AbstractController)` handles the
  POST (returning `false` means failure), `upgrade($oldVersion,
  $newVersion, $services)`. All three are empty by default.
- `Omeka\Controller\Admin\ModuleController::configureAction()`: builds a
  CSRF form named `module_{id}_configure`. Only when that token is valid
  does it call `handleConfigForm()`. On `true` it adds "The module was
  successfully configured" and redirects to the module browse. On
  `false` it adds "There was a problem during configuration" and renders
  the page again, calling `getConfigForm()` afresh.
  `configure.phtml` wraps the module's markup in one `<form method="post">`
  with the CSRF field.
- `Omeka\Service\AclFactory`: `site_admin` is denied `configure`,
  `upgrade`, `install` and the like on the module controller; the lower
  roles are not allowed it at all. Only `global_admin` configures.
- `Omeka\Settings\AbstractSettings`: the table `setting`, with
  `id varchar(190)` and `value longtext` holding JSON. A `set()` with
  `null` deletes the row. On first use in a request, `get()` loads and
  decodes **every** row (`SELECT *`), whatever setting is asked for.
- `Omeka\Module\Manager`: a module whose `module.ini` version is newer
  than the installed one has state `needs_upgrade`.
  `Omeka\Mvc\Application` loads only modules in state `active`. Upgrading
  is an action an administrator takes on the module page.
- `layout/layout-admin.phtml` triggers `view.layout` before it prints
  `$this->messages()`.

## Measured

A measurement-only module, `tools/probes/SettingsProbe/` (never shipped),
was copied into the PHP 8.3 container (Omeka S 4.2.1, PHP 8.3.36) and
installed. It renders a textarea in `getConfigForm()`, stores the POST
value with `Omeka\Settings::set()` in `handleConfigForm()` (or returns
`false` for the value `FAIL`), and adds a messenger warning on
`view.layout` for admin requests. It also reads the setting in a
Doctrine `postFlush` after an upload, as `AfterFlushListener` would, and
logs `upgrade()`. The value was a real trust-settings JSON built from
Tracefern's `trust/` files with the verifier's recipe, plus
`</textarea><script>alert(1)</script>`, `é€😀`, CR LF and a tab:
74 003 bytes, SHA-256 `daed447e…aebd8`. Driven from scratch scripts with
the test support class `OmekaInstallation`.

| what | result |
|---|---|
| `GET /admin/module/configure?id=SettingsProbe` as global admin | 200; the probe's form; CSRF field `module_SettingsProbe_configure_csrf` |
| POST without the CSRF field | 200, "CSRF: Value is required and can't be empty"; `handleConfigForm()` **not called** (log) |
| POST `FAIL` with CSRF (`handleConfigForm()` returns `false`) | 200; the module's own error, then "There was a problem during configuration"; the form rendered again with an **empty** textarea: the posted text is gone unless the module puts it back itself |
| POST the 74 003-byte value with CSRF | 302 to `/admin/module`; `setting.value` 75 962 bytes (the JSON string) |
| the form after that | the textarea decodes to the same SHA-256; `</textarea><script>` appears only escaped |
| the setting read in `postFlush` after an admin-form upload | a string with the same SHA-256 |
| decoding a 74 KB JSON string (what every request pays once the value is stored, since all settings are loaded) | 0.196 ms per `json_decode`, averaged over 1 000 in the container |
| a messenger warning added on `view.layout` | shown on `/admin`, `/admin/media` and the configure page; not on `/login` (not an admin request) |
| `site_admin` and `editor` (users made for the test, logged in) opening the configure page | `Omeka\Mvc\Exception\PermissionDeniedException` for the configure action (container log); Omeka 4.2.1 answers it with HTTP 500 and its generic error page |
| `module.ini` raised to 0.2.0 in the container | the module page offers "Upgrade". Until then the module is not loaded: no `view.layout` warning, no `postFlush` line on an upload, configure gives 404. Other modules carry on (ContentCredentials stored `Valid` for that upload) |
| upgrading from the module page | 302, "The module was successfully upgraded"; `upgrade()` called once with `old=0.1.0 new=0.2.0`; the module active and its listeners back |
| uninstalling the probe (it has no `uninstall()` of its own) | the `setting` row **stays** (75 962 bytes); Omeka does not remove a module's settings |

My first probe called `$view->messenger()` and `$renderer->messenger()`.
`messenger` is a controller plugin, not a view helper, and every admin
page answered 500 (`ServiceNotFoundException: A plugin by the name
"messenger" was not found`, container log). The probe now instantiates
`Omeka\Mvc\Controller\Plugin\Messenger`, as modules do. The table above
is from the corrected probe.

Afterwards the probe was uninstalled and removed from the container, and
its setting row and the two users deleted.

## What follows for M3 (reasoned)

- The settings page is the module's configure page, global admins only.
  `handleConfigForm()` can refuse invalid custom settings by returning
  `false`. `getConfigForm()` must then put the posted text back from the
  request itself, or the administrator loses what they pasted.
- A custom trust-settings JSON fits in `Omeka\Settings`, read back exactly,
  also after an upload's flush. Storing it there makes every request,
  admin or public, decode it once (about 0.2 ms for 74 KB). The
  alternative is a row in a module table, read only when verifying.
  Either is cheap; the spec chooses.
- The age warning can be a messenger warning on `view.layout` (every
  admin page) or only on the configure page. Every admin page is louder
  than Tracefern, which warned on its settings page.
- `uninstall()` must delete the module's settings itself; SPEC-001's
  "uninstall removes everything" then needs a criterion for them.
- A column for the trust source is a change to SPEC-002's table. After a
  release, that means an `upgrade()` with an `ALTER TABLE`, and until an
  administrator upgrades, the module checks nothing. Before the first
  release (version 0.1.0, unreleased) the `CREATE TABLE` can change
  instead.

## Not measured

- The DigiCert option as a checkbox (a POST without the field); the same
  form path, not expected to differ.
- Laminas form elements (`Laminas\Form`) instead of plain markup in
  `getConfigForm()`.
- PHP 8.2: the module refuses to install there (SPEC-001), so it has no
  settings page.
