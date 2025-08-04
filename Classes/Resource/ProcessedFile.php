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

use CPSIT\AdmiralCloudConnector\Service\AdmiralCloudService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ProcessedFile extends \TYPO3\CMS\Core\Resource\ProcessedFile
{
    public function getPublicUrl(): ?string
    {
        if (str_starts_with($this->getOriginalFile()->getMimeType(), 'admiralCloud/')) {
            if ($this->getProcessingConfiguration()['width'] ?? null) {
                $this->properties['width'] = (int)$this->getProcessingConfiguration()['width'];
            }

            $this->properties['height'] = (int)($this->getProcessingConfiguration()['height'] ?? 0);

            return $this->getAdmiralCloudService()->getImagePublicUrl(
                $this->getOriginalFile(),
                (int)($this->properties['width'] ?? 0),
                $this->properties['height'],
            );
        }

        return parent::getPublicUrl();
    }

    protected function getAdmiralCloudService(): AdmiralCloudService
    {
        return GeneralUtility::makeInstance(AdmiralCloudService::class);
    }
}
