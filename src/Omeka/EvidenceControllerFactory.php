<?php

declare(strict_types=1);

namespace ContentCredentials\Omeka;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

/** SPEC-006: the evidence pages' controller with its reader. */
final class EvidenceControllerFactory
{
    public function __invoke(ContainerInterface $services): EvidenceController
    {
        $connection = $services->get('Omeka\Connection');
        if (! $connection instanceof Connection) {
            throw new UnexpectedValueException('Omeka S did not provide its database connection.');
        }

        return new EvidenceController(new EvidenceReader($connection));
    }
}
