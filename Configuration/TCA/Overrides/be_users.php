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

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

ExtensionManagementUtility::addTcaSelectItem(
    'be_users',
    'file_permissions',
    [
        'label' => 'admiral_cloud_connector.be:be_users.file_permissions.folder_add_via_admiral_cloud',
        'value' => 'addFileViaAdmiralCloud',
        'icon' => 'permissions-admiral_cloud-browser',
    ],
    'addFile',
    'after',
);

ExtensionManagementUtility::addTCAcolumns('be_users', [
    'first_name' => [
        'label' => 'admiral_cloud_connector.be:be_users.first_name',
        'config' => [
            'type' => 'input',
            'size' => 15,
            'eval' => 'trim',
        ],
    ],
    'last_name' => [
        'label' => 'admiral_cloud_connector.be:be_users.last_name',
        'config' => [
            'type' => 'input',
            'size' => 15,
            'eval' => 'trim',
        ],
    ],
    'security_group' => [
        'label' => 'admiral_cloud_connector.be:be_users.security_group',
        'config' => [
            'type' => 'input',
            'size' => 15,
            'eval' => 'trim',
        ],
    ],
]);

ExtensionManagementUtility::addToAllTCAtypes(
    'be_users',
    'first_name,last_name,security_group',
    '',
    'after:realName',
);

// User settings
$GLOBALS['TCA']['be_users']['columns']['user_settings']['showitem'] .= ', --div--;admiral_cloud_connector.be:admiral_cloud_connector_title';

ExtensionManagementUtility::addUserSetting(
    'first_name',
    [
        'inheritFromParent' => true,
    ],
    'after:--div--;admiral_cloud_connector.be:admiral_cloud_connector_title',
);
ExtensionManagementUtility::addUserSetting(
    'last_name',
    [
        'inheritFromParent' => true,
    ],
    'after:first_name',
);
ExtensionManagementUtility::addUserSetting(
    'security_group',
    [
        'inheritFromParent' => true,
    ],
    'after:last_name',
);
