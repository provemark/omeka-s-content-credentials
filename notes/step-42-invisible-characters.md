# Step 42 (M7.14, finding 7): every control and format character is made visible

2026-11-07. Finding 7 of the review (step 35): U+061C, U+200B, U+200D, U+2060 and U+FEFF passed
`Text::visible()`, while its comment said nothing could hide. A name with a zero-width space reads the
same as the name without it. SPEC-003 amendment 4, approved by Maurice, replaces the list with two
Unicode categories.

## What was measured before building

With PHP 8.3's PCRE 10.49 (`PCRE_VERSION`): U+061C, U+200B, U+200C, U+200D, U+2060, U+FEFF, U+00AD
and U+E0041 are `\p{Cf}`; U+2028 and U+3164 are neither Cf nor Cc. Every direction control of the old
list is Cf.

## What was built

`Text::visible()` replaces every `[\p{Cc}\p{Cf}]` character with U+FFFD, after replacing bytes that are
not UTF-8. Its comment says so.

## Tests seen red (measured)

```
vendor/bin/pest tests/Unit/ShowTheResultTest.php --filter=AC8
Tests:    14 failed, 21 passed (44 assertions)
```

Thirteen characters that passed, and the "Signed by" line with a zero-width space, a tag character and
a byte order mark. The 21 that passed: the nine characters replaced before, ten texts that must stay
as they are (Greek, Japanese, Arabic, a combining mark, an emoji, a no-break space, U+FFFD itself, and
the blank-looking letters U+3164 and U+2800, a known limit), and two earlier tests. After building:
the unit suite 331 passed.

## Falsified (measured)

Each a temporary change of the pattern, restored after its run, on `tests/Unit/ShowTheResultTest.php`.

| change | failed (of 66) |
|---|---|
| the old list | 14 |
| the old list and the review's five | 9 |
| Cc only | 20 |
| Cf only | 5 |
| all of C (also private use and unassigned) | 1 |
| Cf without the tag characters | 4 |
| also the separators Z | 5 |
| also the marks M | 1 |
| no `u` flag | 28 |
| the text not scrubbed first | 1 |

In a first run, on 65 tests, two changes survived: all of C, and no scrubbing. Two tests were added,
which pass on the built code and fail with those changes:

- **A private use character (U+E000) stays.** The amendment names Cc and Cf, not all of C.
- **Bytes that are not UTF-8 are replaced, not dropped with the rest of the text.** Without the scrub,
  `preg_replace()` with `u` returns null on such text and the function gave an empty string, which the
  earlier test (valid UTF-8 out) allowed.

## Read and measured, not changed

- **The replacement for bytes that are not UTF-8 is `?`, not U+FFFD:** `mb_scrub()` uses
  `mb_substitute_character()`, 63 here (measured: `"a\xFFb"` gives `a?b`). The spec says nothing about
  it; it was so before this step.
- **Which characters are Cf follows PCRE's Unicode data**, so a newer PHP may add some. The tests name
  the characters they rely on.
- **The evidence page** shows the stored document escaped in a `<pre>`, without `Text::visible()`, as it
  must stay byte-identical. Whether invisible characters reach it as such depends on how the verifier
  writes its JSON. Not measured; not part of this finding.

## Not measured

- The page in a browser, by the assistant.
