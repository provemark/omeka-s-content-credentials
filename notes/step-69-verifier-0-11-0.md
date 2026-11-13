# Step 69 (M8.11): c2pa-verifier 0.11.0

2026-11-13, at Maurice's go ("werk Omeka bij naar verifier 0.11.0"). `docs/maintenance.md` step 2
says that a 0.11 is its own step: read its changelog for changes to verdicts, status codes and the
evidence format, and measure before deciding.

## What changed

- `composer.json` moves from `^0.10` to `^0.11`, and `composer.lock` from v0.10.1 to v0.11.0.
- No module code changes.
- `docs/adr/ADR-0002-dependencies.md` dates the new constraint.
- `docs/maintenance.md` now says `^0.11`, "a new 0.11.x" and "a 0.12 or later".
- The CHANGELOG has an entry under Unreleased.
- `docs/milestones.md` has a new row, M8.11.

## What 0.11.0 brings

From its CHANGELOG, read:
- It verifies the general box hash (`c2pa.hash.boxes`) in a JPEG. Microsoft Bing Image Creator
  writes every file that way, and 0.10 refused it by name with `general.error`.
- It reads a JUMBF URI written without the leading slash from the store's root.
- It adds five `StatusCode` cases (`assertion.boxesHash.*`), and nothing was removed or renamed.

## Measured

- **The evidence format is unchanged.** `git diff v0.10.1 v0.11.0` is empty for
  `docs/evidence-format.md`, `src/Verifier/EvidenceDocument.php` and
  `src/Verifier/VerificationReport.php`.
- **The verifier, under this module's own bundled trust settings**
  (`TrustChoice::bundledJson()`, DigiCert off and on). Both versions read this module's 11 C2PA
  fixture images and the verifier's 723 JPEG, PNG, WebP and GIF fixtures, at its `7e325eb`: 1,468
  runs per version, 0 exceptions. Each side loaded its own vendor tree.
  - This module's fixtures: no verdict changes, no report changes (`toJson()`) and no evidence
    document changes (`evidenceToJson()`), with the version string removed before comparing.
  - Across all runs, 33 of 1,468 evidence documents differ, all for the verifier's new probes and
    the Bing file. `Trusted` stays at 6.
  - Verdicts that move, `Invalid` → `Valid`:
    - five of the verifier's own probes (`box-hash/written*`, `slashless/all-slashless.png`), in
      both modes;
    - the Bing file (`writers/microsoft-20260609-bing-fast-heartbeat.jpg`), **with DigiCert on
      only**. Its RFC 3161 time-stamp then validates, the signing certificate is judged at that
      time, and its box hash now matches. With DigiCert off it stays `Invalid`, because its leaf
      expired on 2026-10-01. It is `Valid` and not `Trusted`, because its signer is not on the
      bundled C2PA list.
- **`sh tools/env/reset.sh`, then `composer check`:** exit 0. That covers spec-check, phpstan,
  deptrac, unit 357, integration 152 and release 24.

## Reasoned

- **No module code changes.** The module has no `match` over `StatusCode` (`grep -rn StatusCode
  src` finds nothing), and it stores the verdict and codes the verifier gives.
- **Stored entries keep their verdict and evidence.** Entries checked under 0.10.x keep those
  verdicts. A re-check records 0.11.0, and for a box-hash JPEG it can now give a different
  verdict.
- **Releasing is Maurice's decision.** Under `docs/maintenance.md` this calls for a release of
  the module.
