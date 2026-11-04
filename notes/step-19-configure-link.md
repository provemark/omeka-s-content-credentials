# Step 19: the configure page had no link (SPEC-004 amendment 1)

2026-11-04. Asked how to reach the trust settings from the admin home, I
found that the modules page offered no Configure link for this module.
The page itself worked, but it could only be opened by its URL.

## Cause (read and measured, Omeka S 4.2.1)

- `view/omeka/admin/module/browse.phtml` shows the Configure link only
  when the module is active, `$module->isConfigurable()` is true, and
  the user may `configure`.
- `Omeka\Module\Module::isConfigurable()` returns
  `(bool) $this->getIni('configurable')`. Overriding `getConfigForm()` is
  not enough.
- `config/module.ini` had no `configurable` key.

Step 15's probe and SPEC-004's tests opened
`/admin/module/configure?id=…` directly, so nothing looked at the modules
page. SPEC-004 had no criterion for reaching its page.

## Fix

SPEC-004 amendment 1 (approved by Maurice, 2026-11-04) adds AC11.
`config/module.ini` gains `configurable = true`, which `parse_ini_file`
in the container reads as `"1"`.

## Tests

- The first version of the AC11 test looked for `<a href="…configure…"`,
  and failed. It went on failing after the fix: Omeka writes the link as
  `<a class="o-icon-configure button" href="…">`, with `class` first. My
  test was too strict, so that red run does not count.
- The corrected test looks for the `href` attribute only. Without the
  `configurable` line it is red ("To contain:
  href="/admin/module/configure?id=ContentCredentials"", 1 failed, 2
  assertions); with it, green (5 assertions).
- A site administrator sees the module on `/admin/module` (200) and no
  Configure link (ACL `configure` denied, step 15).

## A second effect: install redirects to the configure page (SPEC-001 amendment 2)

The commit with the fix (`9e72de5`) was pushed with a red `composer
check`. I had chained the check and the commit with `;` instead of `&&`.
The failing test was SPEC-001 AC1: "Failed asserting that
'/admin/module/configure?id=ContentCredentials' ends with
"/admin/module"."

- Read: `ModuleController::installAction()` adds "The module was
  successfully installed" and then redirects a configurable module to
  `configure?id=…`, any other to the modules page.
- Fix: SPEC-001 amendment 2 (approved by Maurice, 2026-11-04). AC1
  expects the redirect to the configure page; the message, the module row
  and the snapshot are unchanged.
- The amended test was seen red without `configurable = true` ("Failed
  asserting that '/admin/module' ends with
  "/admin/module/configure?id=ContentCredentials"", 1 failed, 3
  assertions), and green with it (7 assertions).
