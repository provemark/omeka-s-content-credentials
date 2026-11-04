<?php

declare(strict_types=1);

namespace ViewProbe;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\ColumnType\ColumnTypeInterface;

final class ProbeColumn implements ColumnTypeInterface
{
    public function getLabel(): string
    {
        return 'View probe';
    }

    public function getResourceTypes(): array
    {
        return ['media'];
    }

    public function getMaxColumns(): ?int
    {
        return 1;
    }

    public function renderDataForm(PhpRenderer $view, array $data): string
    {
        return '';
    }

    public function getSortBy(array $data): ?string
    {
        return 'view_probe';
    }

    public function renderHeader(PhpRenderer $view, array $data): string
    {
        return 'VIEWPROBE-HEADER';
    }

    public function renderContent(PhpRenderer $view, AbstractEntityRepresentation $resource, array $data): ?string
    {
        return 'VIEWPROBE-COL-'.$resource->id().' '.$view->escapeHtml(Module::HOSTILE);
    }
}
