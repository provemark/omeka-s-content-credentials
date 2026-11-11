# Step 67 (M8.9): c2pa-verifier 0.10.1

2026-11-11, at Maurice's go ("werk de verifier in omeka bij naar 0.10.1"), as `docs/maintenance.md`
step 2 describes for a new 0.10.x.

## What changed

`composer update provemark/c2pa-verifier`: v0.10.0 → v0.10.1 in `composer.lock`. `composer.json`
stays at `^0.10`. No module code changes.

What 0.10.1 brings (its CHANGELOG, read, not re-measured here): an icon that is not a hashed URI is
`hashedURI.missing` and the file `Invalid`, as C2PA 2.4 §10.2.3.2 and `c2pa` 0.91.11 require, and
one new `StatusCode` case, `HashedUriMissing`. Over the verifier's own corpus at validation time
2026-11-11, five test files move `Valid` → `Invalid` (its step 528); the WordPress plugin measured
the same five through its own code on 2026-11-11.

## Measured

- `sh tools/env/reset.sh`, then `composer check`, exit 0: spec-check (10 specs), phpstan, deptrac
  (0 violations), unit 357, integration 152, release 24.

## Reasoned

- The module has no `match` over `StatusCode` (`grep -rn StatusCode src` finds nothing); it stores
  the verdict and codes the verifier gives, so the new case needs no code.
- Stored entries keep the verdict and evidence they were checked with: evidence documents written
  under 0.10.0 name verifier `0.10.0` and stay byte for byte as stored (SPEC-006). New uploads and
  re-checks record `0.10.1`.
- Per `docs/maintenance.md` this makes a patch release of the module, 0.1.4, Maurice's decision.
