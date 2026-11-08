# Step 64 (M8.4): translatable text

2026-11-08, at Maurice's go ("ok, akkoord met het amendement, begin met M8.4"), on SPEC-009 with
its amendment 1: the module becomes translatable and ships no language.

## What changed

- `config/module.config.php`: a `translator` key that loads `language/<locale>.mo`, the shape
  step 63 measured.
- `bin/i18n-template.php` writes `language/template.pot`: 79 strings, sorted, without source
  references, each with a placeholder marked `#, php-format`. Its two rules (a literal first
  argument of `translate`, `$translate`, `$t` or a Messenger `add…`; every literal on a line marked
  `// @translate`, except an array key and an index) are in the script's header. The marks were
  added in `Presentation`, `VerdictHtml`, `EvidenceHtml`, `RecheckSection`, `RecheckRuns`,
  `TrustChoice`, `SettingsPage` and `Module.php`.
- Sentences that were built by concatenation are now one format each: "Latest run: …" (four
  formats, for a run with or without its start and end), "Reason: %s" on the configure page, and
  "These trust settings were not saved: %s" in `Module.php`. The English output is unchanged; every
  earlier test that reads it passed as it was.
- `src/Display/Format::fill()` fills a translated format and, when PHP throws `ValueError` (a
  placeholder the arguments do not fill, an unknown conversion), fills the English source instead.
  It is used by `ViewText::line()`, `SettingsPage`, `RecheckSection` and `Module.php`. The refusal
  message is filled in `Module.php` and handed to the Messenger as plain text, rather than as an
  `Omeka\Stdlib\Message` (the spec's API sketch): Omeka's `Message` fills the format inside
  `messages()`, where a broken translation could not be caught. The module's own reasons
  (`larger than 1 MiB`, `the trust settings could not be read`) are translated before they are
  filled in; the verifier's are passed as they are.
- `tools/build.sh` archives `language/`; `.gitignore` ignores `/language/*.mo`.
- `compose.yaml`: a tmpfs on `modules/ContentCredentials/language` in both test installations (see
  below).
- `Module.php`'s header said the evidence pages are open to every role; since step 59 they are open
  to Omeka's own roles. Corrected while the header gained its SPEC-009 sentence.

## Tests, red first

Written before the code, run against the code of `fd3fd28`:

- `tests/Unit/TranslationTest.php`: the whole file failed to load, `Warning:
  require_once(…/bin/i18n-template.php): Failed to open stream: No such file or directory`.
- `tests/Integration/TranslationTest.php`, `--group=SPEC-009`: "4 failed, 2 warnings". AC1:
  "Failed asserting that two arrays are identical" (no page of the module showed a bracket); AC8:
  "Failed asserting that an array contains '⟦These trust settings were not saved: ⟦larger than 1
  MiB⟧⟧'"; AC7 and AC9 failed too. AC2 and AC3 passed (with a warning for the missing template):
  they keep what the module already did, English for an English user and for a locale it has no
  file for, and were never red. AC7's first version expected "Verified: trusted signer" for
  `fixture-signed.jpg`, which is `Valid`; the expectation was corrected to "Intact: signer not
  trusted" before the code was written.
- The first integration run of the helper threw on the missing template ("An entry without msgid or
  msgstr"); `Gettext::entries()` now gives no entries for an empty file, so the tests failed on their
  own assertions.
- `tests/Release/BuildTest.php` (AC10, and SPEC-008 AC3 and AC4 by amendment 7) was changed before
  the code but not run red: it needs the release environment.

The pseudo translation: `tests/Support/Gettext.php` makes, from `template.pot`, a `.mo` in which
each msgstr is its msgid between `⟦` and `⟧` (a little-endian GNU `.mo` without hash table, written
in plain PHP), and AC1 reduces each page to the module's part, takes out everything between the
brackets and looks for any fragment of a msgid (its text between placeholders, three characters or
more) in what is left. AC1 covers the media page in ten states of the row and with no row, the
browse column, the advanced search fields, the active filters, both evidence pages and the
configure page with an ended run.

## Measured on the way

- **A file replaced on the host is not the file the container reads.** The first version wrote the
  test's `.mo` into the working tree, which the test installation mounts read-only. Run alone, AC8
  passed; after AC7, which places another `.mo`, every request to the configure page answered 500.
  Measured outside the tests (Docker Desktop on macOS): after `rm` and a new file of another size,
  the container's `stat` gave the old size, 5000 bytes, and `wc -c` answered "cannot open … No such
  file", three times in a row. So the tests now write the file inside the container, and
  `compose.yaml` mounts a tmpfs on `language/` in both installations, over the read-only tree. The
  release environment writes into the unpacked zip, as before. After the change the SPEC-009 group
  passed twice in a row.
- **The module's translation wins over Omeka's own.** "View" is in Omeka's `nl_NL.po` ("Bekijk").
  AC1 cannot tell the two apart (neither leaves "View" in English), so it was measured after
  `composer check`, with a script not kept: with the pseudo translation and locale `nl_NL`, the
  evidence list of media 37494 held "⟦View⟧" once and ">Bekijk<" not at all. The module's file,
  loaded after Omeka's, takes precedence for the same msgid.

## Not done

- The two reasons why the bundled lists cannot be used (`TrustChoice`'s `RuntimeException`s, with a
  file name in them) stay English on the configure page; a verifier's reason stays English by the
  spec. They show only when the module's own files are damaged.
- No `.po`, no `.mo`, no Dutch: amendment 1.

## composer check

Green on the working tree of this commit: SPEC-000, Pint, PHPStan, Deptrac, 357 unit, 152 integration
and 24 release tests.
