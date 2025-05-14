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

namespace CPSIT\AdmiralCloudConnector\Resource\Index;

use CPSIT\AdmiralCloudConnector\Resource\AdmiralCloudDriver;
use CPSIT\AdmiralCloudConnector\Traits\AssetFactory;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Resource;

#[Autoconfigure(public: true)]
class Extractor implements Resource\Index\ExtractorInterface
{
    use AssetFactory;

    public function __construct(\CPSIT\AdmiralCloudConnector\Resource\AssetFactory $assetFactory)
    {
        $this->assetFactory = $assetFactory;
    }

    public function getFileTypeRestrictions(): array
    {
        return [];
    }

    public function getDriverRestrictions(): array
    {
        return [AdmiralCloudDriver::KEY];
    }

    public function getPriority(): int
    {
        return 10;
    }

    public function getExecutionPriority(): int
    {
        return 10;
    }

    public function canProcess(Resource\File $file): bool
    {
        return str_starts_with($file->getMimeType(), 'admiralCloud/');
    }

    public function extractMetaData(Resource\File $file, array $previousExtractedData = []): array
    {
        $asset = $this->getAsset($file->getIdentifier());
        $expectedData = [
            'alternative',
            'title',
            'description',
            'copyright',
            'keywords',
        ];

        if ($asset->isImage() || $asset->isDocument()) {
            $expectedData[] = 'height';
            $expectedData[] = 'width';
        }

        $meta = $asset->extractProperties($expectedData);

        if ($asset->isDocument()) {
            $meta['link'] = 't3://file?uid=' . $file->getUid();
        }

        return $meta;
    }
}
