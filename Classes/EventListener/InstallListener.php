<?php

namespace CPSIT\AdmiralCloudConnector\EventListener;

use CPSIT\AdmiralCloudConnector\Resource\AdmiralCloudDriver;
use CPSIT\AdmiralCloudConnector\Utility\ConfigurationUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Package\Event\AfterPackageActivationEvent;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extensionmanager\Utility\InstallUtility;

/**
 * Create need file storage and file mount after install
 *
 * Class InstallSlot
 * @package CPSIT\AdmiralCloudConnector\Slot
 */
class InstallListener
{
    /**
     * Create a new file storage with the AdmiralCloudDriver
     *
     * @param AfterPackageActivationEvent $event
     * @return void
     */
    public function createAdmiralCloudFileStorage(AfterPackageActivationEvent $event)
    {
        $extensionKey = $event->getPackageKey();

        if ($extensionKey !== ConfigurationUtility::EXTENSION) {
            return;
        }

        /** @var $storageRepository StorageRepository */
        $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
        if ($storageRepository->findByStorageType(AdmiralCloudDriver::KEY) !== []) {
            return;
        }

        // Create Admiral cloud storage
        $field_values = [
            'pid' => 0,
            'tstamp' => $GLOBALS['EXEC_TIME'],
            'crdate' => $GLOBALS['EXEC_TIME'],
            'name' => 'AdmiralCloud',
            'description' => 'Automatically created during the installation of EXT:admiral_cloud_connector',
            'driver' => AdmiralCloudDriver::KEY,
            'configuration' => '',
            'is_online' => 1,
            'is_browsable' => 1,
            'is_public' => 1,
            'is_writable' => 0,
            'is_default' => 0,
            // We use the processed file folder of the default storage as fallback
            'processingfolder' => '1:/_processed_/',
        ];

        $dbConnection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_file_storage');
        $dbConnection->insert('sys_file_storage', $field_values);
        $storageUid = (int)$dbConnection->lastInsertId('sys_file_storage');

        // Create file mount (for the editors)
        $field_values = [
            'pid' => 0,
            'tstamp' => $GLOBALS['EXEC_TIME'],
            'title' => 'AdmiralCloud',
            'description' => 'Automatically created during the installation of EXT:admiral_cloud_connector',
            'path' => '',
            'base' => $storageUid,
        ];

        $dbConnection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_filemounts');
        $dbConnection->insert('sys_filemounts', $field_values);
    }
}
