<?php

declare(strict_types=1);

namespace ContentCredentials\Omeka;

use ContentCredentials\Display\Presentation;
use Laminas\View\Renderer\PhpRenderer;

/**
 * The evidence pages' content (SPEC-006 AC7). Everything from a row goes
 * through escapeHtml: a document holds text from the file.
 *
 * @phpstan-import-type EvidenceRow from EvidenceReader
 */
final class EvidenceHtml
{
    /** @param list<EvidenceRow> $rows newest first */
    public static function browse(PhpRenderer $view, int $mediaId, array $rows): string
    {
        $text = new ViewText($view);
        $html = '<p><a href="'.$text->escape(self::url($view, 'admin/id', ['controller' => 'media', 'id' => $mediaId])).'">'.$text->escape($text->translate('Back to the media item')).'</a></p>';
        if ($rows === []) {
            return $html.'<p>'.$text->escape($text->translate('No evidence is kept for this media item.')).'</p>';
        }
        $html .= '<table class="tablesaw content-credentials-evidence"><thead><tr>';
        foreach (['Checked', 'Verifier', 'Trust', 'File SHA-256', 'Document'] as $heading) { // @translate
            $html .= '<th>'.$text->escape($text->translate($heading)).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $trust = Presentation::trustLine($row['trust']);
            $show = self::url($view, EvidenceController::ROUTE, ['id' => $row['id']]);
            $download = self::url($view, EvidenceController::ROUTE, ['id' => $row['id'], 'action' => 'download']);
            $html .= '<tr><td>'.$text->escape($row['checked_at'].' UTC').'</td>'
                .'<td>'.$text->escape($row['verifier']).'</td>'
                .'<td>'.(is_array($trust) ? $text->line($trust) : '').'</td>'
                .'<td><code>'.$text->escape($row['checked_sha256']).'</code></td>'
                .'<td><a href="'.$text->escape($show).'">'.$text->escape($text->translate('View')).'</a> '
                .'<a href="'.$text->escape($download).'">'.$text->escape($text->translate('Download')).'</a></td></tr>';
        }

        return $html.'</tbody></table>';
    }

    /** @param EvidenceRow $row */
    public static function show(PhpRenderer $view, array $row): string
    {
        $text = new ViewText($view);
        $list = self::url($view, EvidenceController::MEDIA_ROUTE, ['media-id' => $row['media_id']]);
        $download = self::url($view, EvidenceController::ROUTE, ['id' => $row['id'], 'action' => 'download']);

        return '<p><a href="'.$text->escape($list).'">'.$text->escape($text->translate('All checks of this media item')).'</a> '
            .'<a href="'.$text->escape($download).'">'.$text->escape($text->translate('Download')).'</a></p>'
            .'<pre class="content-credentials-document" style="white-space: pre-wrap; overflow-wrap: anywhere;">'.$text->escape($row['document']).'</pre>';
    }

    /** @param array<string, int|string> $parameters */
    private static function url(PhpRenderer $view, string $route, array $parameters): string
    {
        $url = $view->plugin('url');
        $href = is_callable($url) ? $url($route, $parameters) : null;

        return is_string($href) ? $href : '';
    }
}
