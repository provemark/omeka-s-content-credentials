<?php

declare(strict_types=1);

namespace ContentCredentials\Omeka;

use ContentCredentials\Display\AiFilterValue;
use ContentCredentials\Display\FilterValue;
use ContentCredentials\Display\Presentation;
use ContentCredentials\Entity\StoredVerdict;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Laminas\EventManager\EventInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Representation\AbstractResourceRepresentation;
use Omeka\Api\Request;
use Omeka\Mvc\Status;
use UnexpectedValueException;

/**
 * The verdict in the admin interface (SPEC-003): the block on the media
 * page (`view.show.sidebar`) and in the Details panel (`view.details`),
 * and the browse filter (`view.advanced_search`, `view.search.filters`,
 * `api.search.query`). The filter applies to admin requests only: on the
 * public site and the REST API the parameter changes nothing (AC11).
 */
final class AdminDisplay
{
    public const SEARCH_PARTIAL = 'content-credentials/advanced-search';

    public function __construct(
        private readonly VerdictReader $reader,
        private readonly Status $status,
        private readonly EvidenceReader $evidence,
    ) {}

    public static function create(ServiceLocatorInterface $services): self
    {
        $entityManager = $services->get('Omeka\EntityManager');
        $status = $services->get('Omeka\Status');
        $connection = $services->get('Omeka\Connection');
        if (! $entityManager instanceof EntityManager || ! $status instanceof Status || ! $connection instanceof Connection) {
            throw new UnexpectedValueException('Omeka S did not provide its entity manager, status and connection.');
        }

        return new self(new VerdictReader($entityManager), $status, new EvidenceReader($connection));
    }

    /** @param EventInterface<object|string|null, array<mixed>|\ArrayAccess<mixed, mixed>|object> $event */
    public function sidebar(EventInterface $event): void
    {
        $view = $event->getTarget();
        if ($view instanceof PhpRenderer) {
            $this->echoBlock($view, $view->vars('media'));
        }
    }

    /** @param EventInterface<object|string|null, array<mixed>|\ArrayAccess<mixed, mixed>|object> $event */
    public function details(EventInterface $event): void
    {
        $view = $event->getTarget();
        if ($view instanceof PhpRenderer) {
            $this->echoBlock($view, $event->getParam('entity'));
        }
    }

    /** @param EventInterface<object|string|null, array<mixed>|\ArrayAccess<mixed, mixed>|object> $event */
    public function advancedSearch(EventInterface $event): void
    {
        $partials = $event->getParam('partials');
        if (is_array($partials)) {
            $partials[] = self::SEARCH_PARTIAL;
            $event->setParam('partials', $partials);
        }
    }

    /** @param EventInterface<object|string|null, array<mixed>|\ArrayAccess<mixed, mixed>|object> $event */
    public function searchFilters(EventInterface $event): void
    {
        $view = $event->getTarget();
        $query = $event->getParam('query');
        $filters = $event->getParam('filters');
        if (! $view instanceof PhpRenderer || ! is_array($query) || ! is_array($filters)) {
            return;
        }
        $state = FilterValue::parse($query[FilterValue::PARAMETER] ?? null);
        $ai = AiFilterValue::parse($query[AiFilterValue::PARAMETER] ?? null);
        if ($state === null && $ai === null) {
            return;
        }
        $text = new ViewText($view);
        // Plain text: Omeka's search-filters partial escapes labels and values.
        $label = $text->translate(VerdictHtml::HEADING);
        $values = is_array($filters[$label] ?? null) ? $filters[$label] : [];
        if ($state !== null) {
            $values[] = $state === false
                ? $text->translate('(unknown value)')
                : $text->translate(Presentation::HEADLINES[$state]);
        }
        if ($ai !== null) {
            $values[] = $ai === false
                ? $text->translate('(unknown value)')
                : $text->translate(Presentation::AI_LABELS[$ai]);
        }
        $filters[$label] = $values;
        $event->setParam('filters', $filters);
    }

    /** @param EventInterface<object|string|null, array<mixed>|\ArrayAccess<mixed, mixed>|object> $event */
    public function searchQuery(EventInterface $event): void
    {
        if (! $this->status->isAdminRequest()) {
            return;
        }
        $request = $event->getParam('request');
        $qb = $event->getParam('queryBuilder');
        $adapter = $event->getTarget();
        if (! $request instanceof Request || ! $qb instanceof QueryBuilder || ! $adapter instanceof AbstractEntityAdapter) {
            return;
        }
        $state = FilterValue::parse($request->getContent()[FilterValue::PARAMETER] ?? null);
        $ai = AiFilterValue::parse($request->getContent()[AiFilterValue::PARAMETER] ?? null);
        if ($state === false || $ai === false) {
            $qb->andWhere('1 = 0');

            return;
        }
        if ($state !== null) {
            $alias = $adapter->createAlias();
            $qb->innerJoin(StoredVerdict::class, $alias, 'WITH', "{$alias}.media = omeka_root.id")
                ->andWhere($qb->expr()->eq("{$alias}.state", $adapter->createNamedParameter($qb, $state)));
        }
        if ($ai !== null) {
            // The filter lists what the label shows: only on a file that verifies (SPEC-007 AC11).
            $alias = $adapter->createAlias();
            $qb->innerJoin(StoredVerdict::class, $alias, 'WITH', "{$alias}.media = omeka_root.id")
                ->andWhere($qb->expr()->eq("{$alias}.aiStatement", $adapter->createNamedParameter($qb, $ai)))
                // Expr::in() would quote a placeholder as a literal.
                ->andWhere("{$alias}.state IN (".$adapter->createNamedParameter($qb, [Presentation::TRUSTED, Presentation::VALID]).')');
        }
    }

    private function echoBlock(PhpRenderer $view, mixed $media): void
    {
        if ($media instanceof AbstractResourceRepresentation) {
            $id = $media->id();
            $count = is_numeric($id) ? $this->evidence->count((int) $id) : 0;
            $url = $count > 0 ? $view->plugin('url') : null;
            $href = is_callable($url) ? $url(EvidenceController::MEDIA_ROUTE, ['media-id' => $id]) : null;
            echo VerdictHtml::block(Presentation::of($this->reader->rowFor($id)), new ViewText($view), $count, is_string($href) ? $href : null);
        }
    }
}
