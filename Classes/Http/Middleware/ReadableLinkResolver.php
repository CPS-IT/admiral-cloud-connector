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

namespace CPSIT\AdmiralCloudConnector\Http\Middleware;

use CPSIT\AdmiralCloudConnector\Service\AdmiralCloudService;
use CPSIT\AdmiralCloudConnector\Utility\ConfigurationUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * 1. Option to keep request uri path which are built with slugs
 */
readonly class ReadableLinkResolver implements MiddlewareInterface
{
    public function __construct(
        protected AdmiralCloudService $admiralCloudService,
        protected ViewFactoryInterface $viewFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (str_starts_with($path, ConfigurationUtility::getLocalFileUrl())) {
            preg_match('/.*?\/.*?\/([a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12})\/(\d+).*/', $path, $matches);

            if (isset($matches[1], $matches[2])) {
                $url = $this->admiralCloudService->getDirectPublicUrlForHash($matches[1]);
                /** @var FileInterface|null $file */
                $file = $this->admiralCloudService->getStorage()->getFile($matches[2]);

                if ($file) {
                    $content = $this->renderTemplateToFile(
                        'Middleware/DownloadFile',
                        [
                            'file' => $file,
                            'requestUri' => $request->getUri(),
                            'url' => $url,
                            'image' => ConfigurationUtility::getImageUrl() . 'v5/deliverEmbed/' . $matches[1] . '/image/',
                        ],
                    );

                    return new HtmlResponse($content);
                }

                return new RedirectResponse($url);
            }
        }

        return $handler->handle($request);
    }

    protected function renderTemplateToFile(string $templateName, array $variables): string
    {
        $data = new ViewFactoryData(
            ['EXT:admiral_cloud_connector/Resources/Private/Templates'],
            ['EXT:admiral_cloud_connector/Resources/Private/Partials'],
            ['EXT:admiral_cloud_connector/Resources/Private/Layouts'],
        );
        $view = $this->viewFactory->create($data);
        $view->assignMultiple($variables);

        return \trim($view->render($templateName));
    }
}
