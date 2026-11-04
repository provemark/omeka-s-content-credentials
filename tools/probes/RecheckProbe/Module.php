<?php

declare(strict_types=1);

namespace RecheckProbe;

use Laminas\Mvc\Controller\AbstractController;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Module\AbstractModule;

/**
 * Measurement only (M4.2, notes/step-20-recheck-at-scale.md): dispatches
 * RecheckJob from the configure page with JSON arguments, through Omeka's
 * default strategy (PhpCli).
 */
final class Module extends AbstractModule
{
    public function getConfig(): array
    {
        return [];
    }

    public function getConfigForm(PhpRenderer $renderer): string
    {
        return '<p>RECHECKPROBE-FORM</p><input name="args">';
    }

    public function handleConfigForm(AbstractController $controller): bool
    {
        $post = $controller->getRequest()->getPost()->toArray();
        $args = json_decode((string) ($post['args'] ?? '{}'), true) ?: [];
        $this->getServiceLocator()->get('Omeka\Job\Dispatcher')->dispatch(RecheckJob::class, $args);

        return true;
    }
}
