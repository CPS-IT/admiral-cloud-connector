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

use CPSIT\AdmiralCloudConnector\Exception\InvalidAssetException;
use CPSIT\AdmiralCloudConnector\Exception\InvalidPropertyException;
use CPSIT\AdmiralCloudConnector\Exception\InvalidThumbnailException;
use CPSIT\AdmiralCloudConnector\Exception\NotImplementedException;
use CPSIT\AdmiralCloudConnector\Service\AdmiralCloudService;
use CPSIT\AdmiralCloudConnector\Traits\AdmiralCloudStorage;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Asset
{
    use AdmiralCloudStorage;

    /**
     * Available Types used by AdmiralCloud
     */
    public const TYPE_VIDEO = 'video';
    public const TYPE_IMAGE = 'image';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_AUDIO = 'audio';

    protected const ASSET_TYPE_MIME_TYPE = [
        self::TYPE_VIDEO => ['video'],
        self::TYPE_IMAGE => ['image'],
        self::TYPE_DOCUMENT => ['document', 'application', 'text'],
        self::TYPE_AUDIO => ['audio'],
    ];

    protected ?string $type = null;
    protected ?File $file = null;

    /**
     * @throws InvalidAssetException
     */
    public function __construct(
        protected string $identifier,
        protected ?array $information = null,
    ) {
        if (!static::validateIdentifier($this->identifier)) {
            throw new InvalidAssetException(
                'Invalid identifier given: ' . $this->identifier,
                1558014684521,
            );
        }
    }

    /**
     * Identifier pattern should be a numeric string greater than 0
     */
    protected static function validateIdentifier(string $identifier): bool
    {
        if (!is_numeric($identifier)) {
            return false;
        }

        return (int)$identifier > 0;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function isImage(): bool
    {
        return $this->getAssetType() === self::TYPE_IMAGE;
    }

    public function isVideo(): bool
    {
        return $this->getAssetType() === self::TYPE_VIDEO;
    }

    public function isAudio(): bool
    {
        return $this->getAssetType() === self::TYPE_AUDIO;
    }

    public function isDocument(): bool
    {
        return $this->getAssetType() === self::TYPE_DOCUMENT;
    }

    /**
     * Get asset type from mime type of the file
     */
    public function getAssetType(): string
    {
        if (isset($this->type)) {
            return $this->type;
        }

        $this->type = '';

        $file = $this->getFileIndexRepository()->findOneByStorageAndIdentifier(
            $this->getAdmiralCloudStorage(),
            $this->identifier,
        );

        if ($file) {
            $mimeType = str_replace('admiralCloud/', '', $file['mime_type']);
            [$fileType] = explode('/', $mimeType, 2);

            // Map mime type with asset type
            foreach (static::ASSET_TYPE_MIME_TYPE as $assetType => $mimeTypes) {
                if (in_array($fileType, $mimeTypes, true)) {
                    $this->type = $assetType;
                }
            }
        }

        return $this->type;
    }

    public function getThumbnail(int $storageUid = 0): ?string
    {
        return $this->getAdmiralCloudService()->getThumbnailUrl($this->getFile($storageUid));
    }

    /**
     * @throws NotImplementedException
     */
    public function getPublicUrl(int $storageUid = 0): ?string
    {
        $assetType = $this->getAssetType();
        $file = $this->getFile($storageUid);

        if ($file === null) {
            return null;
        }

        if (isset($GLOBALS['admiralcloud']['fe_group'][$file->getIdentifier()])) {
            $file->setContentFeGroup($GLOBALS['admiralcloud']['fe_group'][$file->getIdentifier()]);
        }

        return match ($assetType) {
            self::TYPE_IMAGE => $this->getAdmiralCloudService()->getImagePublicUrl($file),
            self::TYPE_DOCUMENT => $this->getAdmiralCloudService()->getDocumentPublicUrl($file),
            self::TYPE_AUDIO => $this->getAdmiralCloudService()->getAudioPublicUrl($file),
            self::TYPE_VIDEO => $this->getAdmiralCloudService()->getVideoPublicUrl($file),
            default => throw new NotImplementedException('No public url for asset type ' . $assetType, 1747029997),
        };
    }

    public function getInformation(int $storageUid = 0): array
    {
        if ($this->information === null) {
            try {
                // Do API call
                $this->information = $this->getAdmiralCloudService()->getMediaInfo(
                    [$this->identifier],
                    $storageUid ?: $this->getAdmiralCloudStorage()->getUid(),
                )[$this->identifier] ?? [];
            } catch (\Exception) {
                $this->information = [];
            }
        }

        return $this->information;
    }

    /**
     * Extracts information about a file from the filesystem
     *
     * @param array $propertiesToExtract array of properties which should be returned, if empty all default keys will be extracted
     */
    public function extractProperties(array $propertiesToExtract = []): array
    {
        if (empty($propertiesToExtract)) {
            $propertiesToExtract = [
                'size',
                'atime',
                'mtime',
                'ctime',
                'mimetype',
                'name',
                'extension',
                'identifier',
                'identifier_hash',
                'storage',
                'folder_hash',
            ];
        }
        $fileInformation = [];

        foreach ($propertiesToExtract as $property) {
            $fileInformation[$property] = $this->getSpecificProperty($property);
        }

        return $fileInformation;
    }

    /**
     * Extracts a specific FileInformation from the FileSystem
     */
    public function getSpecificProperty(string $property): mixed
    {
        $information = $this->getInformation();

        return match ($property) {
            'size' => $information['size'] ?? null,
            'atime' => $information['atime'] ?? null,
            'mtime' => $information['mtime'] ?? null,
            'ctime' => $information['ctime'] ?? null,
            'name' => $information['name'] ?? null,
            'mimetype' => $information['mimetype'] ?? null,
            'identifier' => $information['identifier'] ?? null,
            'extension' => $information['extension'] ?? null,
            'identifier_hash' => $information['identifier_hash'] ?? null,
            'storage' => $information['storage'] ?? null,
            'folder_hash' => $information['folder_hash'] ?? null,
            'alternative' => $information['alternative'] ?? null,
            'title' => $information['title'] ?? null,
            'description' => $information['description'] ?? null,
            'width' => $information['width'] ?? null,
            'height' => $information['height'] ?? null,
            'copyright' => $information['copyright'] ?? null,
            'keywords' => $information['keywords'] ?? null,
            default => throw new InvalidPropertyException(sprintf('The information "%s" is not available.', $property), 1519130380),
        };
    }

    /**
     * Save a file to a temporary path and returns that path.
     *
     * @return string|null The temporary path
     * @throws InvalidThumbnailException
     */
    public function getLocalThumbnail(int $storageUid = 0): ?string
    {
        $file = $this->getFile($storageUid);

        if (!$file) {
            return null;
        }

        $url = $this->getThumbnail($storageUid);

        if (empty($url)) {
            return null;
        }

        $temporaryPath = $this->getTemporaryPathForFile($file);

        if (!is_file($temporaryPath)) {
            try {
                $data = GeneralUtility::getUrl($url);
            } catch (\Exception $exception) {
                throw new InvalidThumbnailException(
                    sprintf('Requested url "%s" couldn\'t be found', $url),
                    1558442606611,
                    $exception,
                );
            }

            if (!empty($data)) {
                $result = GeneralUtility::writeFile($temporaryPath, $data);
                if ($result === false) {
                    throw new InvalidThumbnailException(
                        sprintf('Copying file "%s" to temporary path "%s" failed.', $this->getIdentifier(), $temporaryPath),
                        1558442609629,
                    );
                }
            }
        }

        // Return absolute path instead of relative when configured
        return $temporaryPath;
    }

    /**
     * Get file from asset identifier
     */
    public function getFile(int $storageUid = 0): ?File
    {
        if ($this->file) {
            return $this->file;
        }

        $fileData = $this->getFileIndexRepository()->findOneByStorageAndIdentifier(
            $this->getAdmiralCloudStorage(),
            $this->identifier
        );

        if ($fileData) {
            $this->file = GeneralUtility::makeInstance(File::class, $fileData, $this->getAdmiralCloudStorage($storageUid));
        } else {
            $this->file = null;
        }

        return $this->file;
    }

    /**
     * Returns a temporary path for a given file, including the file extension.
     */
    protected function getTemporaryPathForFile(File $file): string
    {
        $temporaryPath = Environment::getPublicPath() . '/typo3temp/assets/' . AdmiralCloudDriver::KEY . '/';

        if (!is_dir($temporaryPath)) {
            GeneralUtility::mkdir_deep($temporaryPath);
        }

        return $temporaryPath . $this->getIdentifier() . '.' . $file->getExtension();
    }

    protected function getAdmiralCloudService(): AdmiralCloudService
    {
        return GeneralUtility::makeInstance(AdmiralCloudService::class);
    }
}
