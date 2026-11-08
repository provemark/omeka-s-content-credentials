# SPEC-009: Translation, Dutch first

| Field      | Value                                             |
|------------|---------------------------------------------------|
| Status     | implemented                                       |
| Author     | Maurice van Loon                                  |
| Approved   | Maurice van Loon, 2026-11-08                      |
| Supersedes | —                                                 |

> Lifecycle: `draft` → maintainer approves → `approved` → tests-first →
> `implemented` (Traceability filled). No implementation code while `draft`.
> Only the Traceability section of an `approved` spec may change without a new
> approval; everything else needs a proposed amendment.

## Problem

Omeka S shows its admin interface in the language of the user's or the installation's locale. The
module's own text does not follow: an institution that runs Omeka S in Dutch reads Omeka's menus in
Dutch and the module's verdicts, configure page and evidence pages in English. Step 57
(`notes/step-57-omeka-requirements.md`) found this the one documented Omeka requirement the module
does not meet: no `language/` folder, no `translator` configuration, and the strings cannot be
extracted (one `@translate` marker for 38 `translate(` calls).

Step 63 (`notes/step-63-how-omeka-translates.md`) measured on Omeka S 4.2.1 what this spec rests
on:

- a `translator` key in the module's `module.config.php` with a gettext pattern for
  `language/%s.mo` is enough: with locale `nl_NL`, the module's strings in that `.mo` are shown in
  Dutch, and the strings it lacks stay English;
