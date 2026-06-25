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

namespace CPSIT\AdmiralCloudConnector\Event;

/**
 * ToolbarLinkEvent
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
final class ToolbarLinkEvent
{
    public function __construct(
        public ?string $url = 'https://app.admiralcloud.com/',
        public string $label = 'LLL:EXT:admiral_cloud_connector/Resources/Private/Language/locallang_be.xlf:toolbarItem.openApp.title',
    ) {}

    public function disableLink(): void
    {
        $this->url = null;
    }
}
