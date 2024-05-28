<?php
declare(strict_types = 1);
namespace CPSIT\AdmiralCloudConnector\Http\Middleware;

/**
 * This file extends the redirect handler of the TYPO3 CMS project.
 *
 * 1. Option to keep request uri path which are builded with slugs
 */

use CPSIT\AdmiralCloudConnector\Service\AdmiralCloudService;
use CPSIT\AdmiralCloudConnector\Utility\ConfigurationUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Hooks into the frontend request, and checks if a redirect should apply,
 * If so, a redirect response is triggered.
 *
 * @internal
 */
class ReadableLinkResolver extends \TYPO3\CMS\Redirects\Http\Middleware\RedirectHandler
{
    /**
     * First hook within the Frontend Request handling
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (0 === strpos($path, ConfigurationUtility::getLocalFileUrl())) {
            preg_match('/.*?\/.*?\/([a-z0-9]{8}\-[a-z0-9]{4}\-[a-z0-9]{4}\-[a-z0-9]{4}\-[a-z0-9]{12})\/(\d+).*/',$path,$matches);
            if(isset($matches[1]) && isset($matches[2])){
                /** @var AdmiralCloudService $admiralCloudService */
                $admiralCloudService = GeneralUtility::makeInstance(AdmiralCloudService::class);
                $value = $request->getParsedBody()['download'] ?? $request->getQueryParams()['download'] ?? null;
                $url = $admiralCloudService->getDirectPublicUrlForHash(
                    $matches[1],
                    (bool) $value
                );
                $file = $admiralCloudService->getStorage()->getFile($matches[2]);
                if ($file) {
                    $this->renderTemplateToFile('Middleware/DownloadFile', [
                            'file' => $file,
                            'requestUri' => $request->getUri(),
                            'url' => $url,
                            'image' => ConfigurationUtility::getImageUrl() . 'v3/deliverEmbed/' . $matches[1] . '/image/'
                        ]
                    );
                } else {
                    header("Location: " . $url);
                }
            }
        }

        return $handler->handle($request);
    }

    /**
     * Render template to file.
     *
     * @param string $templateName
     * @param array  $variables
     * @param string $htaccessFile
     */
    protected function renderTemplateToFile(string $templateName, array $variables)
    {
        /** @var StandaloneView $renderer */
        $renderer = GeneralUtility::makeInstance(StandaloneView::class);
        $renderer->setLayoutRootPaths(array(GeneralUtility::getFileAbsFileName('EXT:admiral_cloud_connector/Resources/Private/Layouts')));
        $renderer->setPartialRootPaths(array(GeneralUtility::getFileAbsFileName('EXT:admiral_cloud_connector/Resources/Private/Partials')));
        $renderer->setTemplateRootPaths(array(GeneralUtility::getFileAbsFileName('EXT:admiral_cloud_connector/Resources/Private/Templates')));
        $renderer->setTemplate($templateName);
        $renderer->assignMultiple($variables);
        $content = \trim((string)$renderer->render());
        echo $content;
        die();
    }
}