- a user's own locale wins over the global one;
- a translation whose placeholders do not match its source breaks the whole media page (HTTP 500,
  most likely PHP 8's `ValueError` from `vsprintf`), and `msgfmt -c` catches it only for an entry
  marked `php-format`.

Read in the same note: Omeka's Messenger translates each message before it escapes it, so a message
built by concatenation cannot be translated whole; `nl_NL` is Omeka's only Dutch locale; the
module's name and description in `module.ini` are not translated by Omeka.

The Dutch museums, archives and libraries that use Omeka S
are the first readers, so Dutch comes first.

## Scope

**In scope**

- A `translator` configuration in `config/module.config.php` that loads `language/<locale>.mo`.
- `language/template.pot`: every source string the module passes to `translate()`, made by a script
  in this repository, so it can be checked to be complete and current.
- `language/nl_NL.po` and `language/nl_NL.mo`: a complete Dutch translation.
- Every place the module shows its own text in the admin interface: the media page's sidebar and
  Details panel, the browse column and its heading, the advanced search fields and their active
  filters, the evidence pages and their page titles, the configure page (trust settings and runs),
  and the messages after a save, a start or an end on the configure page.
- Making each of those strings translatable whole: a sentence built by concatenation becomes one
  format with `%s` arguments (`Omeka\Stdlib\Message` for the Messenger).
- Failing closed on a bad translation: a translated format that cannot be filled falls back to the
  English source string, and the page is still shown.
- The zip gains `language/` (SPEC-008 amendment 7 below).

**Out of scope** (each needs its own spec before it may be built)

- Other languages than Dutch. The mechanism takes them without a code change; a new `.po` is a
  contribution with its own review.
- What does not belong to the module's interface and stays as it is:
  - the C2PA status codes and the verifier's `validation_state` words wherever they are shown as data
    (the codes under "Does not verify", the job log's counts);
  - text from the file (signer, issuer, URLs), filled into a translated format unchanged;
  - the verifier's own messages (for example the reason custom trust settings are refused, where it
    comes from the verifier);
  - the evidence document and its download name, byte-identical to `evidenceToJson()` (SPEC-006);
  - dates and times, which keep their ISO form and "UTC";
  - the job log lines, written once in English when a run writes them;
  - the module's name and description on Omeka's modules page (Omeka shows them untranslated);
  - the README and the other documentation.
- The public site: the module shows nothing there.
- Plural forms: no string needs one now (the evidence line already has a form for one and for more).

## Behavior

- **AC1 — Dutch for a Dutch installation**
  - Given locale `nl_NL` (the global setting), the bundled `language/nl_NL.mo`, and media with each
    state, an AI label, a signer and evidence
  - When the administrator views the media browse, a media page and its Details panel, the advanced
    search with both filters active, the evidence list and an evidence document, and the configure
    page with a run that has ended
  - Then every string of the module on those pages is the msgstr of `nl_NL.po` for it; the values
    from the file are filled in unchanged and the result escaped once by `escapeHtml`, as before
    (SPEC-003 AC8); none of the English source strings of `template.pot` appears in the module's
    parts of the pages

- **AC2 — English stays English**
  - Given locale `en_US`, or a global `nl_NL` with the user's own locale `en_US`
  - When the same pages are viewed
  - Then the module's text is the English source, and every existing integration test passes
    unchanged

- **AC3 — a locale without a translation** *(error path)*
  - Given locale `de_DE`, which Omeka has and the module does not
  - When the same pages are viewed
  - Then Omeka's text is German, the module's text English, and every page answers 200

- **AC4 — the template is complete and current**
  - Given the module's source (`Module.php`, `src/`, `view/`)
  - When the template script runs
  - Then it writes exactly the committed `language/template.pot`; that file holds every string the
    module can pass to `translate()` or to the Messenger (the literal arguments, and the constants and
    arrays whose values are passed, each marked `// @translate`), each once, and no other string; an
    entry with a `%` placeholder is marked `#, php-format`

- **AC5 — the Dutch translation is complete**
  - Given `language/template.pot`, `language/nl_NL.po` and `language/nl_NL.mo`
  - When they are compared
  - Then every msgid of the template has one non-empty msgstr in `nl_NL.po`, none marked `fuzzy`, and
    `nl_NL.po` has no msgid the template lacks; `nl_NL.mo` holds exactly the entries of `nl_NL.po`

- **AC6 — a translation must keep its placeholders** *(error path)*
  - Given a `.po` in which a msgstr has a placeholder its msgid lacks, lacks one its msgid has, or
    numbers them differently
  - When the check of AC5 runs on it
  - Then it fails and names the msgid; the committed `nl_NL.po` passes

- **AC7 — a bad translation does not take the page down** *(error path)*
  - Given a `language/nl_NL.mo` in which `Signed by %1$s (%2$s)` is translated with a third
    placeholder, and locale `nl_NL`
  - When the media page of a signed file is viewed
  - Then it answers 200; that one line is shown with the English source format filled in, every other
    line in Dutch; the same holds for every format the module fills (`ViewText::line()`, the
    `sprintf` sites of `SettingsPage` and `RecheckSection`, and the Messenger formats)

- **AC8 — the messages on the configure page**
  - Given locale `nl_NL`
  - When custom trust settings are refused, a run is started, a start is refused because a run is in
    progress, and a run is marked as ended
  - Then each message is the Dutch translation; in the refusal, the module's own reason is translated
    and a reason from the verifier is filled in as it is, both escaped by Omeka's `messages()`

- **AC9 — what is not translated stays byte for byte**
  - Given locale `nl_NL` and media checked before and after the change
  - When an evidence document is downloaded and an `Invalid` media page is viewed
  - Then the download is byte-identical to the stored document and its file name unchanged, and the
    status codes are shown as the verifier gave them

- **AC10 — the zip carries the translation**
  - Given the built zip
  - When it is unpacked in a clean Omeka S with locale `nl_NL`
  - Then the media browse shows the Dutch headline of each verdict (SPEC-008 amendment 7)

## References

- Specification: Omeka S developer documentation, "Internationalization"
  (`omeka.org/s/docs/developer/modules/internationalization/`), as read in step 57; the Omeka S 4.2.1 source:
  `application/config/module.config.php` (`translator`), `application/src/Mvc/MvcListeners.php`
  (the locale), `application/src/View/Helper/Messages.php`, `application/src/Stdlib/Message.php`,
  `application/language/nl_NL.po` (Omeka's own Dutch terms).
- Oracle: step 63, measured in Omeka S 4.2.1 on PHP 8.3 with the 0.1.2 zip and a hand-made
  `nl_NL.mo`; GNU gettext's `msgfmt -c` as printed there.
- Reasoned: that the 500 of step 63 is `vsprintf`'s `ValueError` (Omeka's error page did not show
  it); that the Messenger's translation behaves in Dutch as read in `Messages.php` (not measured).

## API sketch

```php
// config/module.config.php
'translator' => [
    'translation_file_patterns' => [[
        'type' => 'gettext',
        'base_dir' => dirname(__DIR__).'/language',
        'pattern' => '%s.mo',
        'text_domain' => null,
    ]],
],

// src/Omeka/ViewText.php: a format that cannot be filled falls back (AC7)
public function line(array $line): string
{
    try {
        $text = vsprintf($this->translate($line[0]), $line[1]);
    } catch (\ValueError) {
        $text = vsprintf($line[0], $line[1]);
    }
    return $this->escape($text);
}

// Module.php: one format, translated whole (AC8)
(new Messenger)->addError(new Message('These trust settings were not saved: %s', $refusal)); // @translate

// bin/i18n-template.php: writes language/template.pot from the source (AC4)
// tests/Unit/TranslationTest.php: AC4 to AC6, reading the .po and the .mo in plain PHP
```

## Open questions

Each with a recommendation; Maurice decides.

1. **The file format.** (a) `.po` and `.mo`, both committed, as Omeka S and its modules do, so
   translators can use the usual tools (and a later Transifex or Weblate); (b) Laminas' `phparray`,
   one PHP file per language: no binary, no tool, but not what an Omeka translator expects; (c) only
   the `.po` committed, the `.mo` made by `tools/build.sh`. *Recommendation: (a)*; the unit test of
   AC5 reads the committed `.mo` in plain PHP and compares it with the `.po`, so a stale `.mo` cannot
   slip through.
   *Status 2026-11-08:* answered, (a).
2. **The compiler.** (a) GNU gettext's `msgfmt -c`, a development tool outside Composer, recorded in
   an ADR-0007 with the version used; CI does not need it, since the test checks the committed `.mo`;
   (b) a small compiler of our own in `tools/`. *Recommendation: (a)*, with the ADR written before the
   first `.mo`.
   *Status 2026-11-08:* answered, (a).
3. **The template.** (a) a script of our own, `bin/i18n-template.php`, that collects literal
   `translate('…')` arguments and `// @translate` strings, with AC4's test that it is current;
   (b) Omeka's gulp task from the Omeka S repository (Node and a checkout of Omeka S needed).
   *Recommendation: (a)*: no new dependency, and the test sees a forgotten string.
   *Status 2026-11-08:* answered, (a).
4. **The fallback of AC7.** (a) in the code at every format site, as in the API sketch, plus the
   check of AC6; (b) only the check of AC6. *Recommendation: (a)*: step 63 measured that one bad
   string takes the whole media page down, and a later `.po` from someone else should not be able
   to do that.
   *Status 2026-11-08:* answered, (a).
5. **The Dutch words.** Omeka's own `nl_NL.po` is followed where the module uses a word Omeka has
   ("Bekijk" for View, "Taken" for Jobs). "Content Credentials" stays as it is, a name. Proposed for
   the main strings, for Maurice to correct:

   | English | Dutch (proposal) |
   |---|---|
   | Verified: trusted signer | Geverifieerd: vertrouwde ondertekenaar |
   | Intact: signer not trusted | Intact: ondertekenaar niet vertrouwd |
   | Does not verify | Verificatie mislukt |
   | No Content Credentials | Geen Content Credentials |
   | Could not be checked | Kon niet worden gecontroleerd |
   | Not checked | Niet gecontroleerd |
   | AI-generated (signed) | Gemaakt met AI (ondertekend) |
   | AI-edited (signed) | Bewerkt met AI (ondertekend) |
   | Evidence | Bewijs |
   | Trust settings | Vertrouwensinstellingen |

   *Recommendation:* Maurice reads the whole `nl_NL.po` before the step that adds it is committed.
   *Status 2026-11-08:* answered, as recommended; the table is the starting point.
6. **A release.** The translation changes no table and no stored row. *Recommendation:* 0.2.0
   after this spec, since it is a new feature, upgraded like 0.1.2.
   *Status 2026-11-08:* answered, as recommended; the release itself still needs Maurice's go.
   Read at approval: Omeka S 4.2.1's own `nl_NL.po` has 1089 of its 1728 strings translated
   (`msgfmt --statistics`), so a Dutch installation still shows some of Omeka's own text in English;
   AC1 is about the module's text only.

## Amendments to other specs (approved with this one)

- **SPEC-008 amendment 7:** AC3's top level gains `language/`; AC4 compares `language/` with HEAD's
  byte for byte, like `trust/`, `view/` and `config/`. `tools/build.sh` archives `language` too. Was:
  no `language/`.

SPEC-003 keeps its criteria: the strings it names are its English source strings, and its escaping
is unchanged.

## Amendments

Approved by Maurice van Loon on 2026-11-08, after the spec and before any test or code. The
criteria above keep their original words; this amendment says how they now read.

1. **Translatable, no language shipped.** Maurice asked why the module must ship a Dutch
   translation. Omeka's documentation asks that a module be translatable (a `translator`
   configuration, every string marked, a template others can translate from), not that it ship
   a language (step 57). A shipped language also has a cost: every new or changed string then
   needs its translation before the tests pass, and a Dutch administrator still sees Omeka's own
   untranslated third in English. So this spec makes the module translatable; a Dutch
   translation becomes a later spec of its own, when a user asks for it or Maurice wants it.
   - **Title:** "Translatable text". Was: "Translation, Dutch first". The file name stays.
   - **Scope:** `language/nl_NL.po` and `language/nl_NL.mo` leave the scope; `language/` holds
     `template.pot` only. A `.mo` an administrator puts in `language/` is loaded (step 63). Out of
     scope gains: any shipped translation, Dutch included, and the check of a `.po` (AC5, AC6).
   - **The pseudo translation.** The integration tests make, from `template.pot`, a `.mo` in
     which each msgstr is its msgid between `⟦` and `⟧`, with the placeholders kept, and place
     it as `language/nl_NL.mo` in the test environment only. The test support writes that `.mo`
     in plain PHP; nothing is compiled with `msgfmt`, and no `.mo` is committed.
   - **AC1** now reads: with locale `nl_NL` and the pseudo translation, every string of the module
     on the pages AC1 names is shown between `⟦` and `⟧`, the values from the file filled in
     unchanged and escaped once by `escapeHtml`, as before. So every string the module shows goes
     through `translate()` and is in the template. Was: the Dutch of `nl_NL.po`.
   - **AC2, AC3, AC4** stay as written. AC3 still holds: no `de_DE.mo` is shipped.
   - **AC5 and AC6** move to the later spec of a shipped translation. Was: a complete Dutch
     translation, and the placeholder check of a `.po`. AC4's `#, php-format` marks stay, so a
     translator's `msgfmt -c` checks the placeholders.
   - **AC7** stays as written; its `.mo` is a test file the test places, not a shipped one.
   - **AC8** now reads: with the pseudo translation, each of the four messages is shown between
     `⟦` and `⟧` as one whole; in the refusal, the module's own reason is translated and a reason
     from the verifier is filled in as it is. Was: the Dutch translation.
   - **AC9** stays as written, with the pseudo translation in place of the Dutch.
   - **AC10** now reads: the zip holds `language/template.pot`, identical to HEAD's, and no `.po`
     or `.mo`. Was: the Dutch headline in a clean Omeka S.
   - **SPEC-008 amendment 7** now reads: AC3's top level gains `language/`, which holds only
     `template.pot`; AC4 compares it with HEAD's byte for byte.
   - **Open questions 1, 2 and 5** no longer apply: no `.po` or `.mo` is committed, no compiler
     is used, so no ADR-0007, and the Dutch words wait for the later spec, the table there as a
     starting point. Question 3 (the template script) and 4 (the fallback) stand as answered.
     Question 6 now reads: an English user sees no change, so the release is 0.1.3, not 0.2.0.

