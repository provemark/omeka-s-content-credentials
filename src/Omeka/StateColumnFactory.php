<?php

declare(strict_types=1);

namespace ContentCredentials\Omeka;

use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use UnexpectedValueException;

/** Builds the column with Omeka S's entity manager (SPEC-003). */
final class StateColumnFactory
{
    public function __invoke(ContainerInterface $services): StateColumn
    {
        $entityManager = $services->get('Omeka\EntityManager');
        if (! $entityManager instanceof EntityManager) {
            throw new UnexpectedValueException('Omeka S did not provide its entity manager.');
        }

        return new StateColumn(new VerdictReader($entityManager));
    }
}
