# Step 63 (M8.1): how Omeka S translates a module

2026-11-08, at Maurice's request ("ok, werk de vertaalspec uit, Nederlands eerst"), before SPEC-009
rests on it. Step 57 found internationalization the one documented Omeka requirement the module
does not meet.

## Measured

In a clean Omeka S 4.2.1 on PHP 8.3 (the trial compose project of step 48, its volumes removed
first), with the 0.1.2 zip of step 62 installed and the Pixel photo and the Amazon Titan PNG
uploaded through the admin form. The pages were read with the integration tests'
`OmekaInstallation` as the administrator; the scripts are not in the repository.

| # | Situation | Omeka's own text | The module's text | Media page |
|---|---|---|---|---|
| 1 | locale `en_US` (the installation's default) | English | English | 200 |
| 2 | global setting `locale` = `nl_NL`, nothing else changed | Dutch ("Uitloggen", "Objecten", "Taken") | English | 200 |
| 3 | as 2, plus `language/nl_NL.mo` in the module and a `translator` key in its `module.config.php` (below) | Dutch | Dutch where the `.mo` has the string, English elsewhere | **500** |
| 4 | as 3, with the one bad translation corrected | Dutch | Dutch where the `.mo` has the string, English elsewhere | 200 |
| 5 | as 4, plus the user setting `locale` = `en_US` for the administrator | English | English | 200 |

The `translator` key added in the container:

```php
'translator' => ['translation_file_patterns' => [[
    'type' => 'gettext', 'base_dir' => dirname(__DIR__).'/language',
    'pattern' => '%s.mo', 'text_domain' => null,
]]],
```

- Row 3 and 4: the browse cell read "Geverifieerd: vertrouwde ondertekenaar", the browse column's
  heading and the block's heading the test translation of "Content Credentials", and the block's
  line "Ondertekend door Pixel Camera (Google LLC)": the signer's name and issuer from the file,
  filled into the translated format. "Signed at …", not in the `.mo`, stayed English. The `.mo`
  was read on the next request; nothing had to be cleared.
- Row 3: the test `.mo` translated `Signed by %1$s (%2$s)` as `Ondertekend door %1$s (%2$s) %3$s`.
  The media page answered 500 with Omeka's error page ("Omeka S is een fout tegengekomen"): the
  whole page, not only the block, was lost. The error itself is not shown (production mode). The
  likely cause, measured outside Omeka on the local PHP 8.5:
  `vsprintf('%1$s (%2$s) %3$s', ['a', 'b'])` throws `ValueError: The arguments array must contain
  3 items, 2 given`.
- `msgfmt -c` (GNU gettext-tools, `/opt/homebrew/bin/msgfmt`, it prints its version as 1.0)
  compiled that bad translation without complaint. With `#, php-format` above the entry, the same
  check refused a msgstr with an extra placeholder: "a format specification for argument 2, as in
  'msgstr', doesn't exist in 'msgid'", exit 1. So the compiler catches it only when the entry is
  marked as a PHP format.
- Row 5: a user's own locale wins over the global one.

The environment was taken down with its volumes afterwards.

## Read (Omeka S 4.2.1 source, `build/omeka-s/`)

- `application/config/module.config.php`: Omeka's own `translator` has the same shape as above,
  with `base_dir` `application/language`; Laminas merges a module's `translation_file_patterns`
  with it.
- `application/src/Mvc/MvcListeners.php` (near lines 136 and 378): the locale is the user's setting `locale`, else the global setting, else `en_US`; the public
  site has a site setting. The module shows nothing on the public site.
- `application/language/` holds `nl_NL.po` and `nl_NL.mo` and no other Dutch locale, so
  `nl_NL` is the only Dutch value Omeka's locale selects can give.
- `application/src/View/Helper/Messages.php`: every message added with the Messenger is passed
  through `translate()` before it is escaped. A message made by concatenation (`Module.php` line
  172, "These trust settings were not saved: " plus the reason) can therefore not be translated as
  a whole; `Omeka\Stdlib\Message` carries a format and its arguments, and is translated as a
  format.
- `application/view/omeka/admin/module/browse.phtml`: the module's name and description from
  `module.ini` are shown as they are, not translated.
- The module's own code: 38 `translate(` calls in `src/`, `view/` and the closures of
  `SettingsPage` and `RecheckSection`; most pass a constant or an array value (`Presentation`'s
  headlines, labels and reasons, the evidence table's headings), which a tool reading the source
  for `translate('…')` does not see. One string has a `// @translate` marker (`StateColumn`).
- The job log lines (`Recheck`, `RecheckJob`, `RunFailed`) are written to the job's log once, in
  English; the stored rows hold states and reason keys, not sentences (`Presentation::REASONS`),
  so a stored row does not depend on a language.

## Not measured

- A message through the Messenger in Dutch: read only.
- Omeka's gulp task for module templates (in the Omeka S repository, not in the release): not run.
- A locale other than `en_US` and `nl_NL`.
