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

use CPSIT\AdmiralCloudConnector\Backend\ToolbarItems\AdmiralCloudToolbarItem;
use CPSIT\AdmiralCloudConnector\Utility\ConfigurationUtility;
use CPSIT\AdmiralCloudConnector\Utility\PermissionUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

readonly class AdmiralCloudMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // It is not possible to implement this in ext_localconf.php because it is necessary to know the current user
        // and that happens in the middleware before
        if (PermissionUtility::userHasPermissionForAdmiralCloud()) {
            $enableCss = (int)($this->getBackendUser()?->getTSConfig()['admiralcloud.']['enableCss'] ?? 0) === 1;

            // Don't use CSS adjustment for admins
            if (!($this->getBackendUser() && ($this->getBackendUser()->isAdmin() || $enableCss))) {
                // Register as a skin
                $GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets'] [ConfigurationUtility::EXTENSION] = [
                    'name' => ConfigurationUtility::EXTENSION,
                    'stylesheetDirectories' => [
                        'css' => 'EXT:admiral_cloud_connector/Resources/Public/Backend/Css/',
                    ],
                ];
            }

            // Add toolbar item to close AdmiralCloud connection
            $GLOBALS['TYPO3_CONF_VARS']['BE']['toolbarItems'][] = AdmiralCloudToolbarItem::class;
        }

        return $handler->handle($request);
    }

    protected function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }
}
