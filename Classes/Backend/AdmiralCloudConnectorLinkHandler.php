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

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Backend\Controller\AbstractLinkBrowserController;
use TYPO3\CMS\Backend\LinkHandler\LinkHandlerInterface;
use TYPO3\CMS\Backend\LinkHandler\LinkHandlerViewProviderInterface;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\View\ViewInterface;

#[Autoconfigure(public: true)]
class AdmiralCloudConnectorLinkHandler implements LinkHandlerInterface, LinkHandlerViewProviderInterface
{
    protected ViewInterface $view;

    protected array $linkParts = [];

    /**
     * @var class-string<FileInterface>
     */
    protected string $expectedClass = File::class;
    protected string $mode = 'file';

    public function __construct(
        protected readonly LinkService $linkService,
        protected readonly PageRenderer $pageRenderer,
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

        if (\is_object($linkParts['url'][$this->mode] ?? null)
            && is_a($linkParts['url'][$this->mode], $this->expectedClass, true)
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

    public function createView(BackendViewFactory $backendViewFactory, ServerRequestInterface $request): ViewInterface
    {
        return $backendViewFactory->create($request, ['cpsit/admiral-cloud-connector']);
    }

    public function setView(ViewInterface $view): self
    {
        $this->view = $view;

        return $this;
    }

    public function getView(): ViewInterface
    {
        return $this->view;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
