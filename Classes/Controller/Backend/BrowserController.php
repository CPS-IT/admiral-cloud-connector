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
use CPSIT\AdmiralCloudConnector\Resource\Index\FileIndexRepository;
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
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\StorageRepository;

#[AsController]
class BrowserController
{
    use AdmiralCloudStorage;

    /**
     * TemplateRootPath
     *
     * @var string[]
     */
    protected array $templateRootPaths = ['EXT:admiral_cloud_connector/Resources/Private/Templates/Browser'];

    /**
     * PartialRootPath
     *
     * @var string[]
     */
    protected array $partialRootPaths = ['EXT:admiral_cloud_connector/Resources/Private/Partials/Browser'];

    /**
     * LayoutRootPath
     *
     * @var string[]
     */
    protected array $layoutRootPaths = ['EXT:admiral_cloud_connector/Resources/Private/Layouts/Browser'];

    public function __construct(
        FileIndexRepository $fileIndexRepository,
        StorageRepository $storageRepository,
        protected readonly AdmiralCloudService $admiralCloudService,
        protected readonly LoggerInterface $logger,
        protected readonly MetadataService $metadataService,
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly UriBuilder $backendUriBuilder,
    ) {
        $this->fileIndexRepository = $fileIndexRepository;
        $this->storageRepository = $storageRepository;
    }

    public function showAction(ServerRequestInterface $request): ResponseInterface
    {
        $credentials = new Credentials();
        $iframeUrl = ConfigurationUtility::getIframeUrl() . 'overview?clientId=' . $credentials->getClientId() . '&cmsOrigin=';

        return $this->prepareIframe($request, $iframeUrl);
    }

    public function uploadAction(ServerRequestInterface $request): ResponseInterface
    {
        $credentials = new Credentials();
        $iframeUrl = ConfigurationUtility::getIframeUrl() . 'upload/files?clientId=' . $credentials->getClientId() . '&cmsOrigin=';

        if (PermissionUtility::userHasPermissionForAdmiralCloud()
            && isset($this->getBackendUser()->getTSConfig()['admiralcloud.']['overrideUploadIframeUrl'])
        ) {
            $iframeUrl = $this->getBackendUser()->getTSConfig()['admiralcloud.']['overrideUploadIframeUrl'];
        }

        return $this->prepareIframe($request, $iframeUrl);
    }

    public function cropAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assignMultiple([
            'mediaContainerId' => $request->getQueryParams()['mediaContainerId'],
            'embedLink' => $request->getQueryParams()['embedLink'],
            'modus' => 'crop',
        ]);

        $credentials = new Credentials();
        $iframeUrl = ConfigurationUtility::getIframeUrl() . 'overview?clientId=' . $credentials->getClientId() . '&cmsOrigin=';

        return $this->prepareIframe($request, $iframeUrl, $moduleTemplate);
    }

    public function rteLinkAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assign('modus', 'rte-link');

        $credentials = new Credentials();
        $iframeUrl = ConfigurationUtility::getIframeUrl() . 'overview?clientId=' . $credentials->getClientId() . '&cmsOrigin=';

        return $this->prepareIframe($request, $iframeUrl, $moduleTemplate);
    }

    protected function prepareIframe(
        ServerRequestInterface $request,
        string $callbackUrl,
        ?ModuleTemplate $moduleTemplate = null,
    ): ResponseInterface {
        $parameters = $request->getQueryParams();
        $moduleTemplate ??= $this->moduleTemplateFactory->create($request);
        $protocol = 'http';

        if ((isset($_SERVER['HTTP_HTTPS']) && $_SERVER['HTTP_HTTPS'] === 'on')
            || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        ) {
            $protocol = 'https';
        }

        $moduleTemplate->assignMultiple([
            'iframeHost' => rtrim(ConfigurationUtility::getIframeUrl(), '/'),
            'ajaxUrl' => (string)$this->backendUriBuilder->buildUriFromRoute('ajax_admiral_cloud_browser_auth'),
            'iframeUrl' => $callbackUrl . base64_encode($protocol . '://' . $_SERVER['HTTP_HOST']),
            'parameters' => [
                'element' => $parameters['element'] ?? null,
                'irreObject' => $parameters['irreObject'] ?? null,
            ],
        ]);

        return $moduleTemplate->renderResponse('Backend/Browser/Show');
    }

    /**
     * Makes the AJAX call to expand or collapse the foldertree.
     * Called by an AJAX Route, see AjaxRequestHandler
     */
    public function authAction(ServerRequestInterface $request): ResponseInterface
    {
        $bodyParams = json_decode($request->getBody()->getContents());
        $settings = [
            'callbackUrl' => $bodyParams->callbackUrl,
            'controller' => 'loginapp',
            'action' => 'login',
            'device' => $bodyParams->device,
        ];

        try {
            $admiralCloudAuthCode = $this->admiralCloudService->getAdmiralCloudAuthCode($settings);

            return $this->createJsonResponse(
                [
                    'code' => $admiralCloudAuthCode,
                ],
                200,
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
        $media = $request->getParsedBody()['media'];
        $target = $request->getParsedBody()['target'];

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
        $media = $request->getParsedBody()['media'];

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
                    'publicUrl' => 't3://file?uid=' . $file->getUid(),
                ],
                200,
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
        $media = $request->getParsedBody()['media'];
        $target = $request->getParsedBody()['target'];
        $cropperData = $media['cropperData'];
        unset($cropperData['smartCropperUrl'], $cropperData['smartCropperUrlAOI']);
        $cropperData = json_encode($cropperData);

        try {
            $storage = $this->getAdmiralCloudStorage();
            $mediaContainer = $media['mediaContainer'];
            /** @var \CPSIT\AdmiralCloudConnector\Resource\File $file */
            $file = $storage->getFile($mediaContainer['id']);
            $file->setTxAdmiralCloudConnectorCrop($cropperData);
            $link = $this->admiralCloudService->getImagePublicUrl($file, 226, 150);

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
        if (!empty($media['cropperData'])) {
            $cropperData = $media['cropperData'];
            unset($cropperData['smartCropperUrl'], $cropperData['smartCropperUrlAOI']);

            $sessionData = $this->getBackendUser()->getSessionData('admiralCloud') ?? [];
            $sessionData['cropInformation'][$file->getUid()] = $cropperData;

            $this->getBackendUser()->setAndSaveSessionData('admiralCloud', $sessionData);
        }
    }

    protected function createJsonResponse(array $data, int $statusCode): ResponseInterface
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
