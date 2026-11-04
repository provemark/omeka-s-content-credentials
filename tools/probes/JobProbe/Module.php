<?php

declare(strict_types=1);

namespace JobProbe;

use Laminas\Mvc\Controller\AbstractController;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Module\AbstractModule;

/**
 * Measurement only (M4.1, notes/step-18-jobs.md): dispatches ProbeJob from
 * the configure page with a chosen strategy and arguments, so the test can
 * watch Omeka's job table, log and stop action.
 */
final class Module extends AbstractModule
{
    public function getConfig(): array
    {
        return [];
    }

    public function getConfigForm(PhpRenderer $renderer): string
    {
        return '<p>JOBPROBE-FORM</p><input name="strategy"><input name="args">';
    }

    public function handleConfigForm(AbstractController $controller): bool
    {
        $post = $controller->getRequest()->getPost()->toArray();
        $args = json_decode((string) ($post['args'] ?? '{}'), true) ?: [];
        $services = $this->getServiceLocator();
        $strategy = ($post['strategy'] ?? '') === 'sync' ? $services->get('Omeka\Job\DispatchStrategy\Synchronous') : null;
        $t = microtime(true);
        $job = $services->get('Omeka\Job\Dispatcher')->dispatch(ProbeJob::class, $args, $strategy);
        ProbeJob::log(sprintf('DISPATCHED id=%d strategy=%s returned_after=%.3fs status=%s', $job->getId(), $post['strategy'] ?? 'default', microtime(true) - $t, $job->getStatus()));

        return true;
    }
}
