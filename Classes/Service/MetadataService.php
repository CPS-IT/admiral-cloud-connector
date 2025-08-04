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

use CPSIT\AdmiralCloudConnector\Exception\RuntimeException;
use CPSIT\AdmiralCloudConnector\Traits\AdmiralCloudStorage;
use CPSIT\AdmiralCloudConnector\Utility\ConfigurationUtility;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Resource\StorageRepository;

class MetadataService
{
    use AdmiralCloudStorage;

    protected const ITEMS_LIMIT = 100;
    protected const MAXIMUM_ITERATION = 50000;
    protected const DEFAULT_LAST_CHANGED_DATE = '-7 days';

    public function __construct(
        protected readonly AdmiralCloudService $admiralCloudService,
        #[Autowire(expression: 'service("TYPO3\\\\CMS\\\\Core\\\\Cache\\\\CacheManager").getCache("admiral_cloud_connector")')]
        protected readonly FrontendInterface $cache,
        #[Autowire(expression: 'service("TYPO3\\\\CMS\\\\Core\\\\Database\\\\ConnectionPool").getConnectionForTable("sys_file")')]
        protected readonly Connection $conSysFile,
        #[Autowire(expression: 'service("TYPO3\\\\CMS\\\\Core\\\\Database\\\\ConnectionPool").getConnectionForTable("sys_file_metadata")')]
        protected readonly Connection $conSysFileMetadata,
        protected readonly LoggerInterface $logger,
        StorageRepository $storageRepository,
    ) {
        $this->storageRepository = $storageRepository;
    }

    /**
     * Update metadata for all files from AdmiralCloud storage
     */
    public function updateAll(): void
    {
        $offset = 0;
        $iteration = 0;

        while ($iteration < static::MAXIMUM_ITERATION) {
            $iteration++;

            // Get all AdmiralCloud files with their AdmiralCloud mediaContainerId (stored in identifier field)
            $result = $this->conSysFile
                ->select(['uid', 'identifier'], 'sys_file', ['storage' => $this->getAdmiralCloudStorage()->getUid()], [], [], static::ITEMS_LIMIT, $offset)
                ->fetchAllAssociative();

            if (!$result) {
                break;
            }

            $offset += static::ITEMS_LIMIT;

            // Make mapping array between sysFileUid and mediaContainerId (identifier)
            $mappingSysFileAdmiralCloudId = [];

            foreach ($result as $sysFile) {
                if (!empty($sysFile['identifier'])) {
                    $mappingSysFileAdmiralCloudId[$sysFile['uid']] = $sysFile['identifier'];
                }
            }

            // Get metadata for current bunch files
            $metaDataForIdentifiers = $this->admiralCloudService
                ->searchMetaDataForIdentifiers(array_values($mappingSysFileAdmiralCloudId));

            // Update metadata for AdmiralCloud files
            $this->updateMetadataForAdmiralCloudBunchFiles($mappingSysFileAdmiralCloudId, $metaDataForIdentifiers);
        }

        if ($iteration === static::MAXIMUM_ITERATION) {
            throw new RuntimeException(
                'Error getting metadata for all AdmiralCloud files. Maximum iteration was reached.',
                1747036629,
            );
        }
    }

    /**
     * Update metadata for AdmiralCloud files which were recently changed
     *
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     */
    public function updateLastChangedMetadata(): void
    {
        $offset = 0;
        $iteration = 0;
        $cacheKey = 'lastImportedChangedDate';
        $now = new \DateTime();

        if ($this->cache->has($cacheKey)
            && preg_match('/\d+/', (string)$this->cache->get($cacheKey), $matches) === 1
        ) {
            $lastUpdatedMetaDataDate = new \DateTime('@' . $matches[0]);
        } else {
            $lastUpdatedMetaDataDate = new \DateTime(static::DEFAULT_LAST_CHANGED_DATE);
        }

        while ($iteration < static::MAXIMUM_ITERATION) {
            $iteration++;

            // Get metadata from recently updated files in AdmiralCloud
            $result = $this->admiralCloudService->getUpdatedMetaData($lastUpdatedMetaDataDate, $offset, static::ITEMS_LIMIT);

            if (!$result) {
                break;
            }

            $offset += static::ITEMS_LIMIT;
            $mappingSysFileUidAcId = $this->getMappingSysFileAdmiralCloud(array_keys($result));

            // Update metadata if some of the changed AdmiralCloud files were imported
            if ($mappingSysFileUidAcId) {
                $this->updateMetadataForAdmiralCloudBunchFiles($mappingSysFileUidAcId, $result);
            }
        }

        $this->cache->set($cacheKey, (string)$now->getTimestamp());

        if ($iteration === static::MAXIMUM_ITERATION) {
            throw new RuntimeException(
                'Error getting metadata for last updated AdmiralCloud files. Maximum iteration was reached.',
                1747036724,
            );
        }
    }

    /**
     * Update sys_file_metadata with AdmiralCloud information
     */
    protected function updateMetadataForAdmiralCloudBunchFiles(array $mappingArray, array $admiralCloudMetadata): void
    {
        // Update metadata for current bunch files
        foreach ($admiralCloudMetadata as $identifier => $metadata) {
            $sysFileUid = array_search($identifier, $mappingArray, false);

            if ($sysFileUid) {
                $this->conSysFileMetadata->update(
                    'sys_file_metadata',
                    [
                        'alternative' => $metadata[ConfigurationUtility::getMetaAlternativeField()] ?? '',
                        'title' => $metadata[ConfigurationUtility::getMetaTitleField()] ?? '',
                        'description' => $metadata[ConfigurationUtility::getMetaDescriptionField()] ?? '',
                        'copyright' => $metadata[ConfigurationUtility::getMetaCopyrightField()] ?? '',
                    ],
                    ['file' => $sysFileUid],
                );
            }
        }
    }

    /**
     * Update sys_file_metadata with AdmiralCloud information
     */
    public function updateMetadataForAdmiralCloudFile(int $sysFileUid, array $metadata): void
    {
        $this->conSysFileMetadata->update(
            'sys_file_metadata',
            [
                'alternative' => $metadata[ConfigurationUtility::getMetaAlternativeField()] ?? '',
                'title' => $metadata[ConfigurationUtility::getMetaTitleField()] ?? '',
                'description' => $metadata[ConfigurationUtility::getMetaDescriptionField()] ?? '',
                'copyright' => $metadata[ConfigurationUtility::getMetaCopyrightField()] ?? '',
            ],
            ['file' => $sysFileUid],
        );
    }

    /**
     * Get mapping between sys file and AdmiralCloud items
     */
    protected function getMappingSysFileAdmiralCloud(array $identifiers): array
    {
        $queryBuilder = $this->conSysFile->createQueryBuilder();

        $result = $queryBuilder
            ->select('uid', 'identifier')
            ->from('sys_file')
            ->where(
                $queryBuilder->expr()->in('identifier', $identifiers),
            )
            ->executeQuery()
        ;

        $mapping = [];

        while ($item = $result->fetchAssociative()) {
            $mapping[$item['uid']] = $item['identifier'];
        }

        return $mapping;
    }
}
