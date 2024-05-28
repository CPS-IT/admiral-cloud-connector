<?php


namespace CPSIT\AdmiralCloudConnector\Resource;

use CPSIT\AdmiralCloudConnector\Exception\InvalidArgumentException;
use CPSIT\AdmiralCloudConnector\Exception\InvalidAssetException;
use CPSIT\AdmiralCloudConnector\Exception\InvalidPropertyException;
use CPSIT\AdmiralCloudConnector\Exception\InvalidThumbnailException;
use CPSIT\AdmiralCloudConnector\Exception\NotImplementedException;
use CPSIT\AdmiralCloudConnector\Resource\Index\FileIndexRepository;
use CPSIT\AdmiralCloudConnector\Service\AdmiralCloudService;
use CPSIT\AdmiralCloudConnector\Traits\AdmiralCloudStorage;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\ResourceStorageInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/**
 * Class Asset
 * @package CPSIT\AdmiralCloudConnector\Resource
 */
class Asset
{
    use AdmiralCloudStorage;

    /**
     * Available Types used by AdmiralCloud
     */
    const TYPE_VIDEO = 'video';
    const TYPE_IMAGE = 'image';
    const TYPE_DOCUMENT = 'document';
    const TYPE_AUDIO = 'audio';

    protected const ASSET_TYPE_MIME_TYPE = [
        self::TYPE_VIDEO => ['video'],
        self::TYPE_IMAGE => ['image'],
        self::TYPE_DOCUMENT => ['document', 'application', 'text'],
        self::TYPE_AUDIO => ['audio'],
    ];

    /**
     * @var int
     */
    protected $identifier;

    /**
     * API Data
     * @var array
     */
    protected $information;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var File
     */
    protected $file;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    public function injectEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Asset constructor.
     * @param string $identifier
     * @param array $properties
     * @throws InvalidAssetException
     */
    public function __construct(string $identifier, array $information = [])
    {
        if (!static::validateIdentifier($identifier)) {
            throw new InvalidAssetException(
                'Invalid identifier given: ' . $identifier,
                1558014684521
            );
        }

        $this->identifier = $identifier;
        if ($information) {
            $this->information = $information;
        }
    }

    /**
     * Identifier patern should be bigger than 0
     * @param int $identifier
     * @return bool
     */
    public static function validateIdentifier(int $identifier): bool
    {
        return $identifier > 0;
    }

    /**
     * @return int
     */
    public function getIdentifier(): int
    {
        return $this->identifier;
    }

    /**
     * @param int $storageUid
     * @return bool
     */
    public function isImage(int $storageUid = 0): bool
    {
        return $this->getAssetType($storageUid) === self::TYPE_IMAGE;
    }

    /**
     * @return bool
     */
    public function isVideo(int $storageUid = 0): bool
    {
        return $this->getAssetType($storageUid) === self::TYPE_VIDEO;
    }

    /**
     * @return bool
     */
    public function isAudio(int $storageUid = 0): bool
    {
        return $this->getAssetType($storageUid) === self::TYPE_AUDIO;
    }

    /**
     * @return bool
     */
    public function isDocument(int $storageUid = 0): bool
    {
        return $this->getAssetType($storageUid) === self::TYPE_DOCUMENT;
    }

    /**
     * Get asset type from mime type of the file
     *
     * @param int $storageUid
     * @return string
     */
    public function getAssetType(int $storageUid = 0): string
    {
        if (isset($this->type)) {
            return $this->type;
        }

        $this->type = '';


        if(version_compare(\TYPO3\CMS\Core\Utility\VersionNumberUtility::getCurrentTypo3Version(), '11.5.0', '<')){
            /** @var File $file */
            $file = $this->getFileIndexRepository()->findOneByStorageUidAndIdentifier(
                ($storageUid ? $storageUid : $this->getAdmiralCloudStorage()->getUid()),
                $this->identifier
            );
        } else {
            $file = $this->getFileIndexRepository()->findOneByStorageAndIdentifier(
                $this->getAdmiralCloudStorage(),
                $this->identifier
            );
        }

        if ($file) {
            $mimeType = str_replace('admiralCloud/', '', $file['mime_type']);
            [$fileType, $subType] = explode('/', $mimeType);

            // Map mime type with asset type
            foreach (static::ASSET_TYPE_MIME_TYPE as $assetType => $mimeTypes) {
                if (in_array($fileType, $mimeTypes, true)) {
                    $this->type = $assetType;
                }
            }
        }

        return $this->type;
    }

    /**
     * @param int $storageUid
     * @return string|null
     */
    public function getThumbnail(int $storageUid = 0): ?string
    {
        return $this->getAdmiralCloudService()->getThumbnailUrl($this->getFile($storageUid));
    }

    /**
     * @param int $storageUid
     * @return string|null
     * @throws NotImplementedException
     */
    public function getPublicUrl(int $storageUid = 0): ?string
    {
        $assetType = $this->getAssetType($storageUid);
        $file = $this->getFile($storageUid);
        if(isset($GLOBALS['admiralcloud']['fe_group'][$file->getIdentifier()])){
            $file->setContentFeGroup($GLOBALS['admiralcloud']['fe_group'][$file->getIdentifier()]);
        }
            
        switch ($assetType) {
            case self::TYPE_IMAGE:
                $publicUrl = $this->getAdmiralCloudService()->getImagePublicUrl($file);
                break;
            case self::TYPE_DOCUMENT:
                $publicUrl = $this->getAdmiralCloudService()->getDocumentPublicUrl($file);
                break;
            case self::TYPE_AUDIO:
                $publicUrl = $this->getAdmiralCloudService()->getAudioPublicUrl($file);
                break;
            case self::TYPE_VIDEO:
                $publicUrl = $this->getAdmiralCloudService()->getVideoPublicUrl($file);
                break;
            default:
                throw new NotImplementedException('No public url for asset type ' . $assetType);
                break;
        }

        return $publicUrl;
    }

