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

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'actions-admiral_cloud-browser' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:admiral_cloud_connector/Resources/Public/Icons/actions-admiral_cloud-browser.svg',
    ],
    'actions-admiral_cloud-browser_invert' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:admiral_cloud_connector/Resources/Public/Icons/actions-admiral_cloud-browser_invert.svg',
    ],
    'permissions-admiral_cloud-browser' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:admiral_cloud_connector/Resources/Public/Icons/permissions-admiral_cloud-browser.svg',
    ]
];
