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

namespace CPSIT\AdmiralCloudConnector\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\LinkHandler\LinkHandlerVariableProviderInterface;
use TYPO3\CMS\Backend\LinkHandler\LinkHandlerViewProviderInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractLinkBrowserController extends \TYPO3\CMS\Backend\Controller\AbstractLinkBrowserController
{
    protected ModuleTemplate $moduleTemplate;
    protected ModuleTemplateFactory $moduleTemplateFactory;

    public function injectModuleTemplateFactory(ModuleTemplateFactory $moduleTemplateFactory): void
    {
        $this->moduleTemplateFactory = $moduleTemplateFactory;
    }

    /**
     * Injects the request object for the current request or subrequest
     * As this controller goes only through the main() method, it is rather simple for now
     *
     * @param ServerRequestInterface $request the current request
     * @return ResponseInterface the response with the content
     */
    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->setUpBasicPageRendererForBackend($this->pageRenderer, $this->extensionConfiguration, $request, $this->getLanguageService());

        $this->pageRenderer->addInlineLanguageLabelFile('EXT:core/Resources/Private/Language/locallang_misc.xlf');
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:core/Resources/Private/Language/locallang_core.xlf');

        $this->initVariables($request);
        $this->loadLinkHandlers();
        $this->initCurrentUrl();

        $menuData = $this->buildMenuArray($request);

        if ($this->displayedLinkHandler instanceof LinkHandlerViewProviderInterface) {
            $view = $this->displayedLinkHandler->createView($this->backendViewFactory, $request);
        } elseif (($request->getQueryParams()['act'] ?? null) === 'admiralCloud') {
            $view = $this->backendViewFactory->create($request, ['cpsit/admiral-cloud-connector']);

            $this->pageRenderer->loadJavaScriptModule('@cpsit/admiral-cloud-connector/Browser.js');
        } else {
            $view = $this->backendViewFactory->create($request, ['typo3/cms-backend']);
        }

        if ($this->displayedLinkHandler instanceof LinkHandlerVariableProviderInterface) {
            $this->displayedLinkHandler->initializeVariables($request);
        }

        $renderLinkAttributeFields = $this->renderLinkAttributeFields($view);

        if (!empty($this->currentLinkParts)) {
            $this->renderCurrentUrl($view);
        }

        if ($this->displayedLinkHandler !== null && method_exists($this->displayedLinkHandler, 'setView')) {
            $this->displayedLinkHandler->setView($view);
        }

        $view->assignMultiple([
            'initialNavigationWidth' => $this->getBackendUser()->uc['selector']['navigation']['width'] ?? 250,
            'menuItems' => $menuData,
            'linkAttributes' => $renderLinkAttributeFields,
            'contentOnly' => $request->getQueryParams()['contentOnly'] ?? false,
        ]);

        $content = $this->displayedLinkHandler?->render($request);

        if (empty($content)) {
            // @todo: b/w compat layer for link handler that don't render full view but return empty
            //        string instead. This case is unfortunate and should be removed if it gives
            //        headaches at some point. If so, above  method_exists($this->displayedLinkHandler, 'setView')
            //        should be removed and setView() method should be made mandatory, or the entire
            //        construct should be refactored a bit.
            $content = $view->render();
        }

        $this->initDocumentTemplate();
        $this->pageRenderer->setTitle('Link Browser');

        if ($request->getQueryParams()['contentOnly'] ?? false) {
            return new HtmlResponse($content);
        }

        $this->pageRenderer->setBodyContent('<body ' . GeneralUtility::implodeAttributes($this->getBodyTagAttributes(), true, true) . '>' . $content);

        return $this->pageRenderer->renderResponse();
    }
}
