# Step 54 (M7.24): the AI label in the browse column

2026-11-08. Taking screenshots for the README (step 55), the media browse showed the AI label next to
the verdict instead of under it, broken inside the word: "AI-generat / ed (signed)". Maurice approved
amendment 4 to SPEC-007 the same day ("ok, akkoord met beide").

## What was measured

In the test environment (Omeka S 4.2.1, PHP 8.3), with the module installed and four media added
through the admin form (the Pixel photo, the Amazon Titan PNG, `fixture-signed.webp`,
`fixture-unsigned.jpg`), the page opened in headless Chromium (Playwright 1.56.1, run from outside
the repository) at 1440 px wide:

- The module's cell was `<span class="content-credentials-headline">…</span><br><span
  class="content-credentials-ai">…</span>`, as written.
- In the browser, Omeka's admin table (tablesaw) had wrapped it in
  `<span class="tablesaw-cell-content">`, with computed `display: flex` and `flex-wrap: nowrap`. The
  two spans were two flex items side by side; a `<br>` between flex items does nothing.

The module's integration tests read the server's HTML, which has no tablesaw span: that is why AC9's test,
which checked only that the cell contains the label, was green. Maurice's trial (step 48) did not
report it.

## What was built

- **Test first.** AC9's test "the label follows the state on every page" now also requires the cell
  to be one element: `<span class="content-credentials-cell">` holding the headline, a `<br>` and the
  label. Red before the change, four failed (the four rows with a label) and six passed:
  `vendor/bin/pest --testsuite=Integration --filter="AC9: the label follows the state on every page"`.
- **The change.** `VerdictHtml::cell()` wraps the cell in that one span, with or without a label.
  Green after: all 18 AC9 tests.

## Measured on the change

The same page, after a reset of the environment and the same four media, measured in the browser:
the label's top is at or below the headline's bottom, at 1440 px (the label on two lines,
"AI-generated" and "(signed)") and at 1024 px (three lines). The word is no longer broken: the
screenshot of step 55 shows it.

`composer check`: green, 347 unit, 144 integration and 23 release tests.

## Limits

- Measured in Chromium only, not in Firefox or Safari. A single flex item with a `<br>` inside is
  ordinary inline layout, so the outcome is expected to be the same there (reasoned).
- The test checks the markup, not the layout; the layout was measured by hand, as above.
