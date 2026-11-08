<?php

declare(strict_types=1);

namespace ContentCredentials\Omeka;

use ContentCredentials\Display\Format;
use Laminas\View\Renderer\PhpRenderer;

/**
 * The view's `translate` and `escapeHtml`, typed (SPEC-003). Every string
 * the module outputs goes through escape(); its own strings through
 * translate() first.
 */
final class ViewText
{
    public function __construct(private readonly PhpRenderer $view) {}

    public function translate(string $source): string
    {
        $translate = $this->view->plugin('translate');
        $translated = is_callable($translate) ? $translate($source) : null;

        return is_string($translated) ? $translated : $source;
    }

    public function escape(string $text): string
    {
        $escapeHtml = $this->view->plugin('escapeHtml');
        $escaped = is_callable($escapeHtml) ? $escapeHtml($text) : null;

        return is_string($escaped) ? $escaped : htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * A line of a Presentation: its format translated, its values filled
     * in, the whole escaped once. A translation the values do not fit gives
     * the English line (SPEC-009 AC7).
     *
     * @param  array{string, list<string>}  $line
     */
    public function line(array $line): string
    {
        return $this->escape(Format::fill($this->translate($line[0]), $line[0], $line[1]));
    }
}
