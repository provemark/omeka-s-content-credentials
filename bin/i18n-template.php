<?php

declare(strict_types=1);

/*
 * SPEC-009 AC4: writes language/template.pot, every string the module
 * passes to translate() or to Omeka's Messenger, from its source.
 *
 * Two rules, read with PHP's own tokenizer:
 *  - a string literal that is the first argument of a call to `translate`,
 *    `$translate`, `$t`, or a Messenger's `addSuccess`, `addError`,
 *    `addWarning` or `addNotice`;
 *  - every string literal on a line that carries a `// @translate` comment,
 *    except an array key (`'key' =>`) and an index (`[$row['key']]`): the
 *    constants and arrays whose values are passed on later.
 * A msgid that is nothing but placeholders is left out; one with a
 * placeholder is marked `php-format`, so that a translator's `msgfmt -c`
 * checks a translation keeps them. The entries are sorted, with no source
 * references, so the file changes only when a string does.
 *
 * Tooling, not product code: outside the Deptrac layers and outside what
 * Omeka S loads, inside PHPStan and Pint. Tests require_once this file and
 * call i18nTemplate(); the command-line part at the bottom runs only when
 * this file is the script being executed.
 */

const I18N_TEMPLATE_CALLS = ['translate', 'addSuccess', 'addError', 'addWarning', 'addNotice'];

const I18N_TEMPLATE_VARIABLES = ['$translate', '$t'];

const I18N_TEMPLATE_PLACEHOLDER = '/%(?:\d+\$)?[sd]/';

/** The template for the module under $root: its Module.php, src/ and view/. */
function i18nTemplate(string $root): string
{
    $msgids = [];
    foreach (i18nTemplateFiles($root) as $file) {
        foreach (i18nTemplateStrings((string) file_get_contents($file)) as $msgid) {
            $msgids[$msgid] = $msgid;
        }
    }
    ksort($msgids, SORT_STRING);

    $pot = "# Content Credentials (C2PA) for Omeka S: the module's translatable strings.\n"
        ."# Made by bin/i18n-template.php (SPEC-009); not edited by hand.\n"
        ."msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n";
    foreach ($msgids as $msgid) {
        $pot .= "\n".(preg_match(I18N_TEMPLATE_PLACEHOLDER, $msgid) === 1 ? "#, php-format\n" : '')
            .'msgid "'.i18nTemplateQuote($msgid)."\"\nmsgstr \"\"\n";
    }

    return $pot;
}

/** @return list<string> */
function i18nTemplateFiles(string $root): array
{
    $files = is_file($root.'/Module.php') ? [$root.'/Module.php'] : [];
    foreach (['src' => '.php', 'view' => '.phtml'] as $directory => $extension) {
        if (! is_dir($root.'/'.$directory)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && str_ends_with($file->getFilename(), $extension)) {
                $files[] = $file->getPathname();
            }
        }
    }
    sort($files, SORT_STRING);

    return $files;
}

/**
 * The msgids of one file, by the two rules above.
 *
 * @return list<string>
 */
function i18nTemplateStrings(string $source): array
{
    $tokens = array_values(array_filter(
        token_get_all($source),
        static fn (array|string $token): bool => ! is_array($token) || $token[0] !== T_WHITESPACE,
    ));
    $marked = [];
    foreach ($tokens as $token) {
        if (is_array($token) && $token[0] === T_COMMENT && str_contains($token[1], '@translate')) {
            $marked[$token[2]] = true;
        }
    }

    $found = [];
    foreach ($tokens as $i => $token) {
        if (! is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }
        $before = $tokens[$i - 1] ?? null;
        $call = $tokens[$i - 2] ?? null;
        $after = $tokens[$i + 1] ?? null;
        $isArgument = $before === '(' && ($after === ')' || $after === ',') && is_array($call)
            && (($call[0] === T_STRING && in_array($call[1], I18N_TEMPLATE_CALLS, true))
                || ($call[0] === T_VARIABLE && in_array($call[1], I18N_TEMPLATE_VARIABLES, true)));
        $isKey = is_array($after) && $after[0] === T_DOUBLE_ARROW;
        $isIndex = $before === '[' && $after === ']';
        if ($isArgument || (isset($marked[$token[2]]) && ! $isKey && ! $isIndex)) {
            $msgid = i18nTemplateUnquote($token[1]);
            if (trim((string) preg_replace(I18N_TEMPLATE_PLACEHOLDER, '', $msgid)) !== '') {
                $found[] = $msgid;
            }
        }
    }

    return $found;
}

/** A PHP string literal's value; it holds no variables (T_CONSTANT_ENCAPSED_STRING). */
function i18nTemplateUnquote(string $literal): string
{
    $inner = substr($literal, 1, -1);

    return $literal[0] === "'" ? strtr($inner, ['\\\\' => '\\', "\\'" => "'"]) : stripcslashes($inner);
}

function i18nTemplateQuote(string $msgid): string
{
    return strtr($msgid, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\t" => '\\t']);
}

// --- command line: write language/template.pot of the repository ---
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $root = dirname(__DIR__);
    if (! is_dir($root.'/language')) {
        mkdir($root.'/language');
    }
    file_put_contents($root.'/language/template.pot', i18nTemplate($root));
    echo 'Wrote language/template.pot', PHP_EOL;
}
