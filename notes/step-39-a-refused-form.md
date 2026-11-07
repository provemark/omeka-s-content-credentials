# Step 39 (M7.14, finding 4): a refused form gives back the DigiCert choice

2026-11-07. Finding 4 of the review (step 35): a refused settings form gave back the submitted text
but showed the saved DigiCert choice, so an administrator who corrected the text and saved lost the
choice they had just made, with nothing to say so. It holds in both directions; the review named only
the box turned off. SPEC-004 amendment 4 adds AC12, approved by Maurice.

## What was built

- `TrustConfig::submittedDigicert()`: the one reading of the checkbox, on only for exactly `'1'`,
  used by `save()` and by the refusal path.
- `Module::$refusedDigicert`, set beside `$refused` in `handleConfigForm()`, and
  `TrustConfig::form()` shows it instead of the stored choice.

Nothing changes in what is stored: a refusal already saved nothing. The warning about saved custom
settings that cannot be used still describes what is saved, as Maurice decided with the amendment.

## Tests seen red (measured, Omeka S 4.2.1 on PHP 8.3)

```
vendor/bin/pest --testsuite=Integration --filter='AC12: a (refused form|submitted value)'
Tests:    8 failed (24 assertions)
```

All on the box: `true is identical to false` and the reverse, `true is false`. After building, with
AC5 and AC3 in the same filter: 12 passed (87 assertions).

## Falsified (measured)

Each a temporary change, restored after its run; `shasum` confirmed both files at the end. Run on the
filter `AC12: a (refused form|submitted value)|AC5: invalid custom|AC3: DigiCert decides`, 12 tests.

| change | failed |
|---|---|
| the page keeps showing the stored choice | 8 |
| only a submitted on is given back | 7 |
| only a submitted off is given back | 1: off, turned on |
| the box always unchecked after a refusal | 1: off, turned on |
| the module does not keep the choice | 8 |
| the choice saved before the refusal | 9, with AC5 |
| any non-empty value other than `0` counts as on | 2: `on`, `1 ` |
| a loose compare | 1: `1 ` |
| an array counts as on | 1 |
| a missing value counts as on | 1 |
| the choice never saved | 3, with AC5 |

The next save, only read in the review, is now measured: the page saved as shown, with the text
corrected, stores the submitted choice, and a new page shows it.

## Reasoned, not measured

- **"No warning" is seen only as far as the page shows one.** The test looks for `Warning:` and
  `Array to string` in the body and the status 200. The reading is a strict compare on a value that
  may be absent, which raises no warning whatever the value is.

## Not measured

- The page in a browser, by the assistant.