    /**
     * @param int $storageUid
     * @return array
     */
    public function getInformation(int $storageUid = 0): array
    {
        if ($this->information === null) {
            try {
                // Do API call
                $this->information = $this->getAdmiralCloudService()->getMediaInfo(
                    [$this->identifier],
                    ($storageUid?:$this->getAdmiralCloudStorage()->getUid())
                )[$this->identifier] ?? [];
            } catch (\Exception $e) {
                $this->information = [];
            }
        }
        return $this->information;
    }

    /**
     * Extracts information about a file from the filesystem
     *
     * @param array $propertiesToExtract array of properties which should be returned, if empty all default keys will be extracted
     * @return array
     */
    public function extractProperties($propertiesToExtract = []): array
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
                'folder_hash'
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
     *
     * @param string $property
     * @return bool|int|string
     */
    public function getSpecificProperty($property)
    {
        $information = $this->getInformation();
        switch ($property) {
            case 'size':
                return $information['size'] ?? null;
            case 'atime':
                return $information['atime'] ?? null;
            case 'mtime':
                return $information['mtime'] ?? null;
            case 'ctime':
                return $information['ctime'] ?? null;
            case 'name':
                return $information['name'] ?? null;
            case 'mimetype':
                return $information['mimetype'] ?? null;
            case 'identifier':
                return $information['identifier'] ?? null;
            case 'extension':
                return $information['extension'] ?? null;
            case 'identifier_hash':
                return $information['identifier_hash'] ?? null;
            case 'storage':
                return $information['storage'] ?? null;
            case 'folder_hash':
                return $information['folder_hash'] ?? null;

            // Metadata
            case 'alternative':
                return $information['alternative'] ?? null;
            case 'title':
                return $information['title'] ?? null;
            case 'description':
                return $information['description'] ?? null;
            case 'width':
                return $information['width'] ?? null;
            case 'height':
                return $information['height'] ?? null;
            case 'copyright':
                return $information['copyright'] ?? null;
            case 'keywords':
                return $information['keywords'] ?? null;
            default:
                throw new InvalidPropertyException(sprintf('The information "%s" is not available.', $property), 1519130380);
        }
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
        if (!empty($url)) {
            $temporaryPath = $this->getTemporaryPathForFile($url, $file);
            if (!is_file($temporaryPath)) {
                try {
                    $data = GeneralUtility::getUrl($url);
                } catch (\Exception $e) {
                    throw new InvalidThumbnailException(
                        sprintf('Requested url "%s" couldn\'t be found', $url),
                        1558442606611,
                        $e
                    );
                }
                if (!empty($data)) {
                    $result = GeneralUtility::writeFile($temporaryPath, $data);
                    if ($result === false) {
                        throw new InvalidThumbnailException(
                            sprintf('Copying file "%s" to temporary path "%s" failed.', $this->getIdentifier(), $temporaryPath),
                            1558442609629
                        );
                    }
                }
            }

            // Return absolute path instead of relative when configured
            return $temporaryPath;
        }
        return $temporaryPath ?? null;
    }

    /**
     * Get file from asset identifier
     *
     * @param int $storageUid
     * @return File
     */
    public function getFile(int $storageUid = 0): ?File
    {
        if ($this->file) {
            return $this->file;
        }

        if(version_compare(\TYPO3\CMS\Core\Utility\VersionNumberUtility::getCurrentTypo3Version(), '11.5.0', '<')){
            $fileData = $this->getFileIndexRepository()->findOneByStorageUidAndIdentifier(
                ($storageUid ?: $this->getAdmiralCloudStorage()->getUid()),
                $this->identifier
            );
        } else {
            $fileData = $this->getFileIndexRepository()->findOneByStorageAndIdentifier(
                $this->getAdmiralCloudStorage(),
                $this->identifier
            );
        }



        if ($fileData) {
            $this->file = GeneralUtility::makeInstance(File::class, $fileData, $this->getAdmiralCloudStorage($storageUid));
        } else {
            $this->file = null;
        }

        return $this->file;
    }

    /**
     * Returns a temporary path for a given file, including the file extension.
     *
     * @param string $url
     * @param File $file
     * @return string
     */
    protected function getTemporaryPathForFile($url, File $file): string
    {
        $temporaryPath = Environment::getPublicPath() . '/typo3temp/assets/' . AdmiralCloudDriver::KEY . '/';
        if (!is_dir($temporaryPath)) {
            GeneralUtility::mkdir_deep($temporaryPath);
        }

        return $temporaryPath . $this->getIdentifier() . '.' . $file->getExtension();
    }

    /**
     * @return AdmiralCloudService
     */
    protected function getAdmiralCloudService()
    {
        return GeneralUtility::makeInstance(AdmiralCloudService::class);
    }

    /**
     * @return FileIndexRepository
     */
    protected function getFileIndexRepository()
    {
        if(version_compare(\TYPO3\CMS\Core\Utility\VersionNumberUtility::getCurrentTypo3Version(), '10.4.0', '<')){
            return GeneralUtility::makeInstance(FileIndexRepository::class);
        } else {
            $this->eventDispatcher = $eventDispatcher ?? GeneralUtility::getContainer()->get(EventDispatcherInterface::class);
            return GeneralUtility::makeInstance(FileIndexRepository::class, $this->eventDispatcher);
        }

    }
}
