<?php

namespace CPSIT\AdmiralCloudConnector\Service;

use CPSIT\AdmiralCloudConnector\Api\AdmiralCloudApi;
use CPSIT\AdmiralCloudConnector\Api\AdmiralCloudApiFactory;
use CPSIT\AdmiralCloudConnector\Exception\InvalidArgumentException;
use CPSIT\AdmiralCloudConnector\Exception\InvalidFileConfigurationException;
use CPSIT\AdmiralCloudConnector\Traits\AdmiralCloudStorage;
use CPSIT\AdmiralCloudConnector\Utility\ConfigurationUtility;
use CPSIT\AdmiralCloudConnector\Utility\ImageUtility;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Exception;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

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
class AdmiralCloudService implements SingletonInterface
{
    use AdmiralCloudStorage;

    /**
     * @var AdmiralCloudApi
     */
    protected $admiralCloudApi;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Metadata fields in AdmiralCloud
     *
     * @var array
     */
    protected $metaDataFields = [
        'container_name',
        'container_description',
        'meta*',
        'type'
    ];

    /**
     * AdmiralCloudService constructor.
     */
    public function __construct()
    {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * @param array $settings
     * @return string
     */
    public function getAdmiralCloudAuthCode(array $settings): string
    {
        try {
            return AdmiralCloudApiFactory::auth($settings);
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException('AdmiralCloud Auth Code cannot be created', 1559128418168, $e);
        }
    }

    /**
     * @param array $settings
     * @param string $method
     * @return AdmiralCloudApi
     */
    public function callAdmiralCloudApi(array $settings,string $method = 'post'): AdmiralCloudApi
    {
        try {
            $this->admiralCloudApi = AdmiralCloudApiFactory::create($settings,$method);
            return $this->admiralCloudApi;
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException('AdmiralCloud API cannot be created', 1559128418168, $e);
        }
    }

    /**
     * @param array $identifiers
     * @return string
     */
    public function getMetaData(array $identifiers): array
    {
        $settings = [
            'route' => 'metadata/findBatch',
            'controller' => 'metadata',
            'action' => 'findbatch',
            'payload' => [
                'ids' => array_map(
                    function ($item) {
                        return (int)$item;
                    },
                    $identifiers
                ),
                'title' => $this->metaDataFields,
            ]
        ];

        $fileInfoData = $this->callAdmiralCloudApi($settings)->getData();

        if (!$fileInfoData) {
            $this->logger->error('Empty data received after calling getMetaData of identifiers: '
                . implode(',', $identifiers));

            return [];
        }

        $fileInfo = json_decode($fileInfoData);

        if (!$fileInfo) {
            $this->logger->error(sprintf(
                'Error decoding JSON by getMetaData of identifiers [%s]. Json error code: %d. Json message: %s. Json: %s',
                implode(',', $identifiers),
                json_last_error(),
                json_last_error_msg(),
                $fileInfoData
            ));

            return [];
        }

        $metadata = [];
        foreach ($fileInfo as $file) {
            foreach ($settings['payload']['title'] as $index => $title) {
                $metadata[$file->mediaContainerId][$title] = '';
                if (strtolower($file->title) === strtolower($title)) {
                    $metadata[$file->mediaContainerId][$title] = $file->content;
                    unset($settings['payload']['title'][$index]);
                    break;
                }
            }
        }

        return $metadata;
    }

    /**
     * @param array $identifiers
     * @param int $admiralCloudStorageUid
     * @return array
     * @throws \Exception
     */
    public function getMediaInfo(array $identifiers, int $admiralCloudStorageUid = 0): array
    {
        if (!$admiralCloudStorageUid) {
            $admiralCloudStorageUid = $this->getAdmiralCloudStorage()->getUid();
        }

        $settings = [
            'route' => 'media/findBatch',
            'controller' => 'media',
            'action' => 'findbatch',
            'payload' => [
                'ids' => array_map(
                    function ($item) {
                        return (int)$item;
                    },
                    $identifiers
                )
            ]
        ];

        $fileMetaData = $this->getMetaData($identifiers);

        $fileInfoData = $this->callAdmiralCloudApi($settings)->getData();

        if (!$fileInfoData) {
            $this->logger->error('Empty data received after calling getMediaInfo of identifiers: '
                . implode(',', $identifiers));

            return [];
        }

        $fileInfo = json_decode($fileInfoData);

        if (!$fileInfo) {
            $this->logger->error(sprintf(
                'Error decoding JSON by getMediaInfo of identifiers [%s]. Json error code: %d. Json message: %s. Json: %s',
                implode(',', $identifiers),
                json_last_error(),
                json_last_error_msg(),
                $fileInfoData
            ));

            return [];
        }

        $mediaInfo = [];
        foreach ($fileInfo as $file) {
            $mediaInfo[$file->mediaContainerId] = [
                'type' => $file->type,
                'name' => $file->fileName . '_' . $file->mediaContainerId . '.' . $file->fileExtension,
                'mimetype' => 'admiralCloud/' . $file->type . '/' . $file->fileExtension,
                'storage' => $admiralCloudStorageUid,
                'extension' => $file->fileExtension,
                'size' => $file->fileSize,
                'atime' => (new \DateTime($file->updatedAt))->getTimestamp(),
                'mtime' => (new \DateTime($file->updatedAt))->getTimestamp(),
                'ctime' => (new \DateTime($file->createdAt))->getTimestamp(),
                'identifier' => $file->mediaContainerId,
                'identifier_hash' => sha1($file->mediaContainerId),
                'folder_hash' => sha1('AdmiralCloud' . $admiralCloudStorageUid),
                'alternative' => $fileMetaData[$file->mediaContainerId]['meta_altTag'] ?? '',
                'title' => $fileMetaData[$file->mediaContainerId]['container_name'] ?? '',
                'description' => $fileMetaData[$file->mediaContainerId]['container_description'] ?? '',
                'width' => $file->width,
                'height' => $file->height,
                'copyright' => $fileMetaData[$file->mediaContainerId]['meta_iptc_copyrightNotice'] ?? '',
                'keywords' => '',
            ];
        }

        return $mediaInfo;
    }

    /**
     * @param array $search
     * @return string
     */
    public function getSearch(array $search): array
    {
        $settings = [
            'route' => 'search',
            'controller' => 'search',
            'action' => 'search',
            'payload' => $search
        ];
        return json_decode($this->callAdmiralCloudApi($settings)->getData())->hits->hits ?? [];
    }

    /**
     * @param int $id
     * @return string
     */
    public function getEmbedLinks(int $id): array
    {
        $settings = [
            'route' => 'embedlink/' . $id,
            'controller' => 'embedlink',
            'action' => 'find',
            'payload' => [
                'mediaContainerId' => $id
            ]
        ];
        return json_decode($this->callAdmiralCloudApi($settings,'get')->getData()) ?? [];
    }

    /**
     * Get metadata for AdmiralCloud files which were updated after given date
     *
     * @param \DateTime $lastUpdated
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function getUpdatedMetaData(\DateTime $lastUpdated, int $offset = 0, int $limit = 100): array
    {
        // Prepare payload for AdmiralCloud API
        $payload = [];

        $payload['from'] = $offset;
        $payload['size'] = $limit;
        $payload['noAggregation'] = true;
        $payload['sourceFields'] = $this->metaDataFields;

        $payload['sort'] = [];
        $sort = new \stdClass();
        $sort->updatedAt = 'desc';
        $payload['sort'][] = $sort;

        $payload['query'] = new \stdClass();
        $payload['query']->bool = new \stdClass();
        $payload['query']->bool->filter = [];
        $filter = new \stdClass();
        $filter->range = new \stdClass();
        $filter->range->updatedAt = new \stdClass();
        $filter->range->updatedAt->gte = $lastUpdated->format('Y-m-d');
        $payload['query']->bool->filter[] = $filter;

        $settings = [
            'route' => 'search',
            'controller' => 'search',
            'action' => 'search',
            'payload' => $payload
        ];

        // Make AdmiralCloud API call
        $result = json_decode($this->callAdmiralCloudApi($settings)->getData(), true) ?? [];

        // Get metadata information from result
        $metaDataArray = [];

        if (!empty($result['hits']['hits'])) {
            foreach ($result['hits']['hits'] as $item) {
                $metaDataArray[$item['_id']] = $item['_source'];
            }
        }

        return $metaDataArray;
    }

    /**
     * Make search call to AdmiralCloud to get all metadata for given identifiers
     *
     * @param array $identifiers
     * @return array
     */
    public function searchMetaDataForIdentifiers(array $identifiers): array
    {
        // Prepare payload for AdmiralCloud API
        $payload = [];
        $payload['noAggregation'] = true;
        $payload['sourceFields'] = $this->metaDataFields;

        $payload['query'] = new \stdClass();
        $payload['query']->bool = new \stdClass();
        $payload['query']->bool->filter = [];
        $filter = new \stdClass();
        $filter->terms = new \stdClass();
        $filter->terms->id = $identifiers;
        $payload['query']->bool->filter[] = $filter;

        $settings = [
            'route' => 'search',
            'controller' => 'search',
            'action' => 'search',
            'payload' => $payload
        ];

        // Make AdmiralCloud API call
        $result = json_decode($this->callAdmiralCloudApi($settings)->getData(), true) ?? [];

        // Get metadata information from result
        $metaDataArray = [];

        if (!empty($result['hits']['hits'])) {
            foreach ($result['hits']['hits'] as $item) {
                $metaDataArray[$item['_id']] = $item['_source'];
            }
        }

        if (count($identifiers) !== count($metaDataArray)) {
            $notFound = $identifiers;

            foreach (array_keys($metaDataArray) as $id) {
                $index = array_search($id, $identifiers, false);

                if ($index !== false) {
                    unset($notFound[$index]);
                }
            }

            $this->logger->error(sprintf(
                'Error searching for metadata. Some identifiers were not found in AdmiralCloud. Identifiers: [%s]',
                implode(',', $notFound)
            ));
        }

        return $metaDataArray;
    }

    /**
     * Get public url for AdmiralCloud video
     *
     * @param FileInterface $file
     * @return string
     */
    public function getVideoPublicUrl(FileInterface $file): string
    {
        return $this->getDirectPublicUrlForMedia($file);
    }

    /**
     * Get public url for AdmiralCloud audio
     *
     * @param FileInterface $file
     * @return string
     */
    public function getAudioPublicUrl(FileInterface $file): string
    {
        return $this->getDirectPublicUrlForMedia($file);
    }

    /**
     * Get public url for AdmiralCloud document
     *
     * @param FileInterface $file
     * @return string
     */
    public function getDocumentPublicUrl(FileInterface $file): string
    {
        return $this->getDirectPublicUrlForFile($file);
    }

    /**
     * Get public url for AdmiralCloud player
     *
     * @param FileInterface $file
     * @return string
     */
    public function getPlayerPublicUrl(FileInterface $file): string
    {
        return $this->getPlayerPublicUrlForFile($file);
    }

    /**
     * Get public url for admiral cloud image
     *
     * @param FileInterface $file
     * @param int $width
     * @param int $height
     * @return string
     */
    public function getImagePublicUrl(FileInterface $file, int $width = 0, int $height = 0): string
    {
        if ($file instanceof FileReference) {
            // Save crop information from FileReference and set it in the File object
            $crop = $file->getProperty('tx_admiralcloudconnector_crop');
            $file = $file->getOriginalFile();
            $file->setTxAdmiralCloudConnectorCrop($crop);
        }

        // Get width and height with the correct ratio
        $dimensions = ImageUtility::calculateDimensions(
            $file,
            $width,
            $height,
            (!$width) ? ConfigurationUtility::getDefaultImageWidth() : null
        );

        // Determine, if current file is of AdmiralCloud svg mime type
        $isSvgMimeType = ConfigurationUtility::isSvgMimeType($file->getMimeType());

        // Get image public url
        if (!$isSvgMimeType && $file->getTxAdmiralCloudConnectorCrop()) {
            // With crop information
            $link = ConfigurationUtility::getSmartcropUrl() .'v3/deliverEmbed/'
                . $file->getTxAdmiralCloudConnectorLinkhash()
                . '/image/cropperjsfocus/'
                . $dimensions->width
                . '/'
                . $dimensions->height
                . '/'
                . $file->getTxAdmiralCloudConnectorCropUrlPath()
                . '?poc=true' . (!ConfigurationUtility::isProduction()?'&env=dev':'');
        } else {
            if ($isSvgMimeType) {
                $link = ConfigurationUtility::getImageUrl() . 'v3/deliverEmbed/'
                    . $file->getTxAdmiralCloudConnectorLinkhash()
                    . '/image/';
            } else {
                // Without crop information
                $link = ConfigurationUtility::getSmartcropUrl() . 'v3/deliverEmbed/'
                    . $file->getTxAdmiralCloudConnectorLinkhash()
                    . '/image/autocrop/'
                    . $dimensions->width
                    . '/'
                    . $dimensions->height
                    . '/1?poc=true';
            }
        }

        return $link;
    }

    /**
     * Get public url for file thumbnail
     *
     * @param FileInterface $file
     * @return string
     */
    public function getThumbnailUrl(FileInterface $file): string
    {
        if ($file instanceof FileReference) {
            $file = $file->getOriginalFile();
        }

        return ConfigurationUtility::getThumbnailUrl() . '/v5/deliverEmbed/'
            . $file->getTxAdmiralCloudConnectorLinkhash()
            . '/image/144';
    }

    /**
     * @return \TYPO3\CMS\Core\Resource\ResourceStorage|\TYPO3\CMS\Core\Resource\ResourceStorageInterface
     */
    public function getStorage(){
        return $this->getAdmiralCloudStorage();
    }

    /**
     * @param array $mediaContainer
     * @return string
     * @throws InvalidFileConfigurationException
     */
    public function getLinkHashFromMediaContainer(array $mediaContainer): string
    {
        $links = $mediaContainer['links'] ?? [];

        $linkHash = '';

        // Flag Id for given media container type
        $flagId = ConfigurationUtility::getFlagPlayerConfigId();

        // Player configuration id for given media container type
        switch ($mediaContainer['type']) {
            case 'image':
                $playerConfigurationId = ConfigurationUtility::getImagePlayerConfigId();
                break;
            case 'video':
                $playerConfigurationId = ConfigurationUtility::getVideoPlayerConfigId();
                break;
            case 'audio':
                $playerConfigurationId = ConfigurationUtility::getAudioPlayerConfigId();
                break;
            case 'document':
                $playerConfigurationId = ConfigurationUtility::getDocumentPlayerConfigId();
                break;
            default:
                throw new InvalidFileConfigurationException(
                    'Any valid type was found for file in mediaContainer. Given type: ' . $mediaContainer['type'],
                    111222444580
                );
        }

        // Find link with flag id and player configuration id for given media container
        foreach ($links as $link) {
            if (isset($link['playerConfigurationId']) && isset($link['flag'])
                && $link['playerConfigurationId'] == $playerConfigurationId && $link['flag'] == $flagId) {
                $linkHash = $link['link'];
                break;
            }
        }

        // If there isn't link, it is not possible to obtain the public url
        // Link is required for AdmiralCloud field
        if (!$linkHash) {
            throw new InvalidFileConfigurationException(
                'Any valid hash was found for file in mediaContainer given configuration: ' . json_encode($mediaContainer),
                111222444578
            );
        }

        return $linkHash;
    }

    /**
     * Get direct public url for given hash
     *
     * @param string $hash
     * @param bool $download
     * @param array $mediaContainer
     * @return string
     */
    public function getDirectPublicUrlForHash(string $hash, $mediaContainer = []): string
    {
        return ConfigurationUtility::getDirectFileUrl() . $hash;
    }

    /**
     * Get direct public url for given file
     *
     * @param FileInterface $file
     * @return string
     */
    public function getDirectPublicUrlForFile(FileInterface $file): string
    {
        $enableAcReadableLinks = isset($GLOBALS["TSFE"]->tmpl->setup["config."]["enableAcReadableLinks"])?$GLOBALS["TSFE"]->tmpl->setup["config."]["enableAcReadableLinks"]:false;
        if($enableAcReadableLinks){
            return ConfigurationUtility::getLocalFileUrl() .
                $file->getTxAdmiralCloudConnectorLinkhash() . '/' .
                $file->getIdentifier() . '/' .
                $file->getName();
        } else {
            return ConfigurationUtility::getDirectFileUrl()
                . $file->getTxAdmiralCloudConnectorLinkhash();
        }
    }

    /**
     * Get direct public url for given media file
     *
     * @param FileInterface $file
     * @param bool $download
     * @return string
     */
    protected function getDirectPublicUrlForMedia(FileInterface $file, bool $download = false): string
    {
        return ConfigurationUtility::getDirectFileUrl()
            . $file->getTxAdmiralCloudConnectorLinkhash()
            . ($download ? '?download=true' : '');
    }

    /**
     * Get player public url for given file
     *
     * @param FileInterface $file
     * @return string
     */
    protected function getPlayerPublicUrlForFile(FileInterface $file): string
    {
        return ConfigurationUtility::getPlayerFileUrl() . $file->getTxAdmiralCloudConnectorLinkhash();
    }

    /**
     * @param string $hash
     * @return false|int
     */
    public function addMediaByHash(string $hash){
        $searchData = $this->getSearch(
            [
                "from" => 0,
                "size" => 1,
                "searchTerm" => $hash,
                "field" => "links",
                "noAggregation" => true

            ]
        );
        if($searchData){
            foreach ($searchData as $item) {
                $mediaContainerID = $item->_id;
                $type = $item->_source->type;
                return $this->addMediaByIdHashAndType($mediaContainerID, $hash, $type);
            }
        }
        return false;
    }

    public function addMediaById(array $identifiers)
    {
        $return = [];
        $metaData = $this->searchMetaDataForIdentifiers($identifiers);
        foreach($metaData as $id => $data) {
            $return[$id] = false;
            if (isset($data['type'])) {
                $embedDatas = $this->getEmbedLinks($id);
                $playerConfigurationId = ConfigurationUtility::getPlayerConfigurationIdByType($data['type']);
                foreach ($embedDatas as $embedData) {
                    if ($embedData->playerConfigurationId == $playerConfigurationId) {
                        $fileId = $this->addMediaByIdHashAndType($id,$embedData->link,$embedData->type);
                        $return[$id] = $fileId;
                    }
                }
            }
        }
        return $return;
    }

    public function addMediaByIdHashAndType(string $mediaContainerId,string $linkHash,string $type){
        $return = false;
        try {
            $storage = $this->getAdmiralCloudStorage();
            $indexer = $this->getIndexer($storage);
            // First of all check that the file contain a valid hash in other case an exception would be thrown

            $file = $storage->getFile($mediaContainerId);
            if ($file instanceof File) {
                $file->setTxAdmiralCloudConnectorLinkhash($linkHash);
                $file->setType($type );
                $this->getFileIndexRepository()->add($file);
                // (Re)Fetch metadata
                $indexer->extractMetaData($file);
            }
            $return = $file->getUid();

        } catch (Exception $e) {
            $this->logger->error('Error adding file from AdmiralCloud.', ['exception' => $e]);
        }
        return $return;
    }
}
