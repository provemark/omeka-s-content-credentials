<?php

declare(strict_types=1);

namespace ContentCredentials;

use ContentCredentials\Display\RecheckSection;
use ContentCredentials\Omeka\AdminDisplay;
use ContentCredentials\Omeka\AfterFlushListener;
use ContentCredentials\Omeka\RecheckRuns;
use ContentCredentials\Omeka\Schema;
use ContentCredentials\Omeka\TrustConfig;
use Doctrine\DBAL\Connection;
use Laminas\EventManager\EventInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Http\Request;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\Parameters;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Permissions\Acl;
use Omeka\Settings\Settings;

/**
 * The module's entry point for Omeka S.
 *
 * Omeka S finds this class as "<module directory>\Module", so the namespace
 * must equal the directory name, ContentCredentials. It autoloads src/ under
 * that namespace itself; this file loads nothing else.
 *
 * Installing refuses PHP below 8.3 with a message on the modules page
 * (SPEC-001; module.ini cannot state a PHP version), and creates the
 * verdict table; uninstalling drops it (SPEC-002, ADR-0003). Every new
 * media item is handed to the after-flush listener, which verifies JPEG,
 * PNG and WebP originals once they are committed (SPEC-002). The admin
 * media page, its Details panel, the browse column and the browse filter
 * show the verdict (SPEC-003). The configure page holds the trust settings
 * every check uses (SPEC-004); uninstalling removes them. The same page
 * starts a run over existing media and shows the latest (SPEC-005). Every
 * check keeps its evidence; the admin pages that show it are open to every
 * role, and read the media item first, so access follows the media
 * (SPEC-006, ADR-0005).
 *
 * Keep this file parseable on PHP 8.1: Omeka S 4.2 runs from 8.1 and loads it
 * before the module can say it needs 8.3 (ADR-0001).
 */
final class Module extends AbstractModule
{
    private ?AfterFlushListener $afterFlush = null;

    private ?AdminDisplay $adminDisplay = null;

    /** Custom settings submitted and refused in this request (SPEC-004 AC5). */
    private ?string $refused = null;

    /**
     * @return array<mixed>
     */
    public function getConfig(): array
    {
        $config = require __DIR__.'/config/module.config.php';
        if (! is_array($config)) {
            throw new \UnexpectedValueException('config/module.config.php must return an array.');
        }

        return $config;
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);
        // The limit is the controller's read of the media item (SPEC-006 AC10).
        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        if ($acl instanceof Acl) {
            $acl->allow(null, 'ContentCredentials\Omeka\EvidenceController');
        }
    }

    public function install(ServiceLocatorInterface $serviceLocator): void
    {
        // A module that is not installed is not loaded by Omeka S, so its src/
        // is not on the autoloader yet while install() runs (measured, step 07).
        require_once __DIR__.'/src/Requirements.php';
        $problem = Requirements::phpProblem(PHP_VERSION);
        if ($problem !== null) {
            throw new ModuleCannotInstallException($problem);
        }
        require_once __DIR__.'/src/Omeka/Schema.php';
        foreach (Schema::create() as $statement) {
            self::connection($serviceLocator)->executeStatement($statement);
        }
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator): void
    {
        // An inactive module's src/ is not autoloaded either.
        require_once __DIR__.'/src/Omeka/Schema.php';
        // The job rows first: a run still going ends at its next look at its row,
        // before it could write into the tables of a later install (SPEC-008).
        self::connection($serviceLocator)->executeStatement(Schema::DELETE_JOBS, [Schema::JOB_CLASS]);
        self::connection($serviceLocator)->executeStatement(Schema::drop());
        // Omeka S leaves a module's settings behind (step 15); the keys are
        // those of SPEC-004, written out because src/ is not loaded here.
        $settings = $serviceLocator->get('Omeka\Settings');
        if ($settings instanceof Settings) {
            $settings->delete('content_credentials_digicert');
            $settings->delete('content_credentials_custom_settings');
        }
    }

    public function getConfigForm(PhpRenderer $renderer): string
    {
        return TrustConfig::create($this->getServiceLocator(), __DIR__)->form($renderer, $this->refused)
            .RecheckRuns::create($this->getServiceLocator())->html($renderer);
    }

    public function handleConfigForm(AbstractController $controller): bool
    {
        $request = $controller->getRequest();
        $post = $request instanceof Request ? $request->getPost() : null;
        $fields = $post instanceof Parameters ? $post->toArray() : [];
        // Marking a run as ended: nothing else, and nothing saved (SPEC-005 AC14).
        if (array_key_exists(RecheckSection::END_FIELD, $fields)) {
            $problem = RecheckRuns::create($this->getServiceLocator())->end($fields[RecheckSection::END_FIELD]);
            if ($problem === null) {
                (new Messenger)->addSuccess('The run was marked as ended.');
            } else {
                (new Messenger)->addError($problem);
            }

            return true;
        }
        // A start button: start a run, and save nothing (SPEC-005 AC11).
        $start = $fields['content_credentials_recheck'] ?? null;
        if ($start !== null) {
            $problem = RecheckRuns::create($this->getServiceLocator())->start(is_string($start) ? $start : '');
            if ($problem === null) {
                (new Messenger)->addSuccess("Checking existing media has started. Its status is on this module's configure page.");
            } else {
                (new Messenger)->addError($problem);
            }

            return true;
        }
        $refusal = TrustConfig::create($this->getServiceLocator(), __DIR__)->save($fields);
        if ($refusal === null) {
            return true;
        }
        $custom = $fields['content_credentials_custom_settings'] ?? '';
        $this->refused = is_string($custom) ? $custom : '';
        // Plain text: Omeka's messages() escapes it (step 15).
        (new Messenger)->addError('These trust settings were not saved: '.$refusal);

        return false;
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach('Omeka\Entity\Media', 'entity.persist.post', function (EventInterface $event): void {
            try {
                $this->afterFlush ??= AfterFlushListener::create($this->getServiceLocator(), __DIR__);
                $this->afterFlush->note($event->getTarget());
            } catch (\Throwable $e) {
                // Inside Doctrine's flush: nothing may undo the upload (SPEC-002).
            }
        });

        $adminMedia = 'Omeka\Controller\Admin\Media';
        $display = function (string $method): \Closure {
            return function (EventInterface $event) use ($method): void {
                $this->adminDisplay ??= AdminDisplay::create($this->getServiceLocator());
                $this->adminDisplay->{$method}($event);
            };
        };
        $sharedEventManager->attach($adminMedia, 'view.show.sidebar', $display('sidebar'));
        $sharedEventManager->attach($adminMedia, 'view.details', $display('details'));
        $sharedEventManager->attach($adminMedia, 'view.advanced_search', $display('advancedSearch'));
        $sharedEventManager->attach($adminMedia, 'view.search.filters', $display('searchFilters'));
        $sharedEventManager->attach('Omeka\Api\Adapter\MediaAdapter', 'api.search.query', $display('searchQuery'));
    }

    private static function connection(ServiceLocatorInterface $serviceLocator): Connection
    {
        $connection = $serviceLocator->get('Omeka\Connection');
        if (! $connection instanceof Connection) {
            throw new ModuleCannotInstallException('Omeka S did not provide its database connection.');
        }

        return $connection;
    }
}
