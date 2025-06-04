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

namespace CPSIT\AdmiralCloudConnector\Task;

use CPSIT\AdmiralCloudConnector\Exception\InvalidPropertyException;
use CPSIT\AdmiralCloudConnector\Service\MetadataService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

class UpdateAdmiralCloudMetadataTask extends AbstractTask
{
    public const ACTION_TYPE_UPDATE_ALL = 'update_all';
    public const ACTION_TYPE_UPDATE_LAST_CHANGED = 'update_last_changed';

    protected readonly MetadataService $metadataService;
    public string $actionType = '';

    public function __construct()
    {
        parent::__construct();
        $this->metadataService = GeneralUtility::makeInstance(MetadataService::class);
    }

    public function execute(): bool
    {
        match ($this->actionType) {
            static::ACTION_TYPE_UPDATE_LAST_CHANGED => $this->metadataService->updateLastChangedMetadata(),
            static::ACTION_TYPE_UPDATE_ALL => $this->metadataService->updateAll(),
            default => throw new InvalidPropertyException('Action type was not defined for this task.', 1747038968),
        };

        return true;
    }

    /**
     * This method returns the selected table as additional information
     */
    public function getAdditionalInformation(): string
    {
        return sprintf(
            $this->getLanguageService()->sL('LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:task.update_admiral_cloud_metadata.additionalInformation'),
            $this->getLanguageService()->sL('LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:task.update_admiral_cloud_metadata.actionType.' . $this->actionType),
        );
    }
}
