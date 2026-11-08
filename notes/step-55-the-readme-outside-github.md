# Step 55 (M7.25): a README that reads the same on omeka.org

2026-11-08. Before the registration on omeka.org, Maurice asked whether the module needs an
explanation ("Moet er niet ook een uitleg?"). He approved SPEC-008 amendment 6 and `author_link` to
`https://github.com/provemark` ("ok, akkoord met beide, author_link naar github.com/provemark").

## What was read

`omeka.org/s/docs/developer/register_an_addon/` (fetched 2026-11-08): a GitHub repository is
registered once at `omeka.org/register/`; each later release that is not a draft and has a zip with
one top-level folder of the registered name, and a `config/module.ini` with `[info]` and `version`,
is published automatically. "Make sure the README is ready for publication": the README is published
with each version. The page says nothing about screenshots or other documentation, and that omeka.org
does not check whether an addon works. Release 0.1.0's zip meets the listed checks (one folder
`ContentCredentials`; `[info]` with `version = "0.1.0"`).

In Omeka S 4.2.1's `application/view/omeka/admin/module/browse.phtml`, `module_link` links the
module's name and `author_link` the author's name; `support_link` appears nowhere in
`application/`. The same page carries a `version-notification` with `data-addon-id`, which compares
installed modules with omeka.org's registry (read; not measured, as nothing is registered yet).

## What was built

- **SPEC-008 amendment 6**, AC18 and AC19.
- **Tests first.** `tests/Unit/ReadmeTest.php` (AC18) lists every Markdown link, reference and HTML
  `href`/`src` and every image's alt text. On the README before this step it was red, naming
  `trust/README.md` (twice) and `LICENSE`. Its second test, on a made-up sample, was green at once:
  it tests the test's own helper, which did not exist before it. `tests/Integration/ModuleLinksTest.php`
  (AC19) was red before `module.ini` had the links: the name and the author were plain text.
- **The README.** An introduction for collection managers at the top; the three relative links made
  absolute; "Version 0.1.0 is the module's first version" became "an early version (0.1.x)"; two
  screenshots under "In the admin interface", with alt text that says what they show, and a caption.
- **The screenshots** in `docs/images/` (not in the zip): `media-browse.png` (2340 × 990) and
  `media-verdict.png` (670 × 602), 2× pixel density. The README links them by their
  `raw.githubusercontent.com` URL on `main`.
- **`config/module.ini`**: `author_link` and `module_link`.

## How the screenshots were made

In the test environment after `tools/env/reset.sh`: the module installed and four items added
through the admin form, with the fixtures `google-20250919-pixel10-npld-picnic-table.jpg` (public
domain, Wikimedia Commons; `Trusted`), `amazon-20240925-titan-g1.png` (`Valid`, "AI-generated
(signed)"), `fixture-signed.webp` (`Valid`) and `fixture-unsigned.jpg` (none). Nothing from a real
collection. Headless Chromium (Playwright 1.56.1, installed outside the repository, not a dependency)
logged in with the test account and took the media browse's content area at 1440 px wide and the
`.content-credentials` block on media 2's page. Taking them showed the fault of step 54; these are
taken after its fix.

## Measured on the change

- AC18 and AC19 green; AC19 red again with the links taken out of `module.ini` (`git stash`).
- Omeka writes the link as `href="https&#x3A;&#x2F;&#x2F;github.com&#x2F;…"` (`escapeHtmlAttr`); the
  test decodes the snippet before matching.
- `composer check`: green, 349 unit, 145 integration and 23 release tests.

## Limits

- How omeka.org renders the README (images, tables) is not known until it is registered.
- The images are linked on `main`: a later change to them changes the page of every version.
- The version stays 0.1.0 here; 0.1.1 is a release step of its own, after Maurice's go.
