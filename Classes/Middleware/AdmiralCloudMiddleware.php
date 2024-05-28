<?php

namespace CPSIT\AdmiralCloudConnector\Middleware;

use CPSIT\AdmiralCloudConnector\Backend\ToolbarItems\AdmiralCloudToolbarItem;
use CPSIT\AdmiralCloudConnector\Utility\ConfigurationUtility;
use CPSIT\AdmiralCloudConnector\Utility\PermissionUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

class AdmiralCloudMiddleware implements MiddlewareInterface
{
    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // It is not possible to implement this in ext_localconf.php because it is necessary to know the current user
        // and that happens in the middleware before
        if (PermissionUtility::userHasPermissionForAdmiralCloud()) {
            $enableCss = false;
            if($this->getBackendUser() && isset($this->getBackendUser()->getTSConfig()['admiralcloud.']['enableCss']) &&
                $this->getBackendUser()->getTSConfig()['admiralcloud.']['enableCss'] == 1){
                $enableCss = true;
            }
            // Don't use CSS adjustment for admins
            if (!($this->getBackendUser() && ($this->getBackendUser()->isAdmin() || $enableCss))) {
                // Register as a skin
                $GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets'] [ConfigurationUtility::EXTENSION] = [
                    'name' => ConfigurationUtility::EXTENSION,
                    'stylesheetDirectories' => [
                        'css' => 'EXT:admiral_cloud_connector/Resources/Public/Backend/Css/'
                    ]
                ];
            }

            // Add toolbar item to close AdmiralCloud connection
            $GLOBALS['TYPO3_CONF_VARS']['BE']['toolbarItems'][] = AdmiralCloudToolbarItem::class;
        }

        return $handler->handle($request);
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
