<?php

namespace CPSIT\AdmiralCloudConnector\Controller\Backend;

use CPSIT\AdmiralCloudConnector\Api\Oauth\Credentials;
use CPSIT\AdmiralCloudConnector\Resource\Index\FileIndexRepository;
use CPSIT\AdmiralCloudConnector\Service\AdmiralCloudService;
use CPSIT\AdmiralCloudConnector\Service\MetadataService;
use CPSIT\AdmiralCloudConnector\Traits\AdmiralCloudStorage;
use CPSIT\AdmiralCloudConnector\Utility\ConfigurationUtility;
use CPSIT\AdmiralCloudConnector\Utility\PermissionUtility;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Index\Indexer;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Form\Controller\AbstractBackendController;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Extbase\Mvc\Response;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2020
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
class BrowserController extends AbstractBackendController
{
    use AdmiralCloudStorage;

    /**
     * Fluid Standalone View
     *
     * @var StandaloneView
     */
    protected $view;

    /**
     * TemplateRootPath
     *
     * @var string[]
     */
    protected $templateRootPaths = ['EXT:admiral_cloud_connector/Resources/Private/Templates/Browser'];

    /**
     * PartialRootPath
     *
     * @var string[]
     */
    protected $partialRootPaths = ['EXT:admiral_cloud_connector/Resources/Private/Partials/Browser'];

    /**
     * LayoutRootPath
     *
     * @var string[]
     */
    protected $layoutRootPaths = ['EXT:admiral_cloud_connector/Resources/Private/Layouts/Browser'];

    /**
     * AdmiralCloud service
     *
     * @var AdmiralCloudService
     */
    protected $admiralCloudService = null;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * ModuleTemplateFactory object
     *
     * @var ModuleTemplateFactory
     */
    protected $moduleTemplateFactory;


    /**
     * CompactViewController constructor.
     */
    public function __construct()
    {
        $this->admiralCloudService = GeneralUtility::makeInstance(AdmiralCloudService::class);
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $this->moduleTemplateFactory = GeneralUtility::makeInstance(ModuleTemplateFactory::class);
    }

    /**
     * @param ServerRequestInterface|null $request
     * @return ResponseInterface
     */
    public function showAction(ServerRequestInterface $request = NULL): ResponseInterface
    {
        $credentials = new Credentials();
        return $this->prepareIframe($request,ConfigurationUtility::getIframeUrl() . 'overview?clientId=' . $credentials->getClientId() . '&cmsOrigin=');
    }

    /**
     * @param ServerRequestInterface|null $request
     * @param ResponseInterface|null $response
     * @return ResponseInterface
     */
    public function uploadAction(ServerRequestInterface $request = NULL): ResponseInterface
    {
        $credentials = new Credentials();
        $iframeUrl = ConfigurationUtility::getIframeUrl() . 'upload/files?clientId=' . $credentials->getClientId() . '&cmsOrigin=';
        if (PermissionUtility::userHasPermissionForAdmiralCloud()) {
            if ($this->getBackendUser() && isset($this->getBackendUser()->getTSConfig()['admiralcloud.']['overrideUploadIframeUrl'])) {
                $iframeUrl = $this->getBackendUser()->getTSConfig()['admiralcloud.']['overrideUploadIframeUrl'];
            }
        }
        return $this->prepareIframe($request,$iframeUrl);
    }

    /**
     * @param ServerRequestInterface|null $request
     * @param ResponseInterface|null $response
     * @return ResponseInterface
     */
    public function cropAction(ServerRequestInterface $request = NULL): ResponseInterface
    {
        $credentials = new Credentials();
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assignMultiple([
            'mediaContainerId' => $request->getQueryParams()['mediaContainerId'],
            'embedLink' => $request->getQueryParams()['embedLink'],
            'modus' => 'crop'
        ]);
        return $this->prepareIframe($request,ConfigurationUtility::getIframeUrl() . 'overview?clientId=' . $credentials->getClientId() . '&cmsOrigin=', $moduleTemplate);
    }

