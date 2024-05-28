<?php


namespace CPSIT\AdmiralCloudConnector\Resource;

use CPSIT\AdmiralCloudConnector\Service\AdmiralCloudService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ProcessedFile
 * @package CPSIT\AdmiralCloudConnector\Resource
 */
class ProcessedFile extends \TYPO3\CMS\Core\Resource\ProcessedFile
{
    /**
     * @inheritDoc
     */
    public function getPublicUrl($relativeToCurrentScript = false): ?string
    {
        if (str_starts_with($this->getOriginalFile()->getMimeType(), 'admiralCloud/')) {
            if($this->getProcessingConfiguration()['width'] ?? null){
                $this->properties['width'] = (int) $this->getProcessingConfiguration()['width'];
            }
            $this->properties['height'] = (int) ($this->getProcessingConfiguration()['height'] ?? 0);

            return $this->getAdmiralCloudService()->getImagePublicUrl(
                $this->getOriginalFile(),
                (int)($this->properties['width'] ?? 0),
                $this->properties['height']
            );
        }

        return parent::getPublicUrl($relativeToCurrentScript);
    }

    /**
     * @return AdmiralCloudService
     */
    protected function getAdmiralCloudService(): AdmiralCloudService
    {
        return GeneralUtility::makeInstance(AdmiralCloudService::class);
    }
}
