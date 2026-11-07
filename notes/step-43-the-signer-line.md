# Step 43 (M7.14, finding 8): the signer line names what is present

2026-11-07. Finding 8 of the review (step 35), read there: a certificate without an organisation gave
"Signed by X ()". SPEC-003 amendment 5, approved by Maurice with "Signed by Org" for an organisation
alone, says which line each case gives. This was the last finding of M7.14.

## What was measured before building

`Presentation::of()` on a `Valid` row, PHP 8.3:

| common name | organisation | line |
|---|---|---|
| `X` | null | `Signed by X ()` |
| null | `Org` | `Signed by  (Org)` |
| `X` | `""` | `Signed by X ()` |
| `""` | `""` | `Signed by  ()` |

The verifier fills `issuer` with the leaf certificate's organisation and `common_name` with its CN
(read, `Verifier.php:263`), which `Verdict` stores as `signer_issuer` and `signer_common_name`.

## What was built

`Presentation::present()`: a value that is text and not empty after `trim()`, made visible, else null.
The line is "Signed by {name} ({organisation})" with both, "Signed by" and the one present with one,
and no line with neither.

The first build trimmed after `Text::visible()`, and the test "spaces only" failed: a tab is a control
character, which step 42 makes a visible U+FFFD, so it no longer trimmed away. The value is now trimmed
before it is made visible.

## Tests seen red (measured)

```
vendor/bin/pest tests/Unit/ShowTheResultTest.php --filter='AC2: the signer line'
Tests:    12 failed, 3 passed (21 assertions)
```

The three that passed hold what must not change: both present, neither present, and an invisible
character as organisation, which is present and shows as U+FFFD. One case was taken out before the
red run: spaces around a name that has text. The amendment says only that a value empty after trimming
is absent, not whether the spaces show. After building: the unit suite 347 passed.

## Falsified (measured)

Each a temporary change, restored after its run, on `tests/Unit/ShowTheResultTest.php` (81 tests).

| change | failed |
|---|---|
| the old line | 10 |
| no trim | 2 |
| trimmed after `Text::visible()` | 1 |
| an organisation alone in brackets | 4 |
| a name alone dropped | 6 |
| an organisation alone dropped | 4 |
| both: only the name | 5 |
| both: the organisation first | 5 |
| a value that is not text cast to text | 1 |
| not made visible | 3 |

## Not measured

- A real file whose certificate has no organisation: none was looked for. The rule rests on
  constructed rows.
- The page in a browser, by the assistant.