    /**
     * @param ServerRequestInterface|null $request
     * @param ResponseInterface|null $response
     * @return ResponseInterface
     */
    public function rteLinkAction(ServerRequestInterface $request = NULL): ResponseInterface
    {
        $credentials = new Credentials();
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assign('modus', 'rte-link');

        return $this->prepareIframe($request,ConfigurationUtility::getIframeUrl() . 'overview?clientId=' . $credentials->getClientId() . '&cmsOrigin=', $moduleTemplate);
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $callbackUrl
     * @param ModuleTemplate $moduleTemplate
     * @return ResponseInterface
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    public function prepareIframe(ServerRequestInterface $request,string $callbackUrl, $moduleTemplate = false){
        $parameters = $request->getQueryParams();
        $moduleTemplate = $moduleTemplate ? $moduleTemplate : $this->moduleTemplateFactory->create($request);

        $protocol = 'http';
        if ((isset($_SERVER['HTTP_HTTPS']) && $_SERVER['HTTP_HTTPS'] === 'on')
            || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')) {
            $protocol = 'https';
        }
        /** @var \TYPO3\CMS\Backend\Routing\UriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Routing\UriBuilder::class);
        $path = $uriBuilder->buildUriFromRoute('ajax_admiral_cloud_browser_auth');
        $moduleTemplate->assignMultiple([
            'iframeHost' => rtrim(ConfigurationUtility::getIframeUrl(),'/'),
            'ajaxUrl' => (string)$path,
            'iframeUrl' => $callbackUrl . base64_encode($protocol .'://' . $_SERVER['HTTP_HOST']),
            'parameters' => [
                'element' => $parameters['element'] ?? null,
                'irreObject' => $parameters['irreObject'] ?? null,
            ]
        ]);

        return $moduleTemplate->renderResponse('Backend/Browser/Show');
    }

    /**
     * Makes the AJAX call to expand or collapse the foldertree.
     * Called by an AJAX Route, see AjaxRequestHandler
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function authAction(ServerRequestInterface $request): ResponseInterface
    {
        $bodyParams = json_decode($request->getBody()->getContents());
        $settings = [
            'callbackUrl' => $bodyParams->callbackUrl,
            'controller' => 'loginapp',
            'action' => 'login',
            'device' => $bodyParams->device
        ];

        try {
            $admiralCloudAuthCode = $this->admiralCloudService->getAdmiralCloudAuthCode($settings);
            return $this->createJsonResponse(
                [
                    'code' => $admiralCloudAuthCode
                ],
                200
            );
        } catch (\Throwable $exception) {
            $this->logger->error('The authentication to AdmiralCloud was not possible.', ['exception' => $exception]);
            return $this->createJsonResponse( [
                'error' => 'Error information: ' . $exception->getMessage(),
                'exception' => [
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage()
                ],
            ], 500);
        }
    }

    /**
     * Action: Retrieve file from storage
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
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
            $linkHash = $this->admiralCloudService->getLinkHashFromMediaContainer($mediaContainer, (isset($cropperData['usePNG'])?$cropperData['usePNG'] == "true":"false"));

            $file = $storage->getFile((string)$mediaContainer['id']);

            if ($file instanceof File) {
                $file->setTxAdmiralCloudConnectorLinkhash($linkHash);
                $file->setTypeFromMimeType($mediaContainer['type'] . '/' . $mediaContainer['fileExtension']);
                #$this->eventDispatcher = GeneralUtility::getContainer()->get(EventDispatcherInterface::class);
                if(!$file->getProperty('extension')){
                    $properties = [
                        'mime_type' => 'admiralCloud' . '/' . $mediaContainer['type'] . '/' . $mediaContainer['fileExtension'],
                        'extension' => $mediaContainer['fileExtension']
                    ];
                    $file->updateProperties($properties);
                }
                $this->getFileIndexRepository($storage)->add($file);
                #$indexer->updateIndexEntry($file);

                // (Re)Fetch metadata
                $indexer->extractMetaData($file);
                $metadataService = GeneralUtility::makeInstance(MetadataService::class);
                $metadataService->updateMetadataForAdmiralCloudFile($file->getUid(),$mediaContainer);

                $this->storeInSessionCropInformation($file, $media);

                $files[] = $file->getUid();
            }

            if ($files === []) {
                return $this->createJsonResponse( ['error' => 'No files given/found'], 406);
            }

            return $this->createJsonResponse( ['files' => $files], 201);
        } catch (Exception $e) {
            $this->logger->error('Error adding file from AdmiralCloud.', ['exception' => $e]);
            return $this->createJsonResponse( [
                'error' => 'The interaction with AdmiralCloud contained conflicts. Please contact the webmasters.',
                'exception' => [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ],
            ], 404);
        }
    }

    /**
     * Action: Retrieve file from storage
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function getMediaPublicUrlAction(ServerRequestInterface $request): ResponseInterface
    {
        $media = $request->getParsedBody()['media'];
        $rteLinkDownload = $request->getParsedBody()['rteLinkDownload'];

        if (!is_bool($rteLinkDownload)) {
            $rteLinkDownload = $rteLinkDownload === 'true';
        }

        try {
            $mediaContainer = $media['mediaContainer'];
            $cropperData = $media['cropperData'];

            // Get link hash for media container
            $linkHash = $this->admiralCloudService->getLinkHashFromMediaContainer($mediaContainer, (isset($cropperData['usePNG'])?$cropperData['usePNG'] == "true":"false"));

            /** @var AdmiralCloudService $admiralCloudService */
            $admiralCloudService = GeneralUtility::makeInstance(AdmiralCloudService::class);
            $admiralCloudService->addMediaByIdHashAndType($mediaContainer['id'],$linkHash,$mediaContainer['type']);
            $file = $this->getAdmiralCloudStorage()->getFile($mediaContainer['id']);

            return $this->createJsonResponse( [
                'publicUrl' => 't3://file?uid=' . $file->getUid()
            ], 200);
        } catch (Exception $e) {
            $this->logger->error('Error adding file from AdmiralCloud.', ['exception' => $e]);
            return $this->createJsonResponse( [
                'error' => 'The interaction with AdmiralCloud contained conflicts. Please contact the webmasters.',
                'exception' => [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ],
            ], 404);
        }
    }

