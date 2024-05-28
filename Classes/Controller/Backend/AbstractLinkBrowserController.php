<?php

/*
 * This file is part of the TYPO3 CMS project.
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
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Service\DependencyOrderingService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Script class for the Link Browser window.
 * @internal This class is a specific Backend controller implementation and is not part of the TYPO3's Core API.
 */
abstract class AbstractLinkBrowserController extends \TYPO3\CMS\Backend\Controller\AbstractLinkBrowserController
{
    /**
     * @var ModuleTemplate
     */
    protected ModuleTemplate $moduleTemplate;

    protected ModuleTemplateFactory $moduleTemplateFactory;

    /**
     * AbstractLinkBrowserController constructor.
     */
    public function __construct()
    {
        $this->moduleTemplateFactory = GeneralUtility::makeInstance(ModuleTemplateFactory::class);
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

        $this->determineScriptUrl($request);
        $this->initVariables($request);
        $this->loadLinkHandlers();
        $this->initCurrentUrl();

        $menuData = $this->buildMenuArray();
        if ($this->displayedLinkHandler instanceof LinkHandlerViewProviderInterface) {
            $view = $this->displayedLinkHandler->createView($this->backendViewFactory, $request);
        } else {
            if(($request->getQueryParams()['act'] ?? null) == 'admiralCloud') {
                $view = $this->backendViewFactory->create($request, ['cpsit/admiral-cloud-connector']);
                $this->pageRenderer->loadJavaScriptModule('@cpsit/admiral-cloud-connector/Browser.js');
            } else {
                $view = $this->backendViewFactory->create($request, ['typo3/cms-backend']);
            }
        }
        if ($this->displayedLinkHandler instanceof LinkHandlerVariableProviderInterface) {
            $this->displayedLinkHandler->initializeVariables($request);
        }
        $renderLinkAttributeFields = $this->renderLinkAttributeFields($view);
        if (!empty($this->currentLinkParts)) {
            $this->renderCurrentUrl($view);
        }
        if (method_exists($this->displayedLinkHandler, 'setView')) {
            $this->displayedLinkHandler->setView($view);
        }
        $view->assignMultiple([
            'initialNavigationWidth' => $this->getBackendUser()->uc['selector']['navigation']['width'] ?? 250,
            'menuItems' => $menuData,
            'linkAttributes' => $renderLinkAttributeFields,
            'contentOnly' => $request->getQueryParams()['contentOnly'] ?? false,
        ]);
        $content = $this->displayedLinkHandler->render($request);
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

    /**
     * Renders the link attributes for the selected link handler
     */
    protected function customRenderLinkAttributeFields(ViewInterface $view): string
    {
        $fieldRenderingDefinitions = $this->getLinkAttributeFieldDefinitions();
        $fieldRenderingDefinitions = $this->displayedLinkHandler->modifyLinkAttributes($fieldRenderingDefinitions);
        $this->linkAttributeFields = $this->getAllowedLinkAttributes();
        $content = '';
        foreach ($this->linkAttributeFields as $attribute) {
            $content .= $fieldRenderingDefinitions[$attribute] ?? '';
        }
        $view->assign('allowedLinkAttributes', array_combine($this->linkAttributeFields, $this->linkAttributeFields));

        // add update button if appropriate
        if (!empty($this->currentLinkParts) && $this->displayedLinkHandler === $this->currentLinkHandler && $this->currentLinkHandler->isUpdateSupported()) {
            $view->assign('showUpdateParametersButton', true);
        }
        return $content;
    }

}
