<?php

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

if (!defined('TYPO3')) {
    die('Access denied.');
}

$pathEventIcon = 'EXT:admiral_cloud_connector/Resources/Public/Icons/ac.svg';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
    'AdmiralCloud',
    '',
    '',
    [
        'routeTarget' => '',
        'access' => 'group,user',
        'name' => 'events',
        'icon' => 'EXT:t3events/Resources/Public/Icons/event-calendar.svg',
        'labels' => 'LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_mod_main.xlf',
    ]
);

$GLOBALS['TYPO3_USER_SETTINGS']['columns']['first_name'] = [
    'label' => 'LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:usersettings.first_name',
    'type' => 'text',
    'max' => 80,
    'table' => 'be_users',
];
$GLOBALS['TYPO3_USER_SETTINGS']['columns']['last_name'] = [
    'label' => 'LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:usersettings.last_name',
    'type' => 'text',
    'max' => 80,
    'table' => 'be_users',
];
$GLOBALS['TYPO3_USER_SETTINGS']['columns']['security_group'] = [
    'label' => 'LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:usersettings.security_group',
    'type' => 'text',
    'max' => 80,
    'table' => 'be_users',
];
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToUserSettings('--div--;LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:admiral_cloud_connector_title,first_name,last_name,security_group');
