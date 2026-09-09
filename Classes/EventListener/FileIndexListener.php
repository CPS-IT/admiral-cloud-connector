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

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\ReferenceIndex;
use TYPO3\CMS\Core\Resource\Event\AfterFileUpdatedInIndexEvent;

/**
 * @internal
 */
#[AsEventListener('admiral-cloud-connector/file-index-listener')]
final readonly class FileIndexListener
{
    private const MANAGED_FIELDS = [
        'tx_admiralcloudconnector_crop',
        'tx_admiralcloudconnector_linkhash',
    ];

    public function __construct(
        private ConnectionPool $connectionPool,
        private ReferenceIndex $referenceIndex,
    ) {}

    public function __invoke(AfterFileUpdatedInIndexEvent $event): void
    {
        $file = $event->getFile();
        $updatedProperties = array_intersect(self::MANAGED_FIELDS, $file->getUpdatedProperties());

        // Early return if no custom-managed properties were updated
        if ($updatedProperties === []) {
            return;
        }

        $updateRow = [
            'tstamp' => time(),
        ];

        foreach ($updatedProperties as $propertyName) {
            $updateRow[$propertyName] = $file->getProperty($propertyName);
        }

        $affectedRows = $this->connectionPool->getConnectionForTable('sys_file_reference')->update(
            'sys_file',
            $updateRow,
            ['uid' => $file->getUid()],
        );

        if ($affectedRows > 0) {
            $this->referenceIndex->updateRefIndexTable('sys_file', $file->getUid());
        }
    }
}
