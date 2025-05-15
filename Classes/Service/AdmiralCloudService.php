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

namespace CPSIT\AdmiralCloudConnector\Service;

use CPSIT\AdmiralCloudConnector\Api\AdmiralCloudApi;
use CPSIT\AdmiralCloudConnector\Api\AdmiralCloudApiFactory;
use CPSIT\AdmiralCloudConnector\Api\Oauth\Credentials;
use CPSIT\AdmiralCloudConnector\Exception\InvalidArgumentException;
use CPSIT\AdmiralCloudConnector\Exception\InvalidFileConfigurationException;
use CPSIT\AdmiralCloudConnector\Resource\File;
use CPSIT\AdmiralCloudConnector\Resource\Index\FileIndexRepository;
use CPSIT\AdmiralCloudConnector\Traits\AdmiralCloudStorage;
use CPSIT\AdmiralCloudConnector\Utility\ConfigurationUtility;
use CPSIT\AdmiralCloudConnector\Utility\ImageUtility;
use CPSIT\AdmiralCloudConnector\Utility\PermissionUtility;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileType;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\SingletonInterface;

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
     * Metadata fields in AdmiralCloud
     */
    protected array $metaDataFields = [
        'container_name',
        'container_description',
        'meta*',
        'type',
    ];

    public function __construct(
        FileIndexRepository $fileIndexRepository,
        StorageRepository $storageRepository,
        protected readonly LoggerInterface $logger,
    ) {
        $this->fileIndexRepository = $fileIndexRepository;
        $this->storageRepository = $storageRepository;
    }

    public function getMediaType(string $type): string
    {
        $fileType = FileType::tryFrom((int)$type);

        return match ($fileType) {
            FileType::TEXT, FileType::APPLICATION => 'document',
            FileType::IMAGE => 'image',
            FileType::AUDIO => 'audio',
            FileType::VIDEO => 'video',
            default => $type,
        };
    }

    public function getAdmiralCloudAuthCode(array $settings): string
    {
        try {
            return AdmiralCloudApiFactory::auth($settings);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException('AdmiralCloud Auth Code cannot be created', 1559128418168, $exception);
        }
    }

    public function callAdmiralCloudApi(array $settings, string $method = 'post'): AdmiralCloudApi
    {
        try {
            return AdmiralCloudApiFactory::create($settings, $method);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException('AdmiralCloud API cannot be created', 1559128418168, $exception);
        }
    }

    public function getMetaData(array $identifiers): array
    {
        $settings = [
            'route' => 'metadata/findBatch',
            'controller' => 'metadata',
            'action' => 'findbatch',
            'payload' => [
                'ids' => array_map('intval', $identifiers),
                'title' => $this->metaDataFields,
            ],
        ];

        $fileInfoData = $this->callAdmiralCloudApi($settings)->getData();

        if (!$fileInfoData) {
            $this->logger->error(
                'Empty data received after calling getMetaData of identifiers: {identifiers}',
                ['identifiers' => implode(',', $identifiers)],
            );

            return [];
        }

        $fileInfo = json_decode($fileInfoData);

        if (!$fileInfo) {
            $this->logger->error(
                'Error decoding JSON by getMetaData of identifiers [{identifiers}]. Json error code: {errorCode}. Json message: {errorMessage}. Json: {json}',
                [
                    'identifiers' => implode(',', $identifiers),
                    'errorCode' => json_last_error(),
                    'errorMessage' => json_last_error_msg(),
                    'json' => $fileInfoData,
                ],
            );

            return [];
        }

        $metadata = [];

        foreach ($fileInfo as $file) {
            foreach ($settings['payload']['title'] as $index => $title) {
                $metadata[$file->mediaContainerId][$title] = '';

                if (strtolower((string)$file->title) === strtolower((string)$title)) {
                    $metadata[$file->mediaContainerId][$title] = $file->content;
                    unset($settings['payload']['title'][$index]);
                    break;
                }
            }
        }

        return $metadata;
    }

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
                'ids' => array_map('intval', $identifiers),
            ],
        ];

        $fileMetaData = $this->getMetaData($identifiers);
        $fileInfoData = $this->callAdmiralCloudApi($settings)->getData();

        if (!$fileInfoData) {
            $this->logger->error(
                'Empty data received after calling getMediaInfo of identifiers: {identifiers}',
                ['identifiers' => implode(',', $identifiers)],
            );

            return [];
        }

        $fileInfo = json_decode($fileInfoData);

        if (!$fileInfo) {
            $this->logger->error(
                'Error decoding JSON by getMediaInfo of identifiers [{identifiers}]. Json error code: {errorCode}. Json message: {errorMessage}. Json: {json}',
                [
                    'identifiers' => implode(',', $identifiers),
                    'errorCode' => json_last_error(),
                    'errorMessage' => json_last_error_msg(),
                    'json' => $fileInfoData,
                ],
            );

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
                'identifier_hash' => sha1((string)$file->mediaContainerId),
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

    public function getSearch(array $search): array
    {
        $settings = [
            'route' => 'search',
            'controller' => 'search',
            'action' => 'search',
            'payload' => $search,
        ];

        return json_decode($this->callAdmiralCloudApi($settings)->getData())->hits->hits ?? [];
    }

    public function getEmbedLinks(int $id): array
    {
        $settings = [
            'route' => 'embedlink/' . $id,
            'controller' => 'embedlink',
            'action' => 'find',
            'payload' => [
                'mediaContainerId' => $id,
            ],
        ];

        return json_decode($this->callAdmiralCloudApi($settings, 'get')->getData()) ?? [];
    }

    /**
     * Get metadata for AdmiralCloud files which were updated after given date
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
            'payload' => $payload,
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
     * Get external auth token for file
     */
    public function getExternalAuthToken(string $identifier, string $type): array
    {
        $payload = [];
        $payload['identifier'] = $identifier;
        $payload['type'] = $type;

        $settings = [
            'route' => 'extAuth',
            'controller' => 'user',
            'action' => 'extAuth',
            'payload' => $payload,
        ];

        return json_decode($this->callAdmiralCloudApi($settings)->getData(), true) ?? [];
    }

    /**
     * Make search call to AdmiralCloud to get all metadata for given identifiers
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
            'payload' => $payload,
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

            $this->logger->error(
                'Error searching for metadata. Some identifiers were not found in AdmiralCloud. Identifiers: {identifiers}',
                ['identifiers' => implode(',', $notFound)],
            );
        }

        return $metaDataArray;
    }

    /**
     * Get public url for AdmiralCloud video
     */
    public function getVideoPublicUrl(FileInterface $file): string
    {
        return $this->getDirectPublicUrlForMedia($file);
    }

    /**
     * Get public url for AdmiralCloud audio
     */
    public function getAudioPublicUrl(FileInterface $file): string
    {
        return $this->getDirectPublicUrlForMedia($file);
    }

    /**
     * Get public url for AdmiralCloud document
     */
    public function getDocumentPublicUrl(FileInterface $file): string
    {
        return $this->getDirectPublicUrlForFile($file);
    }

    /**
     * Get public url for AdmiralCloud player
     */
    public function getPlayerPublicUrl(FileInterface $file, string $fe_group = ''): string
    {
        return $this->getPlayerPublicUrlForFile($file, $fe_group);
    }

    /**
     * Get public url for admiral cloud image
     */
    public function getImagePublicUrl(FileInterface $file, int $width = 0, int $height = 0): string
    {
        $credentials = new Credentials();

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
            !$width ? ConfigurationUtility::getDefaultImageWidth() : null,
        );

        $isSvgMimeType = ConfigurationUtility::isSvgMimeType($file->getMimeType());
        $fe_group = PermissionUtility::getPageFeGroup();

        if (!$fe_group && $file->getProperty('tablenames') === 'tt_content' && $file->getProperty('uid_foreign')) {
            $fe_group = PermissionUtility::getContentFeGroupFromReference($file->getProperty('uid_foreign'));
        } elseif ($file->getContentFeGroup()) {
            $fe_group = $file->getContentFeGroup();
        }

        $token = '';
        $auth = '';

        if ($fe_group) {
            $token = $this->getSecuredToken($file, 'image', 'embedlink');

            if ($token) {
                $auth = 'auth=' . base64_encode($credentials->getClientId() . ':' . $token['token']);
            }
        }

        // Get image public url
        if (!$isSvgMimeType && $file->getTxAdmiralCloudConnectorCrop()) {
            // With crop information
            $cropData = json_decode((string)$file->getTxAdmiralCloudConnectorCrop()) or $cropData = json_decode('{"usePNG": "false"}');

            return sprintf(
                '%sv3/deliverEmbed/%s/image%s/cropperjsfocus/%s/%s/%s?poc=true%s%s',
                ConfigurationUtility::getSmartcropUrl(),
                $token ? $token['hash'] : $file->getTxAdmiralCloudConnectorLinkhash(),
                property_exists($cropData, 'usePNG') && $cropData->usePNG === 'true' ? '_png' : '',
                $dimensions->width,
                $dimensions->height,
                $file->getTxAdmiralCloudConnectorCropUrlPath(),
                ConfigurationUtility::isProduction() ? '' : '&env=dev',
                $token ? '&' . $auth : '',
            );
        }

        if ($isSvgMimeType) {
            return ConfigurationUtility::getImageUrl() . ($token ? 'v5/deliverFile/' : 'v3/deliverEmbed/')
                . ($token ? $token['hash'] : $file->getTxAdmiralCloudConnectorLinkhash())
                . ($token ? '/' : '/image/')
                . ($token ? '?' . $auth : '');
        }

        // Without crop information
        return sprintf(
            '%sv3/deliverEmbed/%s/image/autocrop/%s/%s/1?poc=true%s',
            ConfigurationUtility::getSmartcropUrl(),
            $token ? $token['hash'] : $file->getTxAdmiralCloudConnectorLinkhash(),
            $dimensions->width,
            $dimensions->height,
            $token ? '&' . $auth : '',
        );
    }

    /**
     * Get public url for file thumbnail
     */
    public function getThumbnailUrl(FileInterface $file): string
    {
        if ($file instanceof FileReference) {
            $file = $file->getOriginalFile();
        }

        return sprintf(
            '%s/v5/deliverEmbed/%s/image/144',
            ConfigurationUtility::getThumbnailUrl(),
            $file->getTxAdmiralCloudConnectorLinkhash(),
        );
    }

    public function getStorage(): ResourceStorage
    {
        return $this->getAdmiralCloudStorage();
    }

    public function getLinkHashFromMediaContainer(array $mediaContainer, bool $usePNG): string
    {
        $links = $mediaContainer['links'] ?? [];
        $linkHash = '';

        // Flag Id for given media container type
        $flagId = ConfigurationUtility::getFlagPlayerConfigId();

        // Player configuration id for given media container type
        $playerConfigurationId = match ($mediaContainer['type']) {
            'image' => $usePNG ? ConfigurationUtility::getImagePNGPlayerConfigId() : ConfigurationUtility::getImagePlayerConfigId(),
            'video' => ConfigurationUtility::getVideoPlayerConfigId(),
            'audio' => ConfigurationUtility::getAudioPlayerConfigId(),
            'document' => ConfigurationUtility::getDocumentPlayerConfigId(),
            default => throw new InvalidFileConfigurationException(
                'Any valid type was found for file in mediaContainer. Given type: ' . $mediaContainer['type'],
                111222444580,
            ),
        };

        // Find link with flag id and player configuration id for given media container
        foreach ($links as $link) {
            if (isset($link['playerConfigurationId'], $link['flag']) &&
                (int)$link['playerConfigurationId'] === $playerConfigurationId &&
                (int)$link['flag'] === $flagId
            ) {
                $linkHash = $link['link'];
                break;
            }
        }

        // If there isn't link, it is not possible to obtain the public url
        // Link is required for AdmiralCloud field
        if (!$linkHash) {
            throw new InvalidFileConfigurationException(
                'Any valid hash was found for file in mediaContainer given configuration: ' . json_encode($mediaContainer),
                111222444578,
            );
        }

        return $linkHash;
    }

    /**
     * @throws InvalidFileConfigurationException
     */
    public function getAuthLinkHashFromMediaContainer(array $mediaContainer): string
    {
        $links = $mediaContainer['links'] ?? [];
        $linkHash = '';

        // Flag Id for given media container type
        $flagId = ConfigurationUtility::getFlagPlayerConfigId();

        // Player configuration id for given media container type
        $playerConfigurationId = match ($mediaContainer['type']) {
            'image' => ConfigurationUtility::getAuthImagePlayerConfigId(),
            'video' => ConfigurationUtility::getAuthVideoPlayerConfigId(),
            'audio' => ConfigurationUtility::getAuthAudioPlayerConfigId(),
            'document' => ConfigurationUtility::getAuthDocumentPlayerConfigId(),
            default => throw new InvalidFileConfigurationException(
                'Any valid type was found for file in mediaContainer. Given type: ' . $mediaContainer['type'],
                111222444580
            ),
        };

        if (!$playerConfigurationId) {
            return '';
        }

        // Find link with flag id and player configuration id for given media container
        foreach ($links as $link) {
            if (isset($link['playerConfigurationId'], $link['flag']) &&
                (int)$link['playerConfigurationId'] === $playerConfigurationId &&
                (int)$link['flag'] === $flagId
            ) {
                $linkHash = $link['link'];
                break;
            }
        }

        return $linkHash;
    }

    /**
     * Get direct public url for given hash
     */
    public function getDirectPublicUrlForHash(string $hash): string
    {
        return ConfigurationUtility::getDirectFileUrl() . $hash;
    }

    /**
     * Get direct public url for given file
     */
    public function getDirectPublicUrlForFile(FileInterface $file): string
    {
        $credentials = new Credentials();
        $enableAcReadableLinks = $GLOBALS['TYPO3_REQUEST']->getAttribute('frontend.typoscript')->getSetupArray()['config.']['enableAcReadableLinks'] ?? false;
        $feGroup = ($GLOBALS['admiralcloud']['fe_group'][$file->getIdentifier()] ?? null) || PermissionUtility::getPageFeGroup();

        if ($enableAcReadableLinks && !$feGroup) {
            return sprintf(
                '%s%s/%s/%s',
                ConfigurationUtility::getLocalFileUrl(),
                $file->getTxAdmiralCloudConnectorLinkhash(),
                $file->getIdentifier(),
                $file->getName(),
            );
        }

        if ($feGroup) {
            $token = $this->getSecuredToken($file, $this->getMediaType($file->getProperty('type')), 'player');

            if ($token) {
                return sprintf(
                    '%s%s?auth=%s',
                    ConfigurationUtility::getDirectFileUrl(),
                    $token['hash'],
                    base64_encode($credentials->getClientId() . ':' . $token['token']),
                );
            }
        }

        return ConfigurationUtility::getDirectFileUrl() . $file->getTxAdmiralCloudConnectorLinkhash();
    }

    /**
     * Get direct public url for given media file
     */
    protected function getDirectPublicUrlForMedia(FileInterface $file, bool $download = false): string
    {
        if (($GLOBALS['admiralcloud']['fe_group'][$file->getIdentifier()] ?? null) || PermissionUtility::getPageFeGroup()) {
            $mediaType = $this->getMediaType($file->getProperty('type'));
            $token = $this->getSecuredToken($file, $mediaType, 'player');

            if ($token) {
                if ($mediaType === 'document') {
                    $credentials = new Credentials();

                    return sprintf(
                        '%s%s%s?auth=%s',
                        ConfigurationUtility::getDirectFileUrl(),
                        $token['hash'],
                        $download ? '?download=true' : '',
                        base64_encode($credentials->getClientId() . ':' . $token['token']),
                    );
                }

                return sprintf(
                    '%s%s%s&token=%s',
                    ConfigurationUtility::getDirectFileUrl(),
                    $token['hash'],
                    $download ? '?download=true' : '',
                    $token['token'],
                );
            }
        }

        return ConfigurationUtility::getDirectFileUrl()
            . $file->getTxAdmiralCloudConnectorLinkhash()
            . ($download ? '?download=true' : '');
    }

    /**
     * Get player public url for given file
     */
    protected function getPlayerPublicUrlForFile(FileInterface $file, string $fe_group): string
    {
        if ($fe_group) {
            $token = $this->getSecuredToken($file, $this->getMediaType($file->getProperty('type')), 'player');

            if ($token) {
                return sprintf(
                    '%s%s&token=%s',
                    ConfigurationUtility::getPlayerFileUrl(),
                    $token['hash'],
                    $token['token'],
                );
            }
        }

        return ConfigurationUtility::getPlayerFileUrl() . $file->getTxAdmiralCloudConnectorLinkhash();
    }

    /**
     * @return array{hash: string, token: string}|null
     */
    protected function getSecuredToken(FileInterface $file, string $linkType, string $extAuthType): ?array
    {
        $searchData = $this->getSearch([
            'from' => 0,
            'size' => 1,
            'searchTerm' => $file->getTxAdmiralCloudConnectorLinkhash(),
            'field' => 'links',
            'noAggregation' => true,
        ]);

        $mediaContainer = [
            'type' => $linkType,
            'links' => [],
        ];

        if ($searchData) {
            foreach ($searchData as $item) {
                foreach ($item->_source->links as $link) {
                    $linkConfig = [];
                    $linkConfig['playerConfigurationId'] = $link->playerConfigurationId;
                    $linkConfig['type'] = $link->type;
                    $linkConfig['link'] = $link->link;
                    $linkConfig['flag'] = ConfigurationUtility::getFlagPlayerConfigId();
                    $mediaContainer['links'][] = $linkConfig;
                }
            }

            $hash = $this->getAuthLinkHashFromMediaContainer($mediaContainer);

            if ($hash) {
                $token = $this->getExternalAuthToken($hash, $extAuthType);

                if ($token && isset($token['token'])) {
                    return [
                        'hash' => $hash,
                        'token' => $token['token'],
                    ];
                }
            }
        }

        return null;
    }

    public function addMediaByHash(string $hash): int|false
    {
        $searchData = $this->getSearch([
            'from' => 0,
            'size' => 1,
            'searchTerm' => $hash,
            'field' => 'links',
            'noAggregation' => true,
        ]);

        $firstSearchData = reset($searchData);

        if ($firstSearchData) {
            return $this->addMediaByIdHashAndType($firstSearchData->_id, $hash, $firstSearchData->_source->type);
        }

        return false;
    }

    public function addMediaById(array $identifiers): array
    {
        $return = [];
        $metaData = $this->searchMetaDataForIdentifiers($identifiers);

        foreach ($metaData as $id => $data) {
            $return[$id] = false;

            if (isset($data['type'])) {
                $embedDatas = $this->getEmbedLinks($id);
                $playerConfigurationId = ConfigurationUtility::getPlayerConfigurationIdByType($data['type']);

                foreach ($embedDatas as $embedData) {
                    if ((int)$embedData->playerConfigurationId === $playerConfigurationId) {
                        $return[$id] = $this->addMediaByIdHashAndType($id, $embedData->link, $embedData->type);
                    }
                }
            }
        }

        return $return;
    }

    public function addMediaByIdHashAndType(string $mediaContainerId, string $linkHash, string $type): int|false
    {
        try {
            $storage = $this->getAdmiralCloudStorage();
            $indexer = $this->getIndexer($storage);
            $file = $storage->getFile($mediaContainerId);

            if ($file instanceof File) {
                $file->setTxAdmiralCloudConnectorLinkhash($linkHash);
                $file->setType($type);
                $this->getFileIndexRepository()->add($file);
                // (Re)Fetch metadata
                $indexer->extractMetaData($file);
            }

            return $file->getUid();
        } catch (\Exception $exception) {
            $this->logger->error('Error adding file from AdmiralCloud.', ['exception' => $exception]);
        }

        return false;
    }
}
