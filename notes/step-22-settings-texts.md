# Step 22: the settings texts name the runs (SPEC-004 amendment 2)

2026-11-04. Since SPEC-005, a run started from the configure page checks
existing media again, with the trust settings saved when it starts. Two
texts on that page, specified by SPEC-004, still spoke of new uploads
only (noted in `notes/step-21-recheck.md`). Maurice approved amendment 2
with both changes.

| where | was | now |
|---|---|---|
| AC8, the note under the settings | "Changes apply to new uploads; media checked before keep their verdict." | "Changes apply to new uploads and to runs started below; until such a run, media checked before keep their verdict." |
| AC6, the warning for custom settings that cannot be built | "The saved custom trust settings cannot be used; new uploads are checked without a trust list." | "The saved custom trust settings cannot be used; new uploads and runs are checked without a trust list." |

SPEC-004's Scope line about the note changed with them. Both texts are in
`src/Display/SettingsPage.php`.

## Tests

The four tests that check these texts were changed first:
- unit: "AC6: the page warns when the saved custom settings cannot be
  used" and "AC8: the page shows the list, the option, the text and the
  note";
- integration: "AC6: settings that cannot be built: checked without them"
  and "AC8: the configure page".

With the old texts in the code, they were red (`--group=SPEC-004
--filter='AC6|AC8'`: 4 failed, 4 passed; the four that passed do not show
the page). With the new texts, all 8 passed (37 assertions).

The warning of AC6 is reasoned to hold for runs: `RecheckJob` builds the
settings with the same `TrustConfig::built()`, and a failure means a
check without settings (SPEC-004 AC6). AC7 of SPEC-005 runs with such
settings; it does not look at the trust source of the rows.
