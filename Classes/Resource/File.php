<?php

declare(strict_types=1);

namespace CPSIT\AdmiralCloudConnector\Resource;

use CPSIT\AdmiralCloudConnector\Traits\AdmiralCloudStorage;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class File
 * @package CPSIT\AdmiralCloudConnector\Resource
 */
class File extends \TYPO3\CMS\Core\Resource\File
{
    use AdmiralCloudStorage;

    /**
     * Link hash to generate AdmiralCloud public url
     */
    protected string $txAdmiralCloudConnectorLinkhash = '';
    protected string $txAdmiralCloudConnectorCrop = '';
    protected string $contentFeGroup = '';

    public function getTxAdmiralCloudConnectorLinkhash(): string
    {
        if (!$this->txAdmiralCloudConnectorLinkhash && !empty($this->properties['tx_admiralcloudconnector_linkhash'])) {
            $this->txAdmiralCloudConnectorLinkhash = $this->properties['tx_admiralcloudconnector_linkhash'];
        } else {
            // Load field "tx_admiralcloudconnector_linkhash" from DB
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('sys_file');

            $row = $queryBuilder
                ->select('tx_admiralcloudconnector_linkhash')
                ->from('sys_file')
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($this->getUid(), \PDO::PARAM_INT))
                )
                ->execute()
                ->fetch();

            if (!empty($row['tx_admiralcloudconnector_linkhash'])) {
                $this->properties['tx_admiralcloudconnector_linkhash'] = $row['tx_admiralcloudconnector_linkhash'];
                $this->txAdmiralCloudConnectorLinkhash = $row['tx_admiralcloudconnector_linkhash'];
            }
        }

        return $this->txAdmiralCloudConnectorLinkhash;
    }

    public function setTxAdmiralCloudConnectorLinkhash(string $txAdmiralCloudConnectorLinkhash): void
    {
        $this->txAdmiralCloudConnectorLinkhash = $txAdmiralCloudConnectorLinkhash;
        $this->properties['tx_admiralcloudconnector_linkhash'] = $txAdmiralCloudConnectorLinkhash;

        $this->updatedProperties[] = 'tx_admiralcloudconnector_linkhash';
    }

    public function getTxAdmiralCloudConnectorCrop(): string
    {
        if (!$this->txAdmiralCloudConnectorCrop && !empty($this->properties['tx_admiralcloudconnector_crop'])) {
            $this->txAdmiralCloudConnectorCrop = $this->properties['tx_admiralcloudconnector_crop'];
        }

        return $this->txAdmiralCloudConnectorCrop ?? '';
    }

    public function getTxAdmiralCloudConnectorCropUrlPath(): string
    {
        $cropArray = json_decode($this->getTxAdmiralCloudConnectorCrop(), true);

        if (!$cropArray) {
            return '';
        }

        return implode(',', $cropArray['cropData']) . '/' . implode(',', $cropArray['focusPoint']);
    }

    public function setTxAdmiralCloudConnectorCrop(?string $txAdmiralCloudconnectorLinkhashCrop): void
    {
        $this->txAdmiralCloudConnectorCrop = $txAdmiralCloudconnectorLinkhashCrop;
    }

    public function setTypeFromMimeType(string $mimeType): int
    {
        // this basically extracts the mimetype and guess the filetype based
        // on the first part of the mimetype works for 99% of all cases, and
        // we don't need to make an SQL statement like EXT:media does currently
        list($fileType) = explode('/', $mimeType);
        switch (strtolower($fileType)) {
            case 'text':
                $this->properties['type'] = self::FILETYPE_TEXT;
                break;
            case 'image':
                $this->properties['type'] = self::FILETYPE_IMAGE;
                break;
            case 'audio':
                $this->properties['type'] = self::FILETYPE_AUDIO;
                break;
            case 'video':
                $this->properties['type'] = self::FILETYPE_VIDEO;
                break;
            case 'document':
            case 'application':
            case 'software':
                $this->properties['type'] = self::FILETYPE_APPLICATION;
                break;
            default:
                $this->properties['type'] = self::FILETYPE_UNKNOWN;
        }

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
        if ($taskType === ProcessedFile::CONTEXT_IMAGEPREVIEW
            && $this->getStorage()->getUid() === $this->getAdmiralCloudStorage()->getUid()) {

            // Return admiral cloud url for previews
            return GeneralUtility::makeInstance(ProcessedFile::class, $this, $taskType, $configuration);
        }

        return $this->getStorage()->processFile($this, $taskType, $configuration);
    }

    protected function getFileIndexRepository(): Index\FileIndexRepository
    {
        return GeneralUtility::makeInstance(Index\FileIndexRepository::class);
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

        if (!$extension && (int)$this->getProperty('type') === 2) {
            return 'jpg';
        }

        return $extension;
    }

    public function getIdentifier(): string
    {
        return (string)$this->identifier;
    }
}
