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

namespace CPSIT\AdmiralCloudConnector\Resource;

use CPSIT\AdmiralCloudConnector\Utility\PermissionUtility;

class FileReference extends \TYPO3\CMS\Core\Resource\FileReference
{
    public function getPublicUrl(): ?string
    {
        $file = $this->originalFile;

        if (str_starts_with($file->getMimeType(), 'admiralCloud/')) {
            $fe_group = PermissionUtility::getPageFeGroup();

            if (!$fe_group && $this->getProperty('tablenames') === 'tt_content' && $this->getProperty('uid_foreign')) {
                $fe_group = PermissionUtility::getContentFeGroupFromReference($this->getProperty('uid_foreign'));
            }

            $GLOBALS['admiralcloud']['fe_group'][$file->getIdentifier()] = $fe_group;
        }

        $publicUrl = $file->getPublicUrl();

        unset($GLOBALS['admiralcloud']['fe_group'][$file->getIdentifier()]);

        return $publicUrl;
    }
}
