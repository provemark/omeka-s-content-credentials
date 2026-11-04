<?php

declare(strict_types=1);

namespace ViewProbe;

use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Omeka\Module\AbstractModule;

/**
 * Measurement only (M2.1, notes/step-13-admin-views.md): every hook M2
 * could use on the admin media pages prints a marker; one prints a hostile
 * string through escapeHtml.
 */
final class Module extends AbstractModule
{
    public const HOSTILE = '<script>alert("x")</script>&"\'';

    public function getConfig(): array
    {
        return require __DIR__.'/config/module.config.php';
    }

    public function attachListeners(SharedEventManagerInterface $shared): void
    {
        $media = 'Omeka\Controller\Admin\Media';
        foreach (['view.show.sidebar', 'view.show.after', 'view.details', 'view.browse.before', 'view.browse.after'] as $name) {
            $shared->attach($media, $name, static function (Event $event) use ($name): void {
                $view = $event->getTarget();
                $resource = $event->getParam('entity') ?? $view->vars('media') ?? $view->vars('resource');
                $id = is_object($resource) && method_exists($resource, 'id') ? $resource->id() : 'none';
                echo '<p>VIEWPROBE-'.strtoupper(str_replace(['view.', '.'], ['', '-'], $name)).' id='.$id.' '.$view->escapeHtml(self::HOSTILE).'</p>';
            });
        }
        $shared->attach($media, 'view.advanced_search', static function (Event $event): void {
            $partials = $event->getParam('partials');
            $partials[] = 'view-probe/advanced-search';
            $event->setParam('partials', $partials);
        });
        $shared->attach($media, 'view.search.filters', static function (Event $event): void {
            $query = $event->getParam('query');
            if (! empty($query['view_probe'])) {
                $filters = $event->getParam('filters');
                $filters['VIEWPROBE-FILTER'][] = 'on';
                $event->setParam('filters', $filters);
            }
        });
        $shared->attach('Omeka\Api\Adapter\MediaAdapter', 'api.search.query', static function (Event $event): void {
            $query = $event->getParam('request')->getContent();
            if (! empty($query['view_probe'])) {
                $qb = $event->getParam('queryBuilder');
                $qb->andWhere($qb->expr()->eq('omeka_root.mediaType', ':vp'))->setParameter('vp', 'image/png');
            }
        });
    }
}
