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

/**
 * @internal
 */
enum AdmiralCloudFileControl
{
    case Overview;
    case Upload;

    public function ajaxRoute(): string
    {
        return match ($this) {
            self::Overview => 'admiral_cloud_browser_show',
            self::Upload => 'admiral_cloud_browser_upload',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Overview => 'admiral_cloud_connector.be:browser.button',
            self::Upload => 'admiral_cloud_connector.be:browser.uploadbutton',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Overview => 'overview',
            self::Upload => 'upload',
        };
    }
}
