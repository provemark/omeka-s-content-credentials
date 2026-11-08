# Step 57: the module against Omeka S's own requirements

2026-11-08. Maurice: "Ik wil helemaal zeker weten of het veilig is en voldoet aan alle eisen van
omeka s." This step is the first half, the requirements; the security review is step 58. Nothing in
the module was changed here.

## Sources (read 2026-11-08)

The Omeka S developer documentation: `modules/` (Introduction to Modules), `register_an_addon/`,
`modules/internationalization/`, `modules/data_model/`, `miscellaneous/acl/` and
`miscellaneous/forms/`. The ACL and Forms pages give no rules for modules (no MUST or SHOULD), and no
page covers coding standards or security. Omeka S's latest release is 4.2.1 (2026-06-18,
`gh api repos/omeka/omeka-s/releases`).

## The checklist

"Met" means checked against the code or measured, with where; "reasoned" says so.

| requirement (source) | status | evidence |
|---|---|---|
| `Module.php` and `config/module.ini` (modules: MUST) | met | both in the zip (SPEC-008 AC3, `tests/Release/BuildTest.php`) |
| `module.ini` starts with `[info]`, has `name` and `version` (modules; register) | met | `config/module.ini` lines 1 to 3 |
| optional `author`, `description`, `configurable`, `module_link`, `author_link`, `omeka_version_constraint` | met, all six | `config/module.ini`; the links shown on Omeka's modules page (step 55, SPEC-008 AC19) |
| module directory CamelCase, unique (modules) | met | `ContentCredentials`; not in omeka.org's module list or on GitHub (measured 2026-11-03, `NOTES.md` decision 1) |
| namespace = directory (modules, by example) | met | `namespace ContentCredentials;` (`Module.php:5`), measured necessary in step 05 |
| templates under `view/<module-name>/`, lowercase with hyphens (modules) | met | `view/content-credentials/` |
| configuration from `getConfig()`, in `config/module.config.php` (modules) | met | `Module.php:66` |
| `configurable = true` with `getConfigForm()` and `handleConfigForm()` (modules) | met | `Module.php:118`, `:124`; the Configure link measured (SPEC-004 AC11) |
| listeners in `attachListeners()` (modules) | met | `Module.php:168` |
| available as a `.zip` (modules: SHOULD) | met | the GitHub releases v0.1.0 and v0.1.1 |
| zip: one top-level folder named as registered; a release that is not a draft, with the zip attached (register) | met | `ContentCredentials/` only (`unzip -l`); both releases not drafts (step 56) |
| README ready for publication, name in capitals (register) | met | `README.md`; every link works outside GitHub (SPEC-008 AC18, step 55) |
| install SQL run one statement at a time (data model: "should") | met | `Module.php:96` runs `Schema::create()` statement by statement |
| version raised for an upgrade; semantic versioning (data model) | met | 0.1.0 to 0.1.1; the upgrade measured (step 56) |
| schema changes in `upgrade()`, not by reinstalling (data model) | met, nothing to upgrade yet | no table changed between 0.1.0 and 0.1.1; the first change will need an `upgrade()` (reasoned) |
| uninstall leaves nothing (not in the docs; SPEC-008) | met | measured in a clean installation (SPEC-008 AC11, step 29) |
| a `language/` folder, `translator` in `module.config.php`, `template.pot`, `@translate` on each string (internationalization) | **not met** | no `language/`, no `translator` key; 30 `translate()` calls in `src/`, but one `@translate` marker, so the strings cannot be extracted |
| PHP and Omeka versions (modules: `omeka_version_constraint`) | met | `^4.2.1`; PHP 8.3 or later enforced at install; refusal on PHP 8.2 measured on the zip (SPEC-008 AC6) |

## What is not met

Only internationalization. Omeka's own text in the admin interface is translated; the module's own
text (the verdicts, the configure page, the evidence page) is English in every language. It works,
but a Dutch institution sees English labels. This was already a later wish (a Dutch translation, step
49's advice). To make it right: the `translator` configuration, an `@translate` marker on every
string, a `language/template.pot`, and then `nl.po`/`nl.mo`. That is a spec of its own.

## Limits

- ACL, CSRF and escaping have no documented requirement; they are in the security review (step 58).
- The documentation was read, not exhaustively: no page states coding standards, so none were
  checked.
