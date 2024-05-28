<?php

namespace CPSIT\AdmiralCloudConnector\Backend;

/*
 * This source file is proprietary property of Beech.it
 * Date: 19-2-18
 * All code (c) Beech.it all rights reserved
 */



use CPSIT\AdmiralCloudConnector\Resource\AdmiralCloudDriver;
use CPSIT\AdmiralCloudConnector\Utility\ConfigurationUtility;
use CPSIT\AdmiralCloudConnector\Utility\PermissionUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Backend\Form\Container\FileReferenceContainer;
use TYPO3\CMS\Backend\Form\Event\CustomFileControlsEvent;
use TYPO3\CMS\Backend\Form\InlineStackProcessor;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Resource\Filter\FileExtensionFilter;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Class InlineControlContainer
 *
 * Override core InlineControlContainer to inject AdmiralCloud button
 */
class FilesControlContainer extends \TYPO3\CMS\Backend\Form\Container\FilesControlContainer
{
    protected function getFileSelectors(array $inlineConfiguration, FileExtensionFilter $fileExtensionFilter): array
    {
        $controls = parent::getFileSelectors($inlineConfiguration, $fileExtensionFilter);
        $controls[] = $this->renderAdmiralCloudOverviewButton();
        $controls[] = $this->renderAdmiralCloudUploadButton();
        return $controls;
    }


    /**
     * @return string
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    protected function renderAdmiralCloudOverviewButton(): string
    {
        $languageService = $this->getLanguageService();

        if (!$this->admiralCloudStorageAvailable()) {
            $errorTextHtml = [];
            $errorTextHtml[] = '<div class="alert alert-danger" style="display: inline-block">';
            $errorTextHtml[] = $this->iconFactory->getIcon('actions-admiral_cloud-browser', Icon::SIZE_SMALL)->render();
            $errorTextHtml[] = htmlspecialchars($languageService->sL('LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:browser.error-no-storage-access'));
            $errorTextHtml[] = '</div>';

            return LF . implode(LF, $errorTextHtml);
        }

        $foreign_table = 'sys_file_reference';
        $currentStructureDomObjectIdPrefix = $this->inlineStackProcessor->getCurrentStructureDomObjectIdPrefix($this->data['inlineFirstPid']);

        $element = 'admiral_cloud' . md5($currentStructureDomObjectIdPrefix);

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $compactViewUrl = $uriBuilder->buildUriFromRoute('admiral_cloud_browser_show', [
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
        $buttonHtml[] = $this->iconFactory->getIcon('actions-admiral_cloud-browser', Icon::SIZE_SMALL)->render();
        $buttonHtml[] = $buttonText;
        $buttonHtml[] = '</a>';
        return LF . implode(LF, $buttonHtml);
    }

    /**
     * @return string
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    protected function renderAdmiralCloudUploadButton(): string
    {
        $languageService = $this->getLanguageService();

        if (!$this->admiralCloudStorageAvailable()) {
            $errorTextHtml = [];
            $errorTextHtml[] = '<div class="alert alert-danger" style="display: inline-block">';
            $errorTextHtml[] = $this->iconFactory->getIcon('actions-admiral_cloud-browser', Icon::SIZE_SMALL)->render();
            $errorTextHtml[] = htmlspecialchars($languageService->sL('LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:browser.error-no-storage-access'));
            $errorTextHtml[] = '</div>';

            return LF . implode(LF, $errorTextHtml);
        }

        $foreign_table = 'sys_file_reference';
        $currentStructureDomObjectIdPrefix = $this->inlineStackProcessor->getCurrentStructureDomObjectIdPrefix($this->data['inlineFirstPid']);

        $element = 'admiral_cloud' . md5($currentStructureDomObjectIdPrefix);

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $compactViewUrl = $uriBuilder->buildUriFromRoute('admiral_cloud_browser_upload', [
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
        $buttonHtml[] = $this->iconFactory->getIcon('actions-admiral_cloud-browser', Icon::SIZE_SMALL)->render();
        $buttonHtml[] = $buttonText;
        $buttonHtml[] = '</a>';
        return LF . implode(LF, $buttonHtml);
    }

    protected function admiralCloudStorageAvailable(): bool
    {
        /** @var ResourceStorage $fileStorage */
        foreach ($this->getBackendUserAuthentication()->getFileStorages() as $fileStorage) {
            if ($fileStorage->getDriverType() === AdmiralCloudDriver::KEY) {
                return true;
            }
        }
        return false;
    }


}
