<?php

declare(strict_types=1);

namespace SettingsProbe;

use Doctrine\ORM\Events;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Module\AbstractModule;
use Omeka\Mvc\Controller\Plugin\Messenger;

/**
 * Measurement only (M3.1, notes/step-15-settings.md): the module
 * configuration form, Omeka\Settings with a large value, reading it after
 * an upload's flush, a warning on every admin page, and upgrade().
 * Everything it learns goes to /tmp/settings-probe.log in the container.
 */
final class Module extends AbstractModule
{
    public const KEY = 'settings_probe_json';

    private bool $attached = false;

    public static function log(string $line): void
    {
        file_put_contents('/tmp/settings-probe.log', $line."\n", FILE_APPEND);
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $services): void
    {
        self::log("UPGRADE old={$oldVersion} new={$newVersion}");
    }

    public function getConfigForm(PhpRenderer $renderer): string
    {
        $value = $this->getServiceLocator()->get('Omeka\Settings')->get(self::KEY, '');
        self::log('FORM rendered len='.strlen((string) $value));
        (new Messenger)->addWarning('SETTINGSPROBE-FORM-WARNING');

        return '<p>SETTINGSPROBE-FORM</p><textarea name="probe_json">'.$renderer->escapeHtml((string) $value).'</textarea>';
    }

    public function handleConfigForm(AbstractController $controller): bool
    {
        $value = $controller->params()->fromPost('probe_json');
        self::log('HANDLE called type='.get_debug_type($value).' len='.(is_string($value) ? strlen($value) : -1));
        if ($value === 'FAIL') {
            $controller->messenger()->addError('SETTINGSPROBE-REFUSED');

            return false;
        }
        $this->getServiceLocator()->get('Omeka\Settings')->set(self::KEY, $value);

        return true;
    }

    public function attachListeners(SharedEventManagerInterface $shared): void
    {
        $shared->attach('*', 'view.layout', function (Event $event): void {
            $view = $event->getTarget();
            if ($view->status()->isAdminRequest()) {
                (new Messenger)->addWarning('SETTINGSPROBE-LAYOUT-WARNING');
            }
        });
        $shared->attach('Omeka\Entity\Media', 'entity.persist.post', function (): void {
            if ($this->attached) {
                return;
            }
            $this->attached = true;
            $services = $this->getServiceLocator();
            $services->get('Omeka\EntityManager')->getEventManager()->addEventListener(Events::postFlush, new class($services)
            {
                private bool $done = false;

                public function __construct(private ServiceLocatorInterface $services) {}

                public function postFlush(): void
                {
                    if ($this->done) {
                        return;
                    }
                    $this->done = true;
                    $value = $this->services->get('Omeka\Settings')->get(Module::KEY);
                    Module::log('POSTFLUSH type='.get_debug_type($value).' sha256='.(is_string($value) ? hash('sha256', $value) : '-'));
                }
            });
        });
    }
}
