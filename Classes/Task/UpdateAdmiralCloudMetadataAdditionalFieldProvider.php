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

use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\SchedulerManagementAction;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

class UpdateAdmiralCloudMetadataAdditionalFieldProvider extends AbstractAdditionalFieldProvider
{
    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule): array
    {
        $currentSchedulerModuleAction = $schedulerModule->getCurrentAction();

        // Initialize selected fields
        if (empty($taskInfo['scheduler_updateAdmiralCloudMetadata_actionType'])) {
            $taskInfo['scheduler_updateAdmiralCloudMetadata_actionType'] = [];

            if ($currentSchedulerModuleAction === SchedulerManagementAction::ADD) {
                $taskInfo['scheduler_updateAdmiralCloudMetadata_actionType'][0] = UpdateAdmiralCloudMetadataTask::ACTION_TYPE_UPDATE_LAST_CHANGED;
            } elseif ($currentSchedulerModuleAction === SchedulerManagementAction::EDIT) {
                $taskInfo['scheduler_updateAdmiralCloudMetadata_actionType'][0] = $task->actionType;
            }
        }

        $fieldName = 'tx_scheduler[scheduler_updateAdmiralCloudMetadata_actionType][]';
        $fieldId = 'task_updateAdmiralCloudMetadata_actionType';
        $fieldOptions = [
            [
                UpdateAdmiralCloudMetadataTask::ACTION_TYPE_UPDATE_LAST_CHANGED,
                'LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:task.update_admiral_cloud_metadata.actionType.update_last_changed',
            ],
            [
                UpdateAdmiralCloudMetadataTask::ACTION_TYPE_UPDATE_ALL,
                'LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:task.update_admiral_cloud_metadata.actionType.update_all',
            ],
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
                $this->getLanguageService()->sL($fieldOption[1]),
            );
        }

        $fieldHtml .= '</select>';

        $additionalFields[$fieldId] = [
            'code' => $fieldHtml,
            'label' => 'LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:task.update_admiral_cloud_metadata.actionType',
            'cshKey' => '_MOD_system_txschedulerM1',
            'cshLabel' => $fieldId,
        ];

        return $additionalFields;
    }

    public function validateAdditionalFields(array &$submittedData, SchedulerModuleController $schedulerModule): bool
    {
        return true;
    }

    public function saveAdditionalFields(array $submittedData, AbstractTask $task): void
    {
        if ($task instanceof UpdateAdmiralCloudMetadataTask) {
            $task->actionType = reset($submittedData['scheduler_updateAdmiralCloudMetadata_actionType']);
        }
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
