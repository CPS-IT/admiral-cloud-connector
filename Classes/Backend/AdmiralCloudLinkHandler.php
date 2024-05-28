<?php

namespace CPSIT\AdmiralCloudConnector\Backend;

use CPSIT\AdmiralCloudConnector\Exception\NotImplementedException;
use CPSIT\AdmiralCloudConnector\Utility\ConfigurationUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\AbstractLinkBrowserController;
use TYPO3\CMS\Backend\LinkHandler\AbstractLinkHandler;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class AdmiralCloudLinkHandler
 * @package CPSIT\AdmiralCloudConnector\Backend
 */
class AdmiralCloudLinkHandler extends AbstractLinkHandler implements \TYPO3\CMS\Backend\LinkHandler\LinkHandlerInterface
{
    /**
     * Parts of the current link
     *
     * @var array
     */
    protected $linkParts = [];

    /**
     * TemplateRootPath
     *
     * @var string[]
     */
    protected $templateRootPaths = ['EXT:admiral_cloud_connector/Resources/Private/Templates/Backend/Browser'];

    /**
     * PartialRootPath
     *
     * @var string[]
     */
    protected $partialRootPaths = ['EXT:admiral_cloud_connector/Resources/Private/Partials/Backend/Browser'];

    /**
     * LayoutRootPath
     *
     * @var string[]
     */
    protected $layoutRootPaths = ['EXT:admiral_cloud_connector/Resources/Private/Layouts/Backend/Browser'];

    /**
     * @var string
     */
    protected $expectedClass = File::class;

    /**
     * @var string
     */
    protected $mode = 'file';

    /**
     * Initialize the handler
     *
     * @param AbstractLinkBrowserController $linkBrowser
     * @param string $identifier
     * @param array $configuration Page TSconfig
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\InvalidExtensionNameException
     */
    public function initialize(AbstractLinkBrowserController $linkBrowser, $identifier, array $configuration)
    {
        $this->linkBrowser = $linkBrowser;
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $this->view = GeneralUtility::makeInstance(\TYPO3\CMS\Fluid\View\StandaloneView::class);
        $this->view->getRequest()->setControllerExtensionName(ConfigurationUtility::EXTENSION);
        $this->view->setPartialRootPaths($this->partialRootPaths);
        $this->view->setTemplateRootPaths($this->templateRootPaths);
        $this->view->setLayoutRootPaths($this->layoutRootPaths);
    }

    /**
     * @inheritDoc
     */
    public function canHandleLink(array $linkParts)
    {
        if (!$linkParts['url']) {
            return false;
        }
        if (isset($linkParts['url'][$this->mode]) && $linkParts['url'][$this->mode] instanceof $this->expectedClass) {
            if(str_starts_with($linkParts['url'][$this->mode]->getMimeType(), 'admiralCloud/')){
                $this->linkParts = $linkParts;
                return true;
            }
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    public function formatCurrentUrl()
    {
        return $this->linkParts['url'][$this->mode]->getName();
    }

    /**
     * @inheritDoc
     */
    public function render(ServerRequestInterface $request)
    {
        //GeneralUtility::makeInstance(PageRenderer::class)->loadRequireJsModule('TYPO3/CMS/AdmiralCloudConnector/Browser');
        GeneralUtility::makeInstance(PageRenderer::class)->loadJavaScriptModule('@cpsit/admiral-cloud-connector/Browser.js');

        $languageService = $this->getLanguageService();

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $compactViewUrl = $uriBuilder->buildUriFromRoute('admiral_cloud_browser_rte_link');

        $rteLinkDownloadLabel = htmlspecialchars($languageService->sL('LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:linkHandler.rteLinkDownload'));
        $buttonText = htmlspecialchars($languageService->sL('LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:browser.button'));
        $titleText = htmlspecialchars($languageService->sL('LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:browser.header'));

        $buttonHtml = [];
        $buttonHtml[] = '<div style="text-align: center;margin-top: 1rem;">'
            . '<span style="display:none"><input id="rteLinkDownload" type="checkbox" style="margin-right: 0.5rem; position: relative; top: 2px;"/>' . $rteLinkDownloadLabel . '</span></div>'
            . '<a href="#" class="btn btn-default t3js-admiral_cloud-browser-btn rte-link"'
            . ' style="margin: 2rem auto;"'
            . ' data-admiral_cloud-browser-url="' . htmlspecialchars($compactViewUrl) . '" '
            . ' data-title="' . htmlspecialchars($titleText) . '">';
        $buttonHtml[] = $this->iconFactory->getIcon('actions-admiral_cloud-browser', Icon::SIZE_SMALL)->render();
        $buttonHtml[] = $buttonText;
        $buttonHtml[] = '</a>';
        return LF . implode(LF, $buttonHtml);
    }

    /**
     * @return string[] Array of body-tag attributes
     */
    public function getBodyTagAttributes()
    {
        return [
            'data-current-link' => GeneralUtility::makeInstance(LinkService::class)->asString(['type' => LinkService::TYPE_FILE, 'file' => $this->linkParts['url']['file']])
        ];
    }
}
