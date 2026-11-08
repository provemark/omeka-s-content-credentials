<?php

declare(strict_types=1);

/*
 * SPEC-008 AC18: omeka.org publishes README.md as the module's page, away from
 * GitHub, where a relative link leads nowhere. Every link and image target must
 * be an absolute https:// URL or a link within the page, and every image needs
 * alt text.
 */

/**
 * What in the Markdown would not hold outside GitHub, one line per problem.
 *
 * @return list<string>
 */
function spec008ReadmeProblems(string $markdown): array
{
    $problems = [];
    // Inline links and images: [text](target) and ![alt](target).
    preg_match_all('/(!?)\[([^\]]*)\]\(\s*<?([^)\s>]+)>?(?:\s+"[^"]*")?\s*\)/', $markdown, $links, PREG_SET_ORDER);
    // Reference definitions: [label]: target.
    preg_match_all('/^\s{0,3}\[[^\]]+\]:\s*<?(\S+?)>?(?:\s|$)/m', $markdown, $references);
    // Raw HTML: <a href> and <img src>.
    preg_match_all('/<(?:a|img)\b[^>]*\b(?:href|src)\s*=\s*"([^"]*)"/i', $markdown, $html);

    $targets = array_merge(array_column($links, 3), $references[1], $html[1]);
    foreach ($targets as $target) {
        if (! str_starts_with($target, 'https://') && ! str_starts_with($target, '#')) {
            $problems[] = "relative link: {$target}";
        }
    }
    foreach ($links as $link) {
        if ($link[1] === '!' && trim($link[2]) === '') {
            $problems[] = "image without alt text: {$link[3]}";
        }
    }
    preg_match_all('/<img\b(?![^>]*\balt\s*=\s*"[^"]+")[^>]*>/i', $markdown, $bare);
    foreach ($bare[0] as $tag) {
        $problems[] = "image without alt text: {$tag}";
    }

    return $problems;
}

it('AC18: every link and image in the README holds outside GitHub', function (): void {
    $readme = (string) file_get_contents(dirname(__DIR__, 2).'/README.md');

    expect(spec008ReadmeProblems($readme))->toBe([]);
})->group('SPEC-008');

it('AC18: a relative link or an image without alt text is named', function (): void {
    $markdown = "See [the lists](trust/README.md), [here](#here) and [GitHub](https://github.com/).\n"
        ."![](https://example.org/a.png) ![A](docs/b.png) <img src=\"https://example.org/c.png\">\n"
        ."[ref]: LICENSE\n";

    expect(spec008ReadmeProblems($markdown))->toBe([
        'relative link: trust/README.md',
        'relative link: docs/b.png',
        'relative link: LICENSE',
        'image without alt text: https://example.org/a.png',
        'image without alt text: <img src="https://example.org/c.png">',
    ]);
})->group('SPEC-008');
