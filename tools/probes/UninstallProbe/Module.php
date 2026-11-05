<?php

declare(strict_types=1);

// M7.2 probe, never shipped: writes a marker wherever Omeka's module pages fire
// view.details, naming the module the page is about.

namespace UninstallProbe;

use Laminas\EventManager\EventInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use Omeka\Module\AbstractModule;

final class Module extends AbstractModule
{
    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach('Omeka\Controller\Admin\Module', 'view.details', function (EventInterface $event): void {
            $entity = $event->getParam('entity');
            echo '<p>PROBE-DETAILS:'.(is_object($entity) && method_exists($entity, 'getId') ? $entity->getId() : '?').'</p>';
        });
    }
}
