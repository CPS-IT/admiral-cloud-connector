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

final readonly class ConfigurationUtility
{
    public const EXTENSION = 'admiral_cloud_connector';

    public static function getDefaultImageWidth(): int
    {
        return 2000;
    }

    public static function isProduction(): bool
    {
        if (getenv('ADMIRALCLOUD_IS_PRODUCTION')) {
            return true;
        }

        return false;
    }

    public static function getApiUrl(): string
    {
        $add = '';

        if (!self::isProduction()) {
            $add = 'dev';
        }

        return 'https://api' . $add . '.admiralcloud.com/';
    }

    public static function getAuthUrl(): string
    {
        $add = '';

        if (!self::isProduction()) {
            $add = 'dev';
        }

        return 'https://auth' . $add . '.admiralcloud.com/';
    }

    public static function getSmartcropUrl(): string
    {
        if (!self::isProduction()) {
            return 'https://smartcropdev.admiralcloud.com/';
        }

        return 'https://images.admiralcloud.com/';
    }

    public static function getImageUrl(): string
    {
        $add = '';

        if (!self::isProduction()) {
            $add = 'dev';
        }

        return 'https://images' . $add . '.admiralcloud.com/';
    }

    public static function getThumbnailUrl(): string
    {
        $add = '';

        if (!self::isProduction()) {
            $add = 'dev';
        }

        return 'https://images' . $add . '.admiralcloud.com/';
    }

    public static function getIframeUrl(): string
    {
        if (!self::isProduction()) {
            return 'https://t3intpoc.admiralcloud.com/';
        }

        return getenv('ADMIRALCLOUD_IFRAMEURL') ?: 'https://t3prod.admiralcloud.com/';
    }

    public static function getDirectFileUrl(): string
    {
        $add = '';

        if (!self::isProduction()) {
            $add = 'dev';
        }

        return 'https://filehub' . $add . '.admiralcloud.com/v5/deliverFile/';
    }

    public static function getPlayerFileUrl(): string
    {
        $add = '';

        if (!self::isProduction()) {
            $add = 'dev';
        }

        return 'https://player' . $add . '.admiralcloud.com/?v=';
    }

    public static function getImagePlayerConfigId(): int
    {
        return (int)(getenv('ADMIRALCLOUD_IMAGE_CONFIG_ID') ?: 3);
    }

    public static function getImagePNGPlayerConfigId(): int
    {
        return (int)(getenv('ADMIRALCLOUD_IMAGE_PNG_CONFIG_ID') ?: 3);
    }

    public static function getVideoPlayerConfigId(): int
    {
        return (int)(getenv('ADMIRALCLOUD_VIDEO_CONFIG_ID') ?: 2);
    }

    public static function getDocumentPlayerConfigId(): int
    {
        return (int)(getenv('ADMIRALCLOUD_DOCUMENT_CONFIG_ID') ?: 5);
    }

    public static function getAudioPlayerConfigId(): int
    {
        return (int)(getenv('ADMIRALCLOUD_AUDIO_CONFIG_ID') ?: 4);
    }

    public static function getAuthImagePlayerConfigId(): int
    {
        return (int)getenv('ADMIRALCLOUD_IMAGE_AUTH_CONFIG_ID');
    }

    public static function getAuthVideoPlayerConfigId(): int
    {
        return (int)getenv('ADMIRALCLOUD_VIDEO_AUTH_CONFIG_ID');
    }

    public static function getAuthDocumentPlayerConfigId(): int
    {
        return (int)getenv('ADMIRALCLOUD_DOCUMENT_AUTH_CONFIG_ID');
    }

    public static function getAuthAudioPlayerConfigId(): int
    {
        return (int)getenv('ADMIRALCLOUD_AUDIO_AUTH_CONFIG_ID');
    }

    public static function getFlagPlayerConfigId(): int
    {
        return (int)getenv('ADMIRALCLOUD_FLAG_CONFIG_ID');
    }

    public static function getMetaTitleField(): string
    {
        return getenv('ADMIRALCLOUD_METADATA_FIELD_OVERRIDE_title') ?: 'container_name';
    }

    public static function getMetaAlternativeField(): string
    {
        return getenv('ADMIRALCLOUD_METADATA_FIELD_OVERRIDE_alternative') ?: 'meta_alttag';
    }

    public static function getMetaDescriptionField(): string
    {
        return getenv('ADMIRALCLOUD_METADATA_FIELD_OVERRIDE_description') ?: 'container_description';
    }

    public static function getMetaCopyrightField(): string
    {
        return getenv('ADMIRALCLOUD_METADATA_FIELD_OVERRIDE_copyright') ?: 'meta_iptc_copyrightNotice';
    }

    /**
     * Checks, if a given mime type is an AdmiralCloud svg mime type.
     *
     * @param string $mimeType The mime type to check for
     * @return bool            Whether it's an AdmiralCloud svg mime type or not
     */
    public static function isSvgMimeType(string $mimeType): bool
    {
        return (bool)preg_match('/^admiralCloud\/image\/svg(\+xml)?$/', $mimeType);
    }

    public static function getPlayerConfigurationIdByType(string $type): int
    {
        return match ($type) {
            'audio' => self::getAudioPlayerConfigId(),
            'video' => self::getVideoPlayerConfigId(),
            'document' => self::getDocumentPlayerConfigId(),
            default => self::getImagePlayerConfigId(),
        };
    }

    public static function getLocalFileUrl(): string
    {
        return '/filehub/deliverFile/';
    }
}
