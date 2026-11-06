# Step 32 (M7.7): refreshing the bundled lists

2026-11-06. Step 31 found both C2PA lists behind upstream. Maurice decided to refresh them before
a release, by ADR-0004's procedure; SPEC-004 amendment 3 and SPEC-005 amendment 1 record it.

## What changed (measured)

| | was | now |
|---|---|---|
| commit | `47009ee` | `64b3f9c`, which last changed the `.pem` files |
| list date | 2026-10-17 | 2026-11-04 |
| compared | 2026-11-04 | 2026-11-06, identical at `a95f2c4`, the newest commit touching `trust-list/` |
| `C2PA-TRUST-LIST.pem` | 32 certificates, `b3949271…` | 33, `148c1c16…` |
| `C2PA-TSA-TRUST-LIST.pem` | 23 certificates, `7182cb22…` | 24, `eca8347e…` |
| age warning from | 2027-05-06 | 2027-05-08 |

- The one addition to both lists is Samsung C2PA Root CA G1, valid to 2046-10-26, fingerprint
  starting `98918f064d7984ba`. By fingerprint nothing was removed, and `diff` shows no removed or
  changed line in either file.
- That a manufacturer's root is on a TSA list as well as the signer list is how upstream publishes
  it; it was not checked further.
- The DigiCert file is untouched. The two lists are no longer equal to Tracefern's copy.

## Tests seen red (measured)

The tests changed first, the lists not yet, Omeka S 4.2.1 on PHP 8.3:

```
tests/Unit, group SPEC-004:           Tests:    11 failed, 28 passed (72 assertions)
tests/Integration, TrustSettingsTest: Tests:    4 failed, 8 passed (50 assertions)
```

The display tests of `c2pa-2026-10-17` stay as written, on purpose: `Presentation` takes the date
from the stored value, so a verdict stored under the old list still shows its own date.

## Verdicts (measured)

All 10 fixtures keep their verdict, as `tools/probes/ReleaseCheck/verdicts.php` showed in step 31
and the suites below show in Omeka. So ADR-0004's "a changed verdict is a finding" has nothing to
record. No fixture is signed under the new root, so that a Samsung file becomes `Trusted` is
reasoned, not measured.

## What an installation sees (reasoned)

Verdicts already stored keep `c2pa-2026-10-17` until "Check all media" runs, and new uploads get
`c2pa-2026-11-04`. An upgrade from a released version does not arise yet; nothing was released.
