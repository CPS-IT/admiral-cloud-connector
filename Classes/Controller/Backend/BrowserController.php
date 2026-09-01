<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "admiral_cloud_connector".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace CPSIT\AdmiralCloudConnector\Controller\Backend;

use CPSIT\AdmiralCloudConnector\Api\Oauth\Credentials;
use CPSIT\AdmiralCloudConnector\Resource\File;
use CPSIT\AdmiralCloudConnector\Service\AdmiralCloudService;
use CPSIT\AdmiralCloudConnector\Service\MetadataService;
use CPSIT\AdmiralCloudConnector\Traits\AdmiralCloudStorage;
use CPSIT\AdmiralCloudConnector\Utility\ConfigurationUtility;
use CPSIT\AdmiralCloudConnector\Utility\PermissionUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Index\FileIndexRepository;
use TYPO3\CMS\Core\Resource\StorageRepository;

/**
 * BrowserController
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 *
 * @phpstan-type MediaPayload array{
 *     mediaContainer: array<string, mixed>,
 *     cropperData: array<string, mixed>,
 * }
 */
#[AsController]
class BrowserController
{
    use AdmiralCloudStorage;

    public function __construct(
        FileIndexRepository $fileIndexRepository,
        StorageRepository $storageRepository,
        protected readonly AdmiralCloudService $admiralCloudService,
        protected readonly LoggerInterface $logger,
        protected readonly MetadataService $metadataService,
        protected readonly UriBuilder $backendUriBuilder,
    ) {
        $this->fileIndexRepository = $fileIndexRepository;
        $this->storageRepository = $storageRepository;
    }

