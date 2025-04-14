<?php

declare(strict_types=1);

namespace CPSIT\AdmiralCloudConnector\Resource;

use CPSIT\AdmiralCloudConnector\Exception\InvalidArgumentException;
use CPSIT\AdmiralCloudConnector\Exception\NotImplementedException;
use CPSIT\AdmiralCloudConnector\Traits\AssetFactory;
use TYPO3\CMS\Core\Resource\Capabilities;
use TYPO3\CMS\Core\Resource\Driver\DriverInterface;

/**
 * Class AdmiralCloudDriver
 * @package CPSIT\AdmiralCloudConnector\Resource
 */
class AdmiralCloudDriver implements DriverInterface
{
    use AssetFactory;

    public const KEY = 'AdmiralCloud';

    protected string $rootFolder = '';
    protected Capabilities $capabilities;
    protected int $storageUid = 0;
    protected array $configuration = [];

    public function __construct()
    {
        $this->capabilities = new Capabilities();
    }

    public function processConfiguration(): void
    {
    }

    public function setStorageUid(int $storageUid): void
    {
        $this->storageUid = $storageUid;
    }

    public function initialize(): void
    {
        $this->capabilities->set(Capabilities::CAPABILITY_BROWSABLE | Capabilities::CAPABILITY_PUBLIC | Capabilities::CAPABILITY_WRITABLE);
    }

    public function getCapabilities(): Capabilities
    {
        return $this->capabilities;
    }

    public function mergeConfigurationCapabilities(Capabilities $capabilities): Capabilities
    {
        $this->capabilities->and($capabilities);

        return $this->capabilities;
    }

    public function hasCapability(int $capability): bool
    {
        return $this->capabilities->hasCapability($capability);
    }

    public function isCaseSensitiveFileSystem(): bool
    {
        return true;
    }

    public function sanitizeFileName(string $fileName, string $charset = ''): string
    {
        // Admiral cloud allows all
        return $fileName;
    }

    public function hashIdentifier(string $identifier): string
    {
        return $this->hash($identifier, 'sha1');
    }

    public function getRootLevelFolder(): string
    {
        return $this->rootFolder;
    }

    public function getDefaultFolder(): string
    {
        return $this->rootFolder;
    }

    public function getParentFolderIdentifierOfIdentifier(string $fileIdentifier): string
    {
        return $this->rootFolder;
    }

    public function getPublicUrl(string $identifier): ?string
    {
        return $this->getAsset($identifier)->getPublicUrl($this->storageUid);
    }

    public function createFolder(string $newFolderName, string $parentFolderIdentifier = '', bool $recursive = false): never
    {
        throw new NotImplementedException(sprintf('Method %s::%s() is not implemented', __CLASS__, __METHOD__), 1519045381);
    }

    public function renameFolder(string $folderIdentifier, string $newName): never
    {
        throw new NotImplementedException(sprintf('Method %s::%s() is not implemented', __CLASS__, __METHOD__), 1519045382);
    }

    public function deleteFolder(string $folderIdentifier, bool $deleteRecursively = false): never
    {
        throw new NotImplementedException(sprintf('Method %s::%s() is not implemented', __CLASS__, __METHOD__), 1519045383);
    }

    public function fileExists(string $fileIdentifier): bool
    {
        // We just assume that the processed file exists as this is just a CDN link
        return !empty($fileIdentifier);
    }

    public function folderExists(string $folderIdentifier): bool
    {
        // We only know the root folder
        return $folderIdentifier === $this->rootFolder;
    }

    public function isFolderEmpty(string $folderIdentifier): bool
    {
        return !$this->folderExists($folderIdentifier);
    }

    public function addFile(string $localFilePath, string $targetFolderIdentifier, string $newFileName = '', bool $removeOriginal = true): never
    {
        throw new NotImplementedException(sprintf('Method %s::%s() is not implemented', __CLASS__, __METHOD__), 1519045386);
    }

    public function createFile(string $fileName, string $parentFolderIdentifier): never
    {
        throw new NotImplementedException(sprintf('Method %s::%s() is not implemented', __CLASS__, __METHOD__), 1519045387);
    }

    public function copyFileWithinStorage(string $fileIdentifier, string $targetFolderIdentifier, string $fileName): never
    {
        throw new NotImplementedException(sprintf('Method %s::%s() is not implemented', __CLASS__, __METHOD__), 1519045388);
    }

    public function renameFile(string $fileIdentifier, string $newName): never
    {
        throw new NotImplementedException(sprintf('Method %s::%s() is not implemented', __CLASS__, __METHOD__), 1519045389);
    }

    public function replaceFile(string $fileIdentifier, string $localFilePath): never
    {
        throw new NotImplementedException(sprintf('Method %s::%s() is not implemented', __CLASS__, __METHOD__), 1519045390);
    }

