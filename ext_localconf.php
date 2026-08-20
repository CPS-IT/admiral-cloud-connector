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

use CPSIT\AdmiralCloudConnector\Form\Element\AdmiralCloudImageManipulationElement;
use CPSIT\AdmiralCloudConnector\Resource\AdmiralCloudDriver;
use CPSIT\AdmiralCloudConnector\Resource\File;
use CPSIT\AdmiralCloudConnector\Resource\FileReference;
use CPSIT\AdmiralCloudConnector\Resource\ProcessedFile;
use CPSIT\AdmiralCloudConnector\Resource\Rendering\AssetRenderer;
use TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Resource\Rendering\RendererRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') || die('Access denied.');

// Register image manipulation element for AdmiralCloud files
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1747210949] = [
    'nodeName' => 'admiralCloudImageManipulation',
    'class' => AdmiralCloudImageManipulationElement::class,
    'priority' => 50,
];

// Register the FAL driver for AdmiralCloud
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['registeredDrivers'][AdmiralCloudDriver::KEY] = [
    'class' => AdmiralCloudDriver::class,
    'label' => 'Admiral Cloud',
    // Provide dummy flex form since the sys_file_storage.configuration requires one to be configured
    'flexFormDS' => 'FILE:EXT:admiral_cloud_connector/Configuration/FlexForms/AdmiralCloudDriverFlexForm.xml',
];

// Register the renderer for AdmiralCloud files
GeneralUtility::makeInstance(RendererRegistry::class)->registerRendererClass(AssetRenderer::class);

// XClasses
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\Resource\File::class] = [
    'className' => File::class,
];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\Resource\FileReference::class] = [
    'className' => FileReference::class,
];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\Resource\ProcessedFile::class] = [
    'className' => ProcessedFile::class,
];

// Register cache for extension
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['admiral_cloud_connector'] ?? null)) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['admiral_cloud_connector'] = [
        'frontend' => VariableFrontend::class,
        'backend' => SimpleFileBackend::class,
    ];
}

// Register Backend CSS
$GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['admiral_cloud_connector'] = 'EXT:admiral_cloud_connector/Resources/Public/Backend/Css/acc.css';
