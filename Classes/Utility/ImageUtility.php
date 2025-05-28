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

namespace CPSIT\AdmiralCloudConnector\Utility;

use TYPO3\CMS\Core\Resource\FileInterface;

final readonly class ImageUtility
{
    /**
     * Calculate dimension based on image ratio and cropped data
     *
     * For instance you have the original width, height and new width.
     * And want to calculate the new height with the same ratio as the original dimensions
     *
     * WARNING: Don't forget to set setTxAdmiralCloudConnectorCrop for the file before if the crop information is wanted
     *
     * @return \stdClass [width, height]
     */
    public static function calculateDimensions(
        FileInterface $file,
        int|string|null $width = null,
        int|string|null $height = null,
        int|string|null $maxWidth = null,
        int|string|null $maxHeight = null,
    ): \stdClass {
        $width = (int)$width;
        $height = (int)$height;
        $maxWidth = (int)$maxWidth;
        $maxHeight = (int)$maxHeight;
        $originalWidth = self::getFileWidthWithCropInformation($file);
        $originalHeight = self::getFileHeightWithCropInformation($file);

        // Create object to be returned
        $finalDimensions = new \stdClass();
        $finalDimensions->width = $width;
        $finalDimensions->height = $height;

        // If width and height are not set, get dimensions from original file
        if ($finalDimensions->width === 0 && $finalDimensions->height === 0) {
            $finalDimensions->width = $originalWidth;
            $finalDimensions->height = $originalHeight;
        }

        // Adjust dimensions for maximal width and height
        $finalDimensions = self::adjustDimensionsForMaxWidth($maxWidth, $finalDimensions);
        $finalDimensions = self::adjustDimensionsForMaxHeight($maxHeight, $finalDimensions);

        // Set height if is not defined
        if ($finalDimensions->height === 0 && $finalDimensions->width > 0 && $originalWidth > 0) {
            $finalDimensions->height = (int)floor($finalDimensions->width / $originalWidth * $originalHeight);
            $finalDimensions = self::adjustDimensionsForMaxHeight($maxHeight, $finalDimensions);
        }

        // Set width if is not defined
        if ($finalDimensions->width === 0 && $finalDimensions->height > 0 && $originalHeight > 0) {
            $finalDimensions->width = (int)floor($finalDimensions->height / $originalHeight * $originalWidth);
            $finalDimensions = self::adjustDimensionsForMaxWidth($maxWidth, $finalDimensions);
        }

        return $finalDimensions;
    }

    private static function getFileWidthWithCropInformation(FileInterface $file): int
    {
        $crop = self::getCropInformation($file);
        $fileWidth = (int)$crop?->cropData->width;

        if (empty($fileWidth)) {
            $fileWidth = (int)$file->getProperty('width');
        }

        return $fileWidth;
    }

    private static function getFileHeightWithCropInformation(FileInterface $file): int
    {
        $crop = self::getCropInformation($file);
        $fileHeight = (int)$crop?->cropData->height;

        if (empty($fileHeight)) {
            $fileHeight = (int)$file->getProperty('height');
        }

        return $fileHeight;
    }

    private static function adjustDimensionsForMaxWidth(int $maxWidth, \stdClass $dimensions): \stdClass
    {
        if ($maxWidth && $dimensions->width > $maxWidth) {
            if ($dimensions->width) {
                $dimensions->height = (int)floor($maxWidth / $dimensions->width * $dimensions->height);
            }

            $dimensions->width = $maxWidth;
        }

        return $dimensions;
    }

    private static function adjustDimensionsForMaxHeight(int $maxHeight, \stdClass $dimensions): \stdClass
    {
        if ($maxHeight && $dimensions->height > $maxHeight) {
            if ($dimensions->width) {
                $dimensions->width = (int)floor($maxHeight / $dimensions->height * $dimensions->width);
            }

            $dimensions->height = $maxHeight;
        }

        return $dimensions;
    }

    private static function getCropInformation(FileInterface $file): ?\stdClass
    {
        if (is_callable([$file, 'getTxAdmiralCloudConnectorCrop'])) {
            $crop = $file->getTxAdmiralCloudConnectorCrop();
        } else {
            $crop = $file->getProperty('tx_admiralcloudconnector_crop');
        }

        if (!is_string($crop)) {
            return null;
        }

        return json_decode($crop) ?: null;
    }
}
