<?php

namespace CPSIT\AdmiralCloudConnector\Task;

use CPSIT\AdmiralCloudConnector\Exception\InvalidPropertyException;
use CPSIT\AdmiralCloudConnector\Service\MetadataService;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Class UpdateAdmiralCloudMetadataTask
 * @package CPSIT\AdmiralCloudConnector\Task
 */
class UpdateAdmiralCloudMetadataTask extends AbstractTask
{
    public const ACTION_TYPE_UPDATE_ALL = 'update_all';
    public const ACTION_TYPE_UPDATE_LAST_CHANGED = 'update_last_changed';

    public $actionType = '';

    /**
     * @inheritDoc
     */
    public function execute()
    {
        switch ($this->actionType) {
            case static::ACTION_TYPE_UPDATE_LAST_CHANGED:
                $this->getMetadataService()->updateLastChangedMetadata();
                break;
            case static::ACTION_TYPE_UPDATE_ALL:
                $this->getMetadataService()->updateAll();
                break;
            default:
                throw new InvalidPropertyException('Action type was not defined for this task.');
                break;
        }

        return true;
    }

    /**
     * This method returns the selected table as additional information
     *
     * @return string Information to display
     */
    public function getAdditionalInformation()
    {
        $message = sprintf(
            $this->getLanguageService()->sL('LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:task.update_admiral_cloud_metadata.additionalInformation'),
            $this->getLanguageService()->sL('LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:task.update_admiral_cloud_metadata.actionType.' . $this->actionType)
        );

        return $message;
    }

    /**
     * @return LanguageService|null
     */
    protected function getLanguageService(): ?LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * @return MetadataServic
     */
    protected function getMetadataService(): MetadataService
    {
        return GeneralUtility::makeInstance(MetadataService::class);
    }
}
