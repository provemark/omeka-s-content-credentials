<?php

declare(strict_types=1);

namespace EvidenceRouteProbe;

use EvidenceRouteProbe\Controller\EvidenceController;
use EvidenceRouteProbe\Controller\EvidenceControllerFactory;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\Http\Segment;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Module\AbstractModule;

/**
 * Measurement only (M5.2, notes/step-24-evidence-in-omeka.md): a table of
 * evidence documents, an admin route with a controller of the module's own,
 * an ACL rule chosen on the configure page, a link in the media page's
 * sidebar, and a job that writes a document for every checkable media item.
 */
final class Module extends AbstractModule
{
    public const TABLE = 'evidence_route_probe';

    public const ACL_SETTING = 'evidence_route_probe_acl';

    public static function log(string $line): void
    {
        file_put_contents('/tmp/evidence-probe.log', date('H:i:s').' '.$line."\n", FILE_APPEND);
    }

    public function getConfig(): array
    {
        return [
            'router' => ['routes' => ['admin' => ['child_routes' => ['evidence-probe' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/evidence-probe/:media-id[/:action]',
                    'constraints' => ['media-id' => '\d+', 'action' => 'raw|show|download'],
                    // Without its own __NAMESPACE__ the route inherits the admin
                    // route's, Omeka\Controller\Admin, and Laminas's
                    // ModuleRouteListener prefixes the controller with it: 404.
                    'defaults' => ['__NAMESPACE__' => 'EvidenceRouteProbe\Controller', 'controller' => 'EvidenceController', 'action' => 'show'],
                ],
            ]]]]],
            'controllers' => ['factories' => [EvidenceController::class => EvidenceControllerFactory::class]],
            'view_manager' => ['template_path_stack' => [__DIR__.'/view']],
        ];
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');
        if (! $acl->hasResource(EvidenceController::class)) {
            self::log('ACL: resource '.EvidenceController::class.' not registered by Omeka; added');
            $acl->addResource(EvidenceController::class);
        }
        if ($services->get('Omeka\Settings')->get(self::ACL_SETTING, 'none') === 'all') {
            $acl->allow(null, EvidenceController::class);
        }
    }

    public function install(ServiceLocatorInterface $services): void
    {
        $services->get('Omeka\Connection')->executeStatement(
            'CREATE TABLE '.self::TABLE.' ('
            .'id INT AUTO_INCREMENT NOT NULL, media_id INT NOT NULL, checked_at DATETIME NOT NULL, '
            .'sha256 CHAR(64) NOT NULL, document LONGTEXT NOT NULL, '
            .'INDEX IDX_EVIDENCE_ROUTE_PROBE_MEDIA (media_id, id), PRIMARY KEY(id), '
            .'CONSTRAINT FK_EVIDENCE_ROUTE_PROBE_MEDIA FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE CASCADE'
            .') DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
        );
    }

    public function uninstall(ServiceLocatorInterface $services): void
    {
        $services->get('Omeka\Connection')->executeStatement('DROP TABLE IF EXISTS '.self::TABLE);
        $services->get('Omeka\Settings')->delete(self::ACL_SETTING);
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach('Omeka\Controller\Admin\Media', 'view.show.sidebar', static function (Event $event): void {
            $view = $event->getTarget();
            $media = $view->vars('media');
            $url = $view->url('admin/evidence-probe', ['media-id' => $media->id(), 'action' => 'download']);
            printf(
                '<div class="evidence-route-probe" data-allowed="%s"><a href="%s">EVIDENCE-PROBE-LINK</a></div>',
                $view->userIsAllowed(EvidenceController::class, 'download') ? 'yes' : 'no',
                $view->escapeHtml($url),
            );
        });
    }

    public function getConfigForm(PhpRenderer $renderer): string
    {
        return '<p>EVIDENCEPROBE-FORM</p><input name="acl"><input name="args"><textarea name="insert"></textarea>';
    }

    public function handleConfigForm(AbstractController $controller): bool
    {
        $post = $controller->getRequest()->getPost()->toArray();
        $services = $this->getServiceLocator();
        if (in_array($post['acl'] ?? '', ['none', 'all'], true)) {
            $services->get('Omeka\Settings')->set(self::ACL_SETTING, $post['acl']);
        }
        if (($post['args'] ?? '') !== '') {
            $services->get('Omeka\Job\Dispatcher')->dispatch(EvidenceJob::class, json_decode((string) $post['args'], true) ?: []);
        }
        if (($post['insert'] ?? '') !== '') {
            // {"media_id": n, "document_b64": "..."}: a crafted document, stored as given
            $insert = json_decode((string) $post['insert'], true);
            $document = (string) base64_decode((string) $insert['document_b64'], true);
            EvidenceJob::insert($services->get('Omeka\Connection'), (int) $insert['media_id'], $document);
        }

        return true;
    }
}