    public function showAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->prepareIframe($request, '/overview');
    }

    public function uploadAction(ServerRequestInterface $request): ResponseInterface
    {
        $callback = $this->getBackendUser()->getTSConfig()['admiralcloud.']['overrideUploadIframeUrl'] ?? null;

        if ($callback === null || !PermissionUtility::userHasPermissionForAdmiralCloud()) {
            $callback = '/upload/files';
        }

        return $this->prepareIframe($request, $callback);
    }

    public function cropAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->prepareIframe(
            $request,
            '/overview',
            [
                'mediaContainerId' => $request->getQueryParams()['mediaContainerId'],
                'embedLink' => $request->getQueryParams()['embedLink'],
                'modus' => 'crop',
            ],
        );
    }

    public function rteLinkAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->prepareIframe($request, '/overview', ['modus' => 'rte-link']);
    }

    /**
     * Build the JSON payload the merged AdmiralCloud browser JavaScript module needs to
     * (re)initialize the persistent AdmiralCloud iframe for a given action.
     */
    protected function prepareIframe(
        ServerRequestInterface $request,
        string $callback,
        array $extra = [],
    ): ResponseInterface {
        $parameters = $request->getQueryParams();

        return $this->createJsonResponse(
            [
                'ajaxUrl' => (string)$this->backendUriBuilder->buildUriFromRoute('ajax_admiral_cloud_browser_auth'),
                'iframeUrl' => $this->buildCallbackUrl($callback, $request),
                'irreObject' => $parameters['irreObject'] ?? null,
                ...$extra,
            ],
        );
    }

    protected function buildCallbackUrl(string $route, ServerRequestInterface $request): string
    {
        /** @var NormalizedParams $normalizedParams */
        $normalizedParams = $request->getAttribute('normalizedParams');

        if (str_contains($route, '://')) {
            $iframeUrl = new Uri($route);
            parse_str($iframeUrl->getQuery(), $queryParams);
        } else {
            $iframeUrl = (new Uri(ConfigurationUtility::getIframeUrl()))->withPath($route);
            $queryParams['clientId'] = (new Credentials())->getClientId();
        }

        $queryParams['cmsOrigin'] = base64_encode($normalizedParams->getRequestHost());

        return (string)$iframeUrl->withQuery(http_build_query($queryParams));
    }

    /**
     * Makes the AJAX call to expand or collapse the foldertree.
     * Called by an AJAX Route, see AjaxRequestHandler
     */
    public function authAction(ServerRequestInterface $request): ResponseInterface
    {
        $bodyParams = json_decode($request->getBody()->getContents());

        try {
            $admiralCloudAuthCode = $this->admiralCloudService->getAdmiralCloudAuthCode(
                $bodyParams->callbackUrl,
                $bodyParams->device,
            );

            return $this->createJsonResponse(
                [
                    'code' => $admiralCloudAuthCode,
                ],
            );
        } catch (\Throwable $exception) {
            $this->logger->error('The authentication to AdmiralCloud was not possible.', ['exception' => $exception]);

            return $this->createJsonResponse(
                [
                    'error' => 'Error information: ' . $exception->getMessage(),
                    'exception' => [
                        'code' => $exception->getCode(),
                        'message' => $exception->getMessage(),
                    ],
                ],
                500,
            );
        }
    }

    /**
     * Action: Retrieve file from storage
     */
    public function getFilesAction(ServerRequestInterface $request): ResponseInterface
    {
        /** @var array{media: MediaPayload} $parsedBody */
        $parsedBody = $request->getParsedBody();
        $media = $parsedBody['media'];

        try {
            $files = [];
            $storage = $this->getAdmiralCloudStorage();
            $indexer = $this->getIndexer($storage);
            $mediaContainer = $media['mediaContainer'];
            $cropperData = $media['cropperData'];

            // First of all check that the file contain a valid hash in other case an exception would be thrown
            $linkHash = $this->admiralCloudService->getLinkHashFromMediaContainer(
                $mediaContainer,
                ($cropperData['usePNG'] ?? null) === 'true',
            );

            $file = $storage->getFile((string)$mediaContainer['id']);

            if ($file instanceof File) {
                $file->setTxAdmiralCloudConnectorLinkhash($linkHash);
                $file->setTypeFromMimeType($mediaContainer['type'] . '/' . $mediaContainer['fileExtension']);

                if (!$file->getProperty('extension')) {
                    $file->updateProperties([
                        'mime_type' => 'admiralCloud' . '/' . $mediaContainer['type'] . '/' . $mediaContainer['fileExtension'],
                        'extension' => $mediaContainer['fileExtension'],
                    ]);
                }

                $this->getFileIndexRepository()->add($file);

                // (Re)Fetch metadata
                $indexer->extractMetaData($file);
                $this->metadataService->updateMetadataForAdmiralCloudFile($file->getUid(), $mediaContainer);

                $this->storeInSessionCropInformation($file, $media);

                $files[] = $file->getUid();
            }

            if ($files === []) {
                return $this->createJsonResponse(['error' => 'No files given/found'], 406);
            }

            return $this->createJsonResponse(['files' => $files], 201);
        } catch (\Exception $e) {
            $this->logger->error('Error adding file from AdmiralCloud.', ['exception' => $e]);

            return $this->createJsonResponse(
                [
                    'error' => 'The interaction with AdmiralCloud contained conflicts. Please contact the webmasters.',
                    'exception' => [
                        'code' => $e->getCode(),
                        'message' => $e->getMessage(),
                    ],
                ],
                404,
            );
        }
    }

    /**
     * Action: Retrieve file from storage
     */
    public function getMediaPublicUrlAction(ServerRequestInterface $request): ResponseInterface
    {
        /** @var array{media: MediaPayload} $parsedBody */
        $parsedBody = $request->getParsedBody();
        $media = $parsedBody['media'];

        try {
            $mediaContainer = $media['mediaContainer'];
            $cropperData = $media['cropperData'];

            // Get link hash for media container
            $linkHash = $this->admiralCloudService->getLinkHashFromMediaContainer(
                $mediaContainer,
                ($cropperData['usePNG'] ?? null) === 'true',
            );

            $this->admiralCloudService->addMediaByIdHashAndType($mediaContainer['id'], $linkHash, $mediaContainer['type']);
            $file = $this->getAdmiralCloudStorage()->getFile($mediaContainer['id']);

            return $this->createJsonResponse(
                [
                    'publicUrl' => 't3://file?uid=' . $file?->getUid(),
                ],
            );
        } catch (\Exception $e) {
            $this->logger->error('Error adding file from AdmiralCloud.', ['exception' => $e]);

            return $this->createJsonResponse(
                [
                    'error' => 'The interaction with AdmiralCloud contained conflicts. Please contact the webmasters.',
                    'exception' => [
                        'code' => $e->getCode(),
                        'message' => $e->getMessage(),
                    ],
                ],
                404,
            );
        }
    }

    /**
     * Action: Retrieve file from storage
     */
    public function cropFileAction(ServerRequestInterface $request): ResponseInterface
    {
        /** @var array{media: MediaPayload, target: string} $parsedBody */
        $parsedBody = $request->getParsedBody();
        $media = $parsedBody['media'];
        $target = $parsedBody['target'];
        $cropperData = $media['cropperData'];
        unset($cropperData['smartCropperUrl'], $cropperData['smartCropperUrlAOI']);
        $cropperData = json_encode($cropperData) ?: null;

        try {
            $storage = $this->getAdmiralCloudStorage();
            $mediaContainer = $media['mediaContainer'];
            /** @var File $file */
            $file = $storage->getFile($mediaContainer['id']);
            $file->setTxAdmiralCloudConnectorCrop($cropperData);
            $link = $this->admiralCloudService->getImagePublicUrl($file, maxHeight: 150);

            return $this->createJsonResponse(
                [
                    'target' => $target,
                    'cropperData' => $cropperData,
                    'link' => $link,
                ],
                201,
            );
        } catch (\Exception $e) {
            $this->logger->error('Error cropping file from AdmiralCloud.', ['exception' => $e]);

            return $this->createJsonResponse(
                [
                    'error' => 'The interaction with AdmiralCloud contained conflicts. Please contact the webmasters.',
                    'exception' => [
                        'code' => $e->getCode(),
                        'message' => $e->getMessage(),
                    ],
                ],
                404,
            );
        }
    }

    /**
     * Store in BE session the crop information for given file
     */
    protected function storeInSessionCropInformation(FileInterface $file, array $media): void
    {
        if (is_array($media['cropperData']) && $media['cropperData'] !== [] && $file instanceof AbstractFile) {
            $cropperData = $media['cropperData'];
            unset($cropperData['smartCropperUrl'], $cropperData['smartCropperUrlAOI']);

            $sessionData = $this->getBackendUser()->getSessionData('admiralCloud') ?? [];
            $sessionData['cropInformation'][$file->getUid()] = $cropperData;

            $this->getBackendUser()->setAndSaveSessionData('admiralCloud', $sessionData);
        }
    }

    protected function createJsonResponse(array $data, int $statusCode = 200): ResponseInterface
    {
        return new JsonResponse(
            $data,
            $statusCode,
            [],
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES,
        );
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
