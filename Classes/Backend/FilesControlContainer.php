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

namespace CPSIT\AdmiralCloudConnector\Backend;

use CPSIT\AdmiralCloudConnector\Resource\AdmiralCloudDriver;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Form\InlineStackProcessor;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Resource\DefaultUploadFolderResolver;
use TYPO3\CMS\Core\Resource\Filter\FileExtensionFilter;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\OnlineMediaHelperRegistry;

/**
 * Class FilesControlContainer
 *
 * Override core FilesControlContainer to inject AdmiralCloud button
 */
class FilesControlContainer extends \TYPO3\CMS\Backend\Form\Container\FilesControlContainer
{
    public function __construct(
        protected readonly IconFactory $iconFactory,
        protected readonly InlineStackProcessor $inlineStackProcessor,
        protected readonly UriBuilder $uriBuilder,
        EventDispatcherInterface $eventDispatcher,
        OnlineMediaHelperRegistry $onlineMediaHelperRegistry,
        DefaultUploadFolderResolver $defaultUploadFolderResolver,
        HashService $hashService,
    ) {
        parent::__construct(
            $iconFactory,
            $inlineStackProcessor,
            $eventDispatcher,
            $onlineMediaHelperRegistry,
            $defaultUploadFolderResolver,
            $hashService,
        );
    }

    protected function getFileSelectors(array $inlineConfiguration, FileExtensionFilter $fileExtensionFilter): array
    {
        $controls = parent::getFileSelectors($inlineConfiguration, $fileExtensionFilter);
        $controls[] = $this->renderAdmiralCloudOverviewButton();
        $controls[] = $this->renderAdmiralCloudUploadButton();

        return $controls;
    }

    /**
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    protected function renderAdmiralCloudOverviewButton(): string
    {
        $languageService = $this->getLanguageService();

        if (!$this->admiralCloudStorageAvailable()) {
            $errorTextHtml = [];
            $errorTextHtml[] = '<div class="alert alert-danger" style="display: inline-block">';
            $errorTextHtml[] = $this->iconFactory->getIcon('actions-admiral_cloud-browser', IconSize::SMALL)->render();
            $errorTextHtml[] = htmlspecialchars($languageService->sL('LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:browser.error-no-storage-access'));
            $errorTextHtml[] = '</div>';

            return LF . implode(LF, $errorTextHtml);
        }

        $foreign_table = 'sys_file_reference';
        $currentStructureDomObjectIdPrefix = $this->inlineStackProcessor->getCurrentStructureDomObjectIdPrefix($this->data['inlineFirstPid']);

        $element = 'admiral_cloud' . md5((string)$currentStructureDomObjectIdPrefix);

        $compactViewUrl = (string)$this->uriBuilder->buildUriFromRoute('admiral_cloud_browser_show', [
            'element' => $element,
            'irreObject' => $currentStructureDomObjectIdPrefix . '-' . $foreign_table,
        ]);

        $this->javaScriptModules[] = JavaScriptModuleInstruction::create('@cpsit/admiral-cloud-connector/Browser.js');
        $buttonText = htmlspecialchars($languageService->sL('LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:browser.button'));
        $titleText = htmlspecialchars($languageService->sL('LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:browser.header'));

        $buttonHtml = [];
        $buttonHtml[] = '<a href="#" class="btn btn-default t3js-admiral_cloud-browser-btn overview ' . $element . '"'
            . ' data-admiral_cloud-browser-url="' . htmlspecialchars($compactViewUrl) . '" '
            . ' data-title="' . htmlspecialchars($titleText) . '">';
        $buttonHtml[] = $this->iconFactory->getIcon('actions-admiral_cloud-browser', IconSize::SMALL)->render();
        $buttonHtml[] = $buttonText;
        $buttonHtml[] = '</a>';

        return LF . implode(LF, $buttonHtml);
    }

    /**
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    protected function renderAdmiralCloudUploadButton(): string
    {
        $languageService = $this->getLanguageService();

        if (!$this->admiralCloudStorageAvailable()) {
            $errorTextHtml = [];
            $errorTextHtml[] = '<div class="alert alert-danger" style="display: inline-block">';
            $errorTextHtml[] = $this->iconFactory->getIcon('actions-admiral_cloud-browser', IconSize::SMALL)->render();
            $errorTextHtml[] = htmlspecialchars($languageService->sL('LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:browser.error-no-storage-access'));
            $errorTextHtml[] = '</div>';

            return LF . implode(LF, $errorTextHtml);
        }

        $foreign_table = 'sys_file_reference';
        $currentStructureDomObjectIdPrefix = $this->inlineStackProcessor->getCurrentStructureDomObjectIdPrefix($this->data['inlineFirstPid']);

        $element = 'admiral_cloud' . md5((string)$currentStructureDomObjectIdPrefix);

        $compactViewUrl = (string)$this->uriBuilder->buildUriFromRoute('admiral_cloud_browser_upload', [
            'element' => $element,
            'irreObject' => $currentStructureDomObjectIdPrefix . '-' . $foreign_table,
        ]);

        $this->javaScriptModules[] = JavaScriptModuleInstruction::create('@cpsit/admiral-cloud-connector/Browser.js');
        $buttonText = htmlspecialchars($languageService->sL('LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:browser.uploadbutton'));
        $titleText = htmlspecialchars($languageService->sL('LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:browser.header'));

        $buttonHtml = [];
        $buttonHtml[] = '<a href="#" class="btn btn-default t3js-admiral_cloud-browser-btn upload ' . $element . '"'
            . ' data-admiral_cloud-browser-url="' . htmlspecialchars($compactViewUrl) . '" '
            . ' data-title="' . htmlspecialchars($titleText) . '">';
        $buttonHtml[] = $this->iconFactory->getIcon('actions-admiral_cloud-browser', IconSize::SMALL)->render();
        $buttonHtml[] = $buttonText;
        $buttonHtml[] = '</a>';

        return LF . implode(LF, $buttonHtml);
    }

    protected function admiralCloudStorageAvailable(): bool
    {
        foreach ($this->getBackendUserAuthentication()->getFileStorages() as $fileStorage) {
            if ($fileStorage->getDriverType() === AdmiralCloudDriver::KEY) {
                return true;
            }
        }
        return false;
    }
}
