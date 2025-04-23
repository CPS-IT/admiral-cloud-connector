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

namespace CPSIT\AdmiralCloudConnector\Backend\ToolbarItems;

use CPSIT\AdmiralCloudConnector\Api\AdmiralCloudApi;
use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * Class AdmiralCloudToolbarItem
 */
readonly class AdmiralCloudToolbarItem implements ToolbarItemInterface
{
    public function __construct(
        protected ViewFactoryInterface $viewFactory,
    ) {}

    public function checkAccess(): bool
    {
        return true;
    }

    public function getItem(): string
    {
        $data = new ViewFactoryData(
            templatePathAndFilename: 'EXT:admiral_cloud_connector/Resources/Private/Templates/ToolbarMenu/MenuItem.html',
        );

        $view = $this->viewFactory->create($data);
        $view->assign('ACGroup', AdmiralCloudApi::getSecurityGroup());

        return $view->render();
    }

    public function hasDropDown(): bool
    {
        return true;
    }

    public function getDropDown(): string
    {
        $data = new ViewFactoryData(
            templatePathAndFilename: 'EXT:admiral_cloud_connector/Resources/Private/Templates/ToolbarMenu/DropDown.html',
        );

        return $this->viewFactory->create($data)->render();
    }

    public function getAdditionalAttributes(): array
    {
        return [];
    }

    public function getIndex(): int
    {
        return 50;
    }
}
