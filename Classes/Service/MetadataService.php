<?php

namespace CPSIT\AdmiralCloudConnector\Service;

use CPSIT\AdmiralCloudConnector\Exception\RuntimeException;
use CPSIT\AdmiralCloudConnector\Traits\AdmiralCloudStorage;
use CPSIT\AdmiralCloudConnector\Utility\ConfigurationUtility;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Class MetadataService
 * @package CPSIT\AdmiralCloudConnector\Service
 */
class MetadataService
{
    use AdmiralCloudStorage;

    public const ITEMS_LIMIT = 100;
    public const MAXIMUM_ITERATION = 50000;
    protected const DEFAULT_LAST_CHANGED_DATE = '-7 days';

    /**
     * @var Connection
     */
    protected $conSysFileMetadata;

    /**
     * @var Connection
     */
    protected $conSysFile;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * MetadataService constructor.
     */
    public function __construct()
    {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $this->conSysFileMetadata = $this->getConnectionPool()->getConnectionForTable('sys_file_metadata');
        $this->conSysFile = $this->getConnectionPool()->getConnectionForTable('sys_file');
    }

    /**
     * Update metadata for all files from AdmiralCloud storage
     */
    public function updateAll()
    {
        $offset = 0;
        $iteration = 0;
        $continue = true;

        while ($continue && $iteration < static::MAXIMUM_ITERATION) {
            $iteration++;

            // Get all AdmiralCloud files with their AdmiralCloud mediaContainerId (stored in identifier field)
            $result = $this->conSysFile->select(
                ['uid', 'identifier'],
                'sys_file',
                ['storage' => $this->getAdmiralCloudStorage()->getUid()],
                [],
                [],
                static::ITEMS_LIMIT,
                $offset
            )->fetchAll(\PDO::FETCH_ASSOC);

            $offset += static::ITEMS_LIMIT;

            if ($result) {
                // Make mapping array between sysFileUid and mediaContainerId (identifier)
                $mappingSysFileAdmiralCloudId = [];

                foreach ($result as $sysFile) {
                    if (!empty($sysFile['identifier'])) {
                        $mappingSysFileAdmiralCloudId[$sysFile['uid']] = $sysFile['identifier'];
                    }
                }

                // Get metadata for current bunch files
                $metaDataForIdentifiers = $this->getAdmiralCloudService()
                    ->searchMetaDataForIdentifiers(array_values($mappingSysFileAdmiralCloudId));

                // Update metadata for AdmiralCloud files
                $this->updateMetadataForAdmiralCloudBunchFiles($mappingSysFileAdmiralCloudId, $metaDataForIdentifiers);
            } else {
                $continue = false;
            }
        }

        if ($iteration === static::MAXIMUM_ITERATION) {
            throw new RuntimeException(
                'Error getting metadata for all AdmiralCloud files. Maximum iteration was reached.'
            );
        }
    }

    /**
     * Update metadata for AdmiralCloud files which were recently changed
     *
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException
     */
    public function updateLastChangedMetadatas()
    {
        $offset = 0;
        $iteration = 0;
        $continue = true;
        $cacheKey = 'lastImportedChangedDate';

        /** @var FrontendInterface $cache */
        $cache = GeneralUtility::makeInstance(CacheManager::class)
            ->getCache(ConfigurationUtility::EXTENSION);

        if ($cache->has($cacheKey)) {
            preg_match('/\d+/',(string)$cache->get($cacheKey), $m);
            $lastUpdatedMetaDataDate = \DateTime::createFromFormat('U', $m[0]);
        } else {
            $lastUpdatedMetaDataDate = new \DateTime(static::DEFAULT_LAST_CHANGED_DATE);
        }

        $now = new \DateTime();

        while ($continue && $iteration < static::MAXIMUM_ITERATION) {
            $iteration++;

            // Get metadata from recently updated files in AdmiralCloud
            $result = $this->getAdmiralCloudService()
                ->getUpdatedMetaData($lastUpdatedMetaDataDate, $offset, static::ITEMS_LIMIT);

            $offset += static::ITEMS_LIMIT;
            if ($result) {
                $mappingSysFileUidAcId = $this->getMappingSysFileAdmiralCloud(array_keys($result));

                // Update metadata if some of the changed AdmiralCloud files were imported
                if ($mappingSysFileUidAcId) {
                    $this->updateMetadataForAdmiralCloudBunchFiles($mappingSysFileUidAcId, $result);
                }
            } else {
                $continue = false;
            }
        }

        $cache->set($cacheKey, (string)$now->getTimestamp());

        if ($iteration === static::MAXIMUM_ITERATION) {
            throw new RuntimeException(
                'Error getting metadata for last updated AdmiralCloud files. Maximum iteration was reached.'
            );
        }
    }

    /**
     * Update sys_file_metadata with AdmiralCloud information
     *
     * @param array $mappingArray
     * @param array $admiralCloudMetadata
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
                        'copyright' => $metadata[ConfigurationUtility::getMetaCopyrightField()] ?? ''
                    ],
                    ['file' => $sysFileUid]
                );
            }
        }
    }

    /**
     * Update sys_file_metadata with AdmiralCloud information
     *
     * @param int $sysFileUid
     * @param array $metadata
     */
    public function updateMetadataForAdmiralCloudFile(int $sysFileUid, array $metadata): void
    {
        $this->conSysFileMetadata->update(
            'sys_file_metadata',
            [
                'alternative' => $metadata[ConfigurationUtility::getMetaAlternativeField()] ?? '',
                'title' => $metadata[ConfigurationUtility::getMetaTitleField()] ?? '',
                'description' => $metadata[ConfigurationUtility::getMetaDescriptionField()] ?? '',
                'copyright' => $metadata[ConfigurationUtility::getMetaCopyrightField()] ?? ''
            ],
            ['file' => $sysFileUid]
        );
    }

    /**
     * Get mapping between sys file and AdmiralCloud items
     *
     * @param array $identifiers
     * @return array
     */
    protected function getMappingSysFileAdmiralCloud(array $identifiers): array
    {
        $queryBuilder = $this->conSysFile->createQueryBuilder();

        $result = $queryBuilder
            ->select('uid', 'identifier')
            ->from('sys_file')
            ->where($queryBuilder->expr()->in('identifier', $identifiers))
            ->execute();

        $mapping = [];

        while ($item = $result->fetch(\PDO::FETCH_ASSOC)) {
            $mapping[$item['uid']] = $item['identifier'];
        }

        return $mapping;
    }


    /**
     * @return ConnectionPool
     */
    protected function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }

    /**
     * @return AdmiralCloudService
     */
    protected function getAdmiralCloudService(): AdmiralCloudService
    {
        return GeneralUtility::makeInstance(AdmiralCloudService::class);
    }
}