    /**
     * Action: Retrieve file from storage
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
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
            $file = $storage->getFile($mediaContainer['id']);
            $file->setTxAdmiralCloudConnectorCrop($cropperData);
            $link = $this->admiralCloudService->getImagePublicUrl($file,226,150);

            return $this->createJsonResponse( ['target' => $target,'cropperData' => $cropperData,'link' => $link], 201);
        } catch (Exception $e) {
            $this->logger->error('Error cropping file from AdmiralCloud.', ['exception' => $e]);
            return $this->createJsonResponse( [
                'error' => 'The interaction with AdmiralCloud contained conflicts. Please contact the webmasters.',
                'exception' => [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage()
                ],
            ], 404);
        }
    }

    /**
     * Store in BE session the crop information for given file
     *
     * @param FileInterface $file
     * @param array $media
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

    /**
     * @param array|null $data
     * @param int $statusCode
     * @return ResponseInterface
     */
    protected function createJsonResponse( $data, int $statusCode): ResponseInterface
    {

        $jsonArray = [];

        if (!empty($data)) {
            $options = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
            $jsonArray = $data;
        }
        return new JsonResponse($jsonArray,$statusCode);
    }

    /**
     * Returns the current BE user.
     *
     * @return \TYPO3\CMS\Core\Authentication\BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

}
