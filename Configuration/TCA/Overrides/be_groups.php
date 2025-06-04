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

ExtensionManagementUtility::addTcaSelectItem(
    'be_groups',
    'file_permissions',
    [
        'LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:be_groups.file_permissions.folder_add_via_admiral_cloud',
        'addFileViaAdmiralCloud',
        'permissions-admiral_cloud-browser',
    ],
    'addFile',
    'after',
);
