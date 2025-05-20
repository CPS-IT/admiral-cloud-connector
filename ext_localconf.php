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

use CPSIT\AdmiralCloudConnector\Backend\FilesControlContainer;
use CPSIT\AdmiralCloudConnector\Controller\Backend\BrowseLinksController;
use CPSIT\AdmiralCloudConnector\Controller\Backend\LinkBrowserController;
use CPSIT\AdmiralCloudConnector\Form\Element\AdmiralCloudImageManipulationElement;
use CPSIT\AdmiralCloudConnector\Resource\AdmiralCloudDriver;
use CPSIT\AdmiralCloudConnector\Resource\File;
use CPSIT\AdmiralCloudConnector\Resource\FileReference;
use CPSIT\AdmiralCloudConnector\Resource\Index\Extractor;
use CPSIT\AdmiralCloudConnector\Resource\Index\FileIndexRepository;
use CPSIT\AdmiralCloudConnector\Resource\ProcessedFile;
use CPSIT\AdmiralCloudConnector\Resource\Rendering\AssetRenderer;
use CPSIT\AdmiralCloudConnector\Task\UpdateAdmiralCloudMetadataAdditionalFieldProvider;
use CPSIT\AdmiralCloudConnector\Task\UpdateAdmiralCloudMetadataTask;
use TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;
use TYPO3\CMS\Core\Resource\Index\ExtractorRegistry;
use TYPO3\CMS\Core\Resource\Rendering\RendererRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') || die('Access denied.');

// Override files control container to inject AdmiralCloud buttons
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1433198160] = [
    'nodeName' => 'file',
    'priority' => 50,
    'class' => FilesControlContainer::class,
];

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
    // @todo: is currently needed to not break the backend. Needs to be fixed in TYPO3
    'flexFormDS' => 'FILE:EXT:admiral_cloud_connector/Configuration/FlexForms/AdmiralCloudDriverFlexForm.xml',
];

// Register the renderer for AdmiralCloud files
GeneralUtility::makeInstance(RendererRegistry::class)->registerRendererClass(AssetRenderer::class);

// Register the extractor to fetch metadata from AdmiralCloud
GeneralUtility::makeInstance(ExtractorRegistry::class)->registerExtractionService(Extractor::class);

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
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\Resource\Index\FileIndexRepository::class] = [
    'className' => FileIndexRepository::class,
];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Controller\LinkBrowserController::class] = [
    'className' => LinkBrowserController::class,
];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\RteCKEditor\Controller\BrowseLinksController::class] = [
    'className' => BrowseLinksController::class,
];

// Add task to update metadata of AdmiralCloud files
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][UpdateAdmiralCloudMetadataTask::class] = [
    'extension' => 'admiral_cloud_connector',
    'title' => 'LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:task.update_admiral_cloud_metadata.name',
    'description' => 'LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:task.update_admiral_cloud_metadata.description',
    'additionalFields' => UpdateAdmiralCloudMetadataAdditionalFieldProvider::class,
];

// Register cache for extension
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['admiral_cloud_connector'] ?? null)) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['admiral_cloud_connector'] = [
        'frontend' => VariableFrontend::class,
        'backend' => SimpleFileBackend::class,
    ];
}
