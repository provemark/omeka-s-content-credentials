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

    public static function block(Presentation $presentation, ViewText $text): string
    {
        $html = '<div class="meta-group content-credentials">'
            .'<h4>'.$text->escape($text->translate(self::HEADING)).'</h4>'
            .'<div class="value">'.$text->escape($text->translate($presentation->headline())).'</div>';
        foreach ($presentation->lines() as $line) {
            $html .= '<div class="value">'.$text->line($line).'</div>';
        }

        return $html.'</div><!-- /content-credentials -->';
    }

    public static function cell(Presentation $presentation, ViewText $text): string
    {
        return '<span class="content-credentials-headline">'.$text->escape($text->translate($presentation->headline())).'</span>';
    }
}