    public function deleteFile(string $fileIdentifier): bool
    {
        // Deleting processed files isn't needed as this is just a link to a file in the CDN
        // to prevent false errors for the user we just tell the API that deleting was successful
        if ($this->isProcessedFile($fileIdentifier)) {
            return true;
        }

        throw new NotImplementedException(sprintf('Method %s::%s() is not implemented', __CLASS__, __METHOD__), 1519045448);
    }

    public function hash(string $fileIdentifier, string $hashAlgorithm): string
    {
        switch ($hashAlgorithm) {
            case 'sha1':
                return sha1($fileIdentifier);
            case 'md5':
                return md5($fileIdentifier);
            default:
                throw new InvalidArgumentException('Hash algorithm ' . $hashAlgorithm . ' is not implemented.', 1519131572);
        }
    }

    public function moveFileWithinStorage(string $fileIdentifier, string $targetFolderIdentifier, string $newFileName): never
    {
        throw new NotImplementedException(sprintf('Method %s::%s() is not implemented', __CLASS__, __METHOD__), 1519045392);
    }

    public function moveFolderWithinStorage(string $sourceFolderIdentifier, string $targetFolderIdentifier, string $newFolderName): never
    {
        throw new NotImplementedException(sprintf('Method %s::%s() is not implemented', __CLASS__, __METHOD__), 1519045393);
    }

    public function copyFolderWithinStorage(string $sourceFolderIdentifier, string $targetFolderIdentifier, string $newFolderName): never
    {
        throw new NotImplementedException(sprintf('Method %s::%s() is not implemented', __CLASS__, __METHOD__), 1519045394);
    }

    public function getFileContents(string $fileIdentifier): never
    {
        throw new NotImplementedException(sprintf('Method %s::%s() is not implemented', __CLASS__, __METHOD__), 1530716557);
    }

    public function setFileContents(string $fileIdentifier, string $contents): never
    {
        throw new NotImplementedException(sprintf('Method %s::%s() is not implemented', __CLASS__, __METHOD__), 1519045395);
    }

    public function fileExistsInFolder(string $fileName, string $folderIdentifier): bool
    {
        return !empty($fileName) && ($this->rootFolder === $folderIdentifier);
    }

    public function folderExistsInFolder(string $folderName, string $folderIdentifier): bool
    {
        // Currently we don't know the concept of folders within Admiral cloud and for now always return false
        return false;
    }

    public function getFileForLocalProcessing(string $fileIdentifier, bool $writable = true): string
    {
        return $this->getAsset($fileIdentifier)->getLocalThumbnail($this->storageUid);
    }

    public function getPermissions(string $identifier): array
    {
        return [
            'r' => $identifier === $this->rootFolder || $this->fileExists($identifier),
            'w' => false
        ];
    }

    public function dumpFileContents(string $identifier): never
    {
        throw new NotImplementedException(sprintf('Method %s::%s() is not implemented', __CLASS__, __METHOD__), 1530716441);
    }

    public function isWithin(string $folderIdentifier, string $identifier): bool
    {
        return $folderIdentifier === $this->rootFolder;
    }

    public function getFileInfoByIdentifier(string $fileIdentifier, array $propertiesToExtract = []): array
    {
        return $this->getAsset($fileIdentifier)->extractProperties($propertiesToExtract);
    }

    public function getFolderInfoByIdentifier(string $folderIdentifier): array
    {
        return [
            'identifier' => $folderIdentifier,
            'name' => 'AdmiralCloud',
            'mtime' => 0,
            'ctime' => 0,
            'storage' => $this->storageUid
        ];
    }

    public function getFileInFolder(string $fileName, string $folderIdentifier): string
    {
        return '';
    }

    public function getFilesInFolder(
        string $folderIdentifier,
        int $start = 0,
        int $numberOfItems = 0,
        bool $recursive = false,
        array $filenameFilterCallbacks = [],
        string $sort = '',
        bool $sortRev = false,
    ): array {
        return [];
    }

    public function getFolderInFolder(string $folderName, string $folderIdentifier): string
    {
        return '';
    }

    public function getFoldersInFolder(
        string $folderIdentifier,
        int $start = 0,
        int $numberOfItems = 0,
        bool $recursive = false,
        array $folderNameFilterCallbacks = [],
        string $sort = '',
        bool $sortRev = false,
    ): array {
        return [];
    }

    public function countFilesInFolder(string $folderIdentifier, bool $recursive = false, array $filenameFilterCallbacks = []): int
    {
        return 0;
    }

    public function countFoldersInFolder(string $folderIdentifier, bool $recursive = false, array $folderNameFilterCallbacks = []): int
    {
        return 0;
    }

    protected function isProcessedFile(string $fileIdentifier): bool
    {
        return (bool)preg_match('/^processed_([0-9A-Z\-]{35})_([a-z]+)/', $fileIdentifier);
    }
}
