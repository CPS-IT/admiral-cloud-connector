<?php

namespace CPSIT\AdmiralCloudConnector\Resource\Index;

use CPSIT\AdmiralCloudConnector\Resource\AdmiralCloudDriver;
use CPSIT\AdmiralCloudConnector\Traits\AssetFactory;
use TYPO3\CMS\Core\Resource;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Extractor
 */
class Extractor implements Resource\Index\ExtractorInterface
{
    use AssetFactory;

    /**
     * @return array
     */
    public function getFileTypeRestrictions(): array
    {
        return [];
    }

    /**
     * @return array
     */
    public function getDriverRestrictions(): array
    {
        return [AdmiralCloudDriver::KEY];
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return 10;
    }

    /**
     * @return int
     */
    public function getExecutionPriority(): int
    {
        return 10;
    }

    /**
     * @param Resource\File $file
     * @return bool
     */
    public function canProcess(Resource\File $file): bool
    {
        return str_starts_with($file->getMimeType(), 'admiralCloud/');
    }

    /**
     * Extract metadata of AdmiralCloud assets
     *
     * @param Resource\File $file
     * @param array $previousExtractedData
     * @return array
     */
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
