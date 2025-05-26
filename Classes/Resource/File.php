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

namespace CPSIT\AdmiralCloudConnector\Resource;

use CPSIT\AdmiralCloudConnector\Traits\AdmiralCloudStorage;
use TYPO3\CMS\Core\Resource\FileType;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class File extends \TYPO3\CMS\Core\Resource\File
{
    use AdmiralCloudStorage;

    protected string $contentFeGroup = '';

    public function getTxAdmiralCloudConnectorLinkhash(): string
    {
        return $this->properties['tx_admiralcloudconnector_linkhash'] ?? '';
    }

    public function setTxAdmiralCloudConnectorLinkhash(string $txAdmiralCloudConnectorLinkhash): void
    {
        $this->properties['tx_admiralcloudconnector_linkhash'] = $txAdmiralCloudConnectorLinkhash;

        if (!in_array('tx_admiralcloudconnector_linkhash', $this->updatedProperties, true)) {
            $this->updatedProperties[] = 'tx_admiralcloudconnector_linkhash';
        }
    }

    public function getTxAdmiralCloudConnectorCrop(): string
    {
        return $this->properties['tx_admiralcloudconnector_crop'] ?? '';
    }

    public function setTxAdmiralCloudConnectorCrop(?string $txAdmiralCloudConnectorCrop): void
    {
        $this->properties['tx_admiralcloudconnector_crop'] = $txAdmiralCloudConnectorCrop;

        if (!in_array('tx_admiralcloudconnector_crop', $this->updatedProperties, true)) {
            $this->updatedProperties[] = 'tx_admiralcloudconnector_crop';
        }
    }

    public function getTxAdmiralCloudConnectorCropUrlPath(): string
    {
        $cropArray = json_decode($this->getTxAdmiralCloudConnectorCrop(), true);

        if (!$cropArray) {
            return '';
        }

        return implode(',', $cropArray['cropData']) . '/' . implode(',', $cropArray['focusPoint']);
    }

    public function setTypeFromMimeType(string $mimeType): int
    {
        // this basically extracts the mimetype and guess the filetype based
        // on the first part of the mimetype works for 99% of all cases, and
        // we don't need to make an SQL statement like EXT:media does currently
        [$fileType] = explode('/', $mimeType);
        $this->properties['type'] = match (strtolower($fileType)) {
            'text' => FileType::TEXT->value,
            'image' => FileType::IMAGE->value,
            'audio' => FileType::AUDIO->value,
            'video' => FileType::VIDEO->value,
            'document', 'application', 'software' => FileType::APPLICATION->value,
            default => FileType::UNKNOWN->value,
        };

        $this->updatedProperties[] = 'type';

        return $this->properties['type'];
    }

    public function setType(string $type): int
    {
        $this->properties['type'] = $type;
        $this->updatedProperties[] = 'type';

        return (int)$this->properties['type'];
    }

    /**
     * Returns a modified version of the file.
     *
     * @param string $taskType The task type of this processing
     * @param array $configuration the processing configuration, see manual for that
     * @return ProcessedFile The processed file
     */
    public function process(string $taskType, array $configuration): ProcessedFile
    {
        // Return admiral cloud url for previews
        if ($taskType === ProcessedFile::CONTEXT_IMAGEPREVIEW
            && $this->getStorage()->getUid() === $this->getAdmiralCloudStorage()->getUid()
        ) {
            return GeneralUtility::makeInstance(ProcessedFile::class, $this, $taskType, $configuration);
        }

        return $this->getStorage()->processFile($this, $taskType, $configuration);
    }

    public function getContentFeGroup(): string
    {
        return $this->contentFeGroup;
    }

    public function setContentFeGroup(string $contentFeGroup): self
    {
        $this->contentFeGroup = $contentFeGroup;

        return $this;
    }

    /**
     * Get the extension of this file in a lower-case variant
     */
    public function getExtension(): string
    {
        $extension = parent::getExtension();

        if (!$extension && (int)$this->getProperty('type') === FileType::IMAGE->value) {
            return 'jpg';
        }

        return $extension;
    }

    public function getIdentifier(): string
    {
        return (string)$this->identifier;
    }
}
