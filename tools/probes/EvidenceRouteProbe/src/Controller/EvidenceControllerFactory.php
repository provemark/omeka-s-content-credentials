<?php

declare(strict_types=1);

namespace EvidenceRouteProbe\Controller;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/** Measurement only (M5.2). */
final class EvidenceControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null): EvidenceController
    {
        return new EvidenceController($services->get('Omeka\Connection'));
    }
}
