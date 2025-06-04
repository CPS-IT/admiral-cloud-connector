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

namespace CPSIT\AdmiralCloudConnector\EventListener;

use CPSIT\AdmiralCloudConnector\Resource\AdmiralCloudDriver;
use CPSIT\AdmiralCloudConnector\Utility\ConfigurationUtility;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Package\Event\AfterPackageActivationEvent;
use TYPO3\CMS\Core\Resource\StorageRepository;

/**
 * Create need file storage and file mount after install
 */
final readonly class InstallListener
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private Context $context,
        private StorageRepository $storageRepository,
    ) {}

    /**
     * Create a new file storage with the AdmiralCloudDriver
     */
    #[AsEventListener('admiral-cloud-connector/install-listener')]
    public function __invoke(AfterPackageActivationEvent $event): void
    {
        $extensionKey = $event->getPackageKey();

        if ($extensionKey !== ConfigurationUtility::EXTENSION) {
            return;
        }

        if ($this->storageRepository->findByStorageType(AdmiralCloudDriver::KEY) !== []) {
            return;
        }

        // Create Admiral cloud storage
        $currentTimestamp = $this->context->getPropertyFromAspect('date', 'timestamp');
        $defaultStorageUid = $this->storageRepository->getDefaultStorage()?->getUid();
        $field_values = [
            'pid' => 0,
            'tstamp' => $currentTimestamp,
            'crdate' => $currentTimestamp,
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
            'processingfolder' => sprintf('%d:/_processed_/', $defaultStorageUid),
        ];

        $dbConnection = $this->connectionPool->getConnectionForTable('sys_file_storage');
        $dbConnection->insert('sys_file_storage', $field_values);
        $storageUid = (int)$dbConnection->lastInsertId();

        // Create file mount (for the editors)
        $field_values = [
            'pid' => 0,
            'tstamp' => $currentTimestamp,
            'title' => 'AdmiralCloud',
            'description' => 'Automatically created during the installation of EXT:admiral_cloud_connector',
            'identifier' => $storageUid . ':',
        ];

        $dbConnection = $this->connectionPool->getConnectionForTable('sys_filemounts');
        $dbConnection->insert('sys_filemounts', $field_values);
    }
}
