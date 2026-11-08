<?php

declare(strict_types=1);

namespace ContentCredentials\Omeka;

use ContentCredentials\Display\Presentation;

/**
 * The module's markup (SPEC-003): the block for the media page's sidebar
 * and the Details panel, in Omeka's own `meta-group` shape, and the
 * browse cell. Everything passes through ViewText::escape(); nothing from
 * a row becomes an attribute or a link.
 */
final class VerdictHtml
{
    public const HEADING = 'Content Credentials';

    /**
     * @param  string|null  $evidenceUrl  where the media item's evidence is, when it has any (SPEC-006 AC6)
     */
    public static function block(Presentation $presentation, ViewText $text, int $evidenceCount = 0, ?string $evidenceUrl = null): string
    {
        $html = '<div class="meta-group content-credentials">'
            .'<h4>'.$text->escape($text->translate(self::HEADING)).'</h4>'
            .'<div class="value">'.$text->escape($text->translate($presentation->headline())).'</div>';
        $label = $presentation->aiLabel();
        if ($label !== null) {
            $html .= '<div class="value">'.$text->escape($text->translate($label)).'</div>';
        }
        foreach ($presentation->lines() as $line) {
            $html .= '<div class="value">'.$text->line($line).'</div>';
        }

        if ($evidenceCount > 0 && $evidenceUrl !== null) {
            $line = $evidenceCount === 1 ? ['Evidence: 1 check kept', []] : ['Evidence: %s checks kept', [(string) $evidenceCount]];
            $html .= '<div class="value"><a href="'.$text->escape($evidenceUrl).'">'.$text->line($line).'</a></div>';
        }

        return $html.'</div><!-- /content-credentials -->';
    }

    public static function cell(Presentation $presentation, ViewText $text): string
    {
        // One element: Omeka's admin table wraps a cell's content in a flex span
        // (tablesaw-cell-content), which would set two elements side by side and
        // ignore the <br> between them (SPEC-007 amendment 4).
        $html = '<span class="content-credentials-cell"><span class="content-credentials-headline">'.$text->escape($text->translate($presentation->headline())).'</span>';
        $label = $presentation->aiLabel();
        if ($label !== null) {
            $html .= '<br><span class="content-credentials-ai">'.$text->escape($text->translate($label)).'</span>';
        }

        return $html.'</span>';
    }
}
