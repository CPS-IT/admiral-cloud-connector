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

use CPSIT\AdmiralCloudConnector\Controller\Backend\BrowserController;
use CPSIT\AdmiralCloudConnector\Controller\Backend\ToolbarController;

return [
    'admiral_cloud_browser_auth' => [
        'path' => '/admiral_cloud/browser/auth',
        'target' => BrowserController::class . '::authAction',
    ],
    'admiral_cloud_browser_get_files' => [
        'path' => '/admiral_cloud/browser/getfiles',
        'target' => BrowserController::class . '::getFilesAction',
    ],
    'admiral_cloud_browser_crop_file' => [
        'path' => '/admiral_cloud/browser/cropfile',
        'target' => BrowserController::class . '::cropFileAction',
    ],
    'admiral_cloud_browser_get_media_public_url' => [
        'path' => '/admiral_cloud/browser/getmediapublicurl',
        'target' => BrowserController::class . '::getMediaPublicUrlAction',
    ],
    'admiral_cloud_toolbar_update_changed_metadata' => [
        'path' => '/admiral_cloud/toolvar/updateChangedMetadata',
        'target' => ToolbarController::class . '::updateChangedMetadataAction',
    ],
];
