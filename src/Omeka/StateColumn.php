<?php

declare(strict_types=1);

namespace ContentCredentials\Omeka;

use ContentCredentials\Display\Presentation;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractEntityRepresentation;
use Omeka\ColumnType\ColumnTypeInterface;

/**
 * The admin media browse's column "Content Credentials" (SPEC-003): the
 * headline only. No sorting: Omeka ignores a sort_by its adapter does not
 * know (step 13).
 */
final class StateColumn implements ColumnTypeInterface
{
    public const TYPE = 'content_credentials';

    public function __construct(private readonly VerdictReader $reader) {}

    public function getLabel(): string
    {
        return VerdictHtml::HEADING; // @translate
    }

    /** @return list<string> */
    public function getResourceTypes(): array
    {
        return ['media'];
    }

    public function getMaxColumns(): int
    {
        return 1;
    }

    /** @param array<mixed> $data */
    public function renderDataForm(PhpRenderer $view, array $data): string
    {
        return '';
    }

    /** @param array<mixed> $data */
    public function getSortBy(array $data): ?string
    {
        return null;
    }

    /**
     * Plain text: Omeka escapes the header itself (Browse::renderHeaderRow()).
     *
     * @param  array<mixed>  $data
     */
    public function renderHeader(PhpRenderer $view, array $data): string
    {
        return (new ViewText($view))->translate(VerdictHtml::HEADING);
    }

    /**
     * Markup: Omeka outputs a cell as it is (Browse::renderContentRow()).
     *
     * @param  array<mixed>  $data
     */
    public function renderContent(PhpRenderer $view, AbstractEntityRepresentation $resource, array $data): string
    {
        return VerdictHtml::cell(Presentation::of($this->reader->rowFor($resource->id())), new ViewText($view));
    }
}
