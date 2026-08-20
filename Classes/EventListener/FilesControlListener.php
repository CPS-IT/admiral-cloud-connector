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

namespace CPSIT\AdmiralCloudConnector\EventListener;

use CPSIT\AdmiralCloudConnector\Resource\AdmiralCloudDriver;
use TYPO3\CMS\Backend\Form\Event\CustomFileSelectorsEvent;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * @internal
 */
#[AsEventListener('admiral-cloud-connector/files-control-listener')]
final readonly class FilesControlListener
{
    public function __construct(
        private FlashMessageService $flashMessageService,
        private IconFactory $iconFactory,
        private LanguageServiceFactory $languageServiceFactory,
        private UriBuilder $uriBuilder,
    ) {}

    /**
     * @throws RouteNotFoundException
     */
    public function __invoke(CustomFileSelectorsEvent $event): void
    {
        // Early return if no storage is available
        if (!$this->isAdmiralCloudStorageAvailable()) {
            $this->addFlashMessage();
            return;
        }

        $selectors = $event->getSelectors();
        $selectors[] = $this->renderButton(AdmiralCloudFileControl::Overview, $event);
        $selectors[] = $this->renderButton(AdmiralCloudFileControl::Upload, $event);

        $event->setSelectors($selectors);
    }

    /**
     * @throws RouteNotFoundException
     */
    private function renderButton(AdmiralCloudFileControl $action, CustomFileSelectorsEvent $event): string
    {
        $languageService = $this->getLanguageService();
        $element = 'admiral_cloud' . md5($event->getFormFieldIdentifier());

        $compactViewUrl = (string)$this->uriBuilder->buildUriFromRoute($action->ajaxRoute(), [
            'element' => $element,
            'irreObject' => $event->getFormFieldIdentifier(),
        ]);

        $javaScriptModules = $event->getJavaScriptModules();
        $javaScriptModules[] = JavaScriptModuleInstruction::create('@cpsit/admiral-cloud-connector/browser.js');
        $event->setJavaScriptModules($javaScriptModules);

        return sprintf(
            '<button type="button" class="btn btn-default t3js-admiral_cloud-browser-btn %s %s" data-admiral_cloud-browser-url="%s" data-title="%s">%s%s</button>',
            $action->cssClass(),
            $element,
            htmlspecialchars($compactViewUrl),
            htmlspecialchars($languageService->sL('admiral_cloud_connector.be:browser.header')),
            $this->iconFactory->getIcon('actions-admiral_cloud-browser', IconSize::SMALL)->render(),
            htmlspecialchars($languageService->sL($action->label())),
        );
    }

    private function isAdmiralCloudStorageAvailable(): bool
    {
        foreach ($this->getBackendUserAuthentication()?->getFileStorages() ?? [] as $fileStorage) {
            if ($fileStorage->getDriverType() === AdmiralCloudDriver::KEY) {
                return true;
            }
        }

        return false;
    }

    private function addFlashMessage(): void
    {
        $this->flashMessageService->getMessageQueueByIdentifier()->addMessage(
            new FlashMessage(
                $this->getLanguageService()->sL('admiral_cloud_connector.be:browser.error-no-storage-access'),
                '',
                ContextualFeedbackSeverity::ERROR,
            ),
        );
    }

    private function getLanguageService(): LanguageService
    {
        return $this->languageServiceFactory->createFromUserPreferences($this->getBackendUserAuthentication());
    }

    private function getBackendUserAuthentication(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
