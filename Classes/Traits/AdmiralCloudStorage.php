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

namespace CPSIT\AdmiralCloudConnector\Traits;

use CPSIT\AdmiralCloudConnector\Exception\InvalidArgumentException;
use CPSIT\AdmiralCloudConnector\Resource\AdmiralCloudDriver;
use CPSIT\AdmiralCloudConnector\Resource\Index\FileIndexRepository;
use TYPO3\CMS\Core\Resource\Index\Indexer;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

trait AdmiralCloudStorage
{
    protected ?FileIndexRepository $fileIndexRepository = null;
    protected ?StorageRepository $storageRepository = null;

    protected function getAdmiralCloudStorage(int $storageUid = 0): ResourceStorage
    {
        $this->storageRepository ??= GeneralUtility::makeInstance(StorageRepository::class);

        $allStorages = $this->storageRepository->findAll();

        if ($storageUid > 0) {
            foreach ($allStorages as $storage) {
                if ($storage->getUid() === $storageUid) {
                    return $storage;
                }
            }
        }

        foreach ($allStorages as $fileStorage) {
            if ($fileStorage->getDriverType() === AdmiralCloudDriver::KEY) {
                return $fileStorage;
            }
        }

        throw new InvalidArgumentException('Missing Admiral Cloud file storage', 1559128872210);
    }

    protected function getIndexer(ResourceStorage $storage): Indexer
    {
        return GeneralUtility::makeInstance(Indexer::class, $storage);
    }

    protected function getFileIndexRepository(): FileIndexRepository
    {
        $this->fileIndexRepository ??= GeneralUtility::makeInstance(FileIndexRepository::class);

        return $this->fileIndexRepository;
    }
}