## Traceability

Filled when status becomes `implemented`.

| Acceptance criterion | Test (file :: name / group) | Source (file/symbol) |
|----------------------|-----------------------------|----------------------|
| AC1 | `tests/Integration/TranslationTest.php` :: AC1: with a translation, every string of the module on every page is translated | `config/module.config.php` (`translator`); the `// @translate` marks in `src/`; `RecheckSection::html()` (one format per case) |
| AC2 | `tests/Integration/TranslationTest.php` :: AC2: a user whose own locale is English sees English; every earlier integration test, unchanged | (Omeka S's locale) |
| AC3 | `tests/Integration/TranslationTest.php` :: AC3: a locale the module has no translation for shows the module in English, and every page answers | (no `.mo` shipped) |
| AC4 | `tests/Unit/TranslationTest.php` :: AC4: the committed template is what the script writes; the template holds the strings the module passes on; a format is marked php-format, nothing else is, and no msgid is only a placeholder; the script takes literal calls and marked lines, and leaves keys and unmarked strings | `bin/i18n-template.php`, `language/template.pot` |
| AC5 | moved to a later spec of a shipped translation by amendment 1 | — |
| AC6 | moved to a later spec of a shipped translation by amendment 1 | — |
| AC7 | `tests/Unit/TranslationTest.php` :: AC7 (three tests); `tests/Integration/TranslationTest.php` :: AC7: a translation that breaks a format does not take a page down | `src/Display/Format.php`; `ViewText::line()`; `SettingsPage::html()`; `RecheckSection::html()`; `Module::handleConfigForm()` |
| AC8 | `tests/Integration/TranslationTest.php` :: AC8: the messages of the configure page are translated whole | `Module::handleConfigForm()`, `Module::translator()`; the marks in `RecheckRuns` and `TrustChoice::refusal()` |
| AC9 | `tests/Integration/TranslationTest.php` :: AC9: what is not translated stays as it was | (unchanged: `EvidenceController`, `Presentation::of()`'s codes) |
| AC10 | `tests/Release/BuildTest.php` :: AC10: the zip carries the template and no translation; SPEC-008 AC3 and AC4 (amendment 7) | `tools/build.sh` |
