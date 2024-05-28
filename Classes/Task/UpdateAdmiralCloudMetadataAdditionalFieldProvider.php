<?php

namespace CPSIT\AdmiralCloudConnector\Task;

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Scheduler\Task\Enumeration\Action;

/**
 * Class UpdateAdmiralCloudMetadataAdditionalFieldProvider
 * @package CPSIT\AdmiralCloudConnector\Task
 */
class UpdateAdmiralCloudMetadataAdditionalFieldProvider extends AbstractAdditionalFieldProvider
{
    /**
     * @inheritDoc
     */
    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule)
    {
        $currentSchedulerModuleAction = $schedulerModule->getCurrentAction();

        // Initialize selected fields
        if (empty($taskInfo['scheduler_updateAdmiralCloudMetadata_actionType'])) {
            $taskInfo['scheduler_updateAdmiralCloudMetadata_actionType'] = [];
            if ($currentSchedulerModuleAction->equals(Action::ADD)) {
                $taskInfo['scheduler_updateAdmiralCloudMetadata_actionType'][0] = UpdateAdmiralCloudMetadataTask::ACTION_TYPE_UPDATE_LAST_CHANGED;
            } elseif ($currentSchedulerModuleAction->equals(Action::EDIT)) {
                $taskInfo['scheduler_updateAdmiralCloudMetadata_actionType'][0] = $task->actionType;
            }
        }
        $fieldName = 'tx_scheduler[scheduler_updateAdmiralCloudMetadata_actionType][]';
        $fieldId = 'task_updateAdmiralCloudMetadata_actionType';
        $fieldOptions = [
            [
                UpdateAdmiralCloudMetadataTask::ACTION_TYPE_UPDATE_LAST_CHANGED,
                'LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:task.update_admiral_cloud_metadata.actionType.update_last_changed'
            ],
            [
                UpdateAdmiralCloudMetadataTask::ACTION_TYPE_UPDATE_ALL,
                'LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:task.update_admiral_cloud_metadata.actionType.update_all'
            ]
        ];
        $fieldHtml = '<select class="form-control" name="' . $fieldName . '" id="' . $fieldId . '">';

        foreach ($fieldOptions as $fieldOption) {
            $selected = '';
            if ($task !== null) {
                $selected = ($fieldOption[0] === $task->actionType) ? ' selected' : '';
            }

            $fieldHtml .= sprintf(
                '<option value="%s" %s>%s</option>',
                $fieldOption[0],
                $selected,
                $this->getLanguageService()->sL($fieldOption[1])
            );
        }

        $fieldHtml .= '</select>';

        $additionalFields[$fieldId] = [
            'code' => $fieldHtml,
            'label' => 'LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:task.update_admiral_cloud_metadata.actionType',
            'cshKey' => '_MOD_system_txschedulerM1',
            'cshLabel' => $fieldId
        ];
        return $additionalFields;
    }

    /**
     * @inheritDoc
     */
    public function validateAdditionalFields(array &$submittedData, SchedulerModuleController $schedulerModule)
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function saveAdditionalFields(array $submittedData, AbstractTask $task)
    {
        $task->actionType = reset($submittedData['scheduler_updateAdmiralCloudMetadata_actionType']);
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}
