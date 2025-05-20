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

return [
    'admiral_cloud_browser_show' => [
        'path' => '/admiral_cloud/browser/show',
        'target' => BrowserController::class . '::showAction',
    ],
    'admiral_cloud_browser_upload' => [
        'path' => '/admiral_cloud/browser/upload',
        'target' => BrowserController::class . '::uploadAction',
    ],
    'admiral_cloud_browser_crop' => [
        'path' => '/admiral_cloud/browser/crop',
        'target' => BrowserController::class . '::cropAction',
    ],
    'admiral_cloud_browser_api' => [
        'path' => '/admiral_cloud/browser/api',
        'target' => BrowserController::class . '::apiAction',
    ],
    'admiral_cloud_browser_rte_link' => [
        'path' => '/admiral_cloud/browser/rte-link',
        'target' => BrowserController::class . '::rteLinkAction',
    ],
];
