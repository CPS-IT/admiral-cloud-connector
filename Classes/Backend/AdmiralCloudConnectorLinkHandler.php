<?php

declare(strict_types=1);

namespace CPSIT\AdmiralCloudConnector\Backend;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Backend\Controller\AbstractLinkBrowserController;
use TYPO3\CMS\Backend\LinkHandler\LinkHandlerInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\View\ViewInterface;

/**
 * Class AdmiralCloudLinkHandler
 * @package CPSIT\AdmiralCloudConnector\Backend
 */
#[Autoconfigure(public: true)]
class AdmiralCloudConnectorLinkHandler implements LinkHandlerInterface
{
    protected ViewInterface $view;

    protected array $linkParts = [];
    protected string $expectedClass = File::class;
    protected string $mode = 'file';

   public function __construct(
       protected readonly IconFactory $iconFactory,
       protected readonly LinkService $linkService,
       protected readonly PageRenderer $pageRenderer,
       protected readonly UriBuilder $uriBuilder,
   ) {}

    public function initialize(AbstractLinkBrowserController $linkBrowser, $identifier, array $configuration): void
    {
        // Nothing to do here.
    }

   public function canHandleLink(array $linkParts): bool
   {
       if (!($linkParts['url'] ?? null)) {
           return false;
       }

       if (is_a($linkParts['url'][$this->mode] ?? null, $this->expectedClass, true)
           && str_starts_with($linkParts['url'][$this->mode]->getMimeType(), 'admiralCloud/')
       ) {
           $this->linkParts = $linkParts;

           return true;
       }

       return false;
   }

   public function formatCurrentUrl(): string
   {
       return $this->linkParts['url'][$this->mode]?->getName() ?? '';
   }

   public function render(ServerRequestInterface $request): string
   {
       $this->pageRenderer->loadJavaScriptModule('@cpsit/admiral-cloud-connector/Browser.js');

       $languageService = $this->getLanguageService();
       $compactViewUrl = (string) $this->uriBuilder->buildUriFromRoute('admiral_cloud_browser_rte_link');
       $rteLinkDownloadLabel = htmlspecialchars((string) $languageService->sL('LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:linkHandler.rteLinkDownload'));
       $buttonText = htmlspecialchars((string) $languageService->sL('LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:browser.button'));
       $titleText = htmlspecialchars((string) $languageService->sL('LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:browser.header'));

       $buttonHtml = [];
       $buttonHtml[] = '<div style="text-align: center;margin-top: 1rem;">'
             . '<span style="display:none"><input id="rteLinkDownload" type="checkbox" style="margin-right: 0.5rem; position: relative; top: 2px;"/>' . $rteLinkDownloadLabel . '</span></div>'
             . '<a href="#" class="btn btn-default t3js-admiral_cloud-browser-btn rte-link"'
             . ' style="margin: 2rem auto;"'
             . ' data-admiral_cloud-browser-url="' . htmlspecialchars($compactViewUrl) . '" '
             . ' data-title="' . htmlspecialchars($titleText) . '">';
       $buttonHtml[] = $this->iconFactory->getIcon('actions-admiral_cloud-browser', IconSize::SMALL)->render();
       $buttonHtml[] = $buttonText;
       $buttonHtml[] = '</a>';

       $this->view->assign('html', LF . implode(LF, $buttonHtml));

       return $this->view->render('LinkBrowser/AdmiralCloud');
   }

   /**
   * @return string[] Array of body-tag attributes
   */
   public function getBodyTagAttributes(): array
   {
       if (count($this->linkParts) === 0 || empty($this->linkParts['url']['pageuid'])) {
           return [];
       }

       return [
           'data-current-link' => $this->linkService->asString([
               'type' => LinkService::TYPE_FILE,
               'file' => $this->linkParts['url']['file'],
           ]),
       ];
   }

   public function getLinkAttributes(): array
   {
      return ['target', 'title', 'class', 'params', 'rel'];
   }

   public function modifyLinkAttributes(array $fieldDefinitions): array
   {
      return $fieldDefinitions;
   }

   /**
   * We don't support updates since there is no difference to simply set the link again.
   */
   public function isUpdateSupported(): bool
   {
      return false;
   }

    public function setView(ViewInterface $view): void
    {
        $this->view = $view;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
