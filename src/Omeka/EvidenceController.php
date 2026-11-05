<?php

declare(strict_types=1);

namespace ContentCredentials\Omeka;

use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Omeka\Mvc\Controller\Plugin\Api;
use Omeka\Mvc\Exception\NotFoundException;
use UnexpectedValueException;

/**
 * The evidence pages (SPEC-006, ADR-0005 point 8). Every action reads the
 * media item through Omeka's API before it serves anything: whoever cannot
 * see the media item gets the 404 that read throws, as on the media page
 * (step 24).
 *
 * @phpstan-import-type EvidenceRow from EvidenceReader
 */
final class EvidenceController extends AbstractActionController
{
    public const MEDIA_ROUTE = 'admin/content-credentials-media-evidence';

    public const ROUTE = 'admin/content-credentials-evidence';

    public function __construct(private readonly EvidenceReader $reader) {}

    public function browseAction(): ViewModel
    {
        $mediaId = $this->number('media-id');
        $this->readMedia($mediaId);
        $view = new ViewModel(['mediaId' => $mediaId, 'rows' => $this->reader->rows($mediaId)]);
        $view->setTemplate('content-credentials/evidence/browse');

        return $view;
    }

    public function showAction(): ViewModel
    {
        $view = new ViewModel(['row' => $this->visibleRow()]);
        $view->setTemplate('content-credentials/evidence/show');

        return $view;
    }

    public function downloadAction(): Response
    {
        $row = $this->visibleRow();
        $response = $this->getResponse();
        if (! $response instanceof Response) {
            throw new UnexpectedValueException('Not an HTTP response.');
        }
        $response->setContent($row['document']);
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/json')
            ->addHeaderLine('Content-Disposition', sprintf('attachment; filename="media-%d-evidence-%d.json"', $row['media_id'], $row['id']))
            ->addHeaderLine('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    /** @return EvidenceRow */
    private function visibleRow(): array
    {
        $row = $this->reader->row($this->number('id'));
        if ($row === null) {
            throw new NotFoundException;
        }
        $this->readMedia($row['media_id']);

        return $row;
    }

    /** Throws Omeka's not-found exception when this user cannot see the media item. */
    private function readMedia(int $mediaId): void
    {
        $api = $this->plugin('api');
        if (! $api instanceof Api) {
            throw new UnexpectedValueException('Omeka S did not provide its API plugin.');
        }
        $api->read('media', $mediaId);
    }

    private function number(string $parameter): int
    {
        $value = $this->getEvent()->getRouteMatch()?->getParam($parameter);

        return is_numeric($value) ? (int) $value : 0;
    }
}
