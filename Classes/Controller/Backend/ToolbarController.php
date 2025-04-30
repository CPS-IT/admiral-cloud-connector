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

namespace CPSIT\AdmiralCloudConnector\Controller\Backend;

use CPSIT\AdmiralCloudConnector\Service\MetadataService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Http\JsonResponse;

#[AsController]
readonly class ToolbarController
{
    public function __construct(
        protected MetadataService $metadataService,
    ) {}

    /**
     * Update metadata for changed files in AdmiralCloud
     * This function will be called per Ajax in the toolbar
     */
    public function updateChangedMetadataAction(): ResponseInterface
    {
        try {
            $this->metadataService->updateLastChangedMetadatas();
            $jsonArray = ['message' => 'ok'];
            $statusCode = 200;
        } catch (\Throwable $exception) {
            $jsonArray = ['message' => $exception->getMessage()];
            $statusCode = 500;
        }

        return new JsonResponse($jsonArray, $statusCode);
    }
}
