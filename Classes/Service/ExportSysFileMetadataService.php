<?php

namespace CPSIT\AdmiralCloudConnector\Service;

use CPSIT\AdmiralCloudConnector\Exception\InvalidArgumentException;
use CPSIT\AdmiralCloudConnector\Exception\RuntimeException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ExportSysFileMetadataService
 * @package CPSIT\AdmiralCloudConnector\Service
 */
class ExportSysFileMetadataService
{
    public const MAXIMUM_ITERATION = 50000;
    protected const EXPORT_SYS_FILE_ITERATION_LIMIT = 1000;
    protected const JSON_ENTRIES_PER_FILE = 20000;

    protected const IMAGE_POOL_STORAGE_NAME = 'Bilderpool';
    protected const IMAGE_POOL_STORAGE_PATH = 'storages/Bilderpool';

    protected const FILEADMIN_STORAGE_PATH = 'fileadmin';

    /**
     * @var int
     */
    protected $imagePoolStorageUid;

    /**
     * @var string
     */
    protected $absoluteExportFilePath = '';

    /**
     * @var string
     */
    protected $logFilePath = '';

    /**
     * @var int
     */
    protected $currentFileNumber = 0;

    /**
     * @param string $exportFilePath
     * @param array $securityGroupMapping
     * @throws \Throwable
     */
    public function generateFileWithSysFileMetadata(
        string $exportFilePath,
        array $securityGroupMapping
    ): void {
        // Exist if export file path is empty
        if (empty($exportFilePath)) {
            throw new InvalidArgumentException('Export file path cannot be empty.');
        }

        // Set export and log file absolute paths
        $this->setAbsoluteFilesPath($exportFilePath);

        // Start JSON array
        if ($this->writeToExportFile('[', true) === false) {
            throw new RuntimeException(
                'It was not possible to write in the export file: ' . sprintf($this->absoluteExportFilePath, $this->currentFileNumber)
            );
        }

        // Empty log file
        if ($this->writeToLogFile('uid, storage, identifier', false) === false) {
            throw new RuntimeException('It was not possible to write in the log file: ' . $this->logFilePath);
        }

        $iteration = 0;
        $exportedFiles = 0;
        $duration = time();

        try {
            // Init query to get rows from sys_file with metadata
            $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('sys_file');

            $queryBuilder->select(
                'sys_file.uid',
                'sys_file.identifier',
                'sys_file.name',
                'sys_file.storage',
                'm.crdate',
                'm.tstamp',
                'm.title',
                'm.description',
                'm.alternative',
                'm.copyright',
                'm.caption',
                'm.publisher'
            )
                ->from('sys_file')
                ->join('sys_file', 'sys_file_metadata', 'm', 'sys_file.uid = m.file')
                ->setMaxResults(static::EXPORT_SYS_FILE_ITERATION_LIMIT);

            $continue = true;
            $insertCommaBefore = false;

            while ($continue && $iteration < static::MAXIMUM_ITERATION) {
                // Execute the query with current offset
                $result = $queryBuilder
                    ->setFirstResult($iteration * static::EXPORT_SYS_FILE_ITERATION_LIMIT)
                    ->executeQuery();

                $iteration++;

                if ($result->rowCount()) {
                    while ($row = $result->fetchAssociative()) {
                        // Get security group for current file
                        $securityGroup = $this->getSecurityGroup($row, $securityGroupMapping);

                        // If security group was found, export file
                        if ($securityGroup) {
                            // Add coma for JSON array
                            if ($insertCommaBefore) {
                                $this->writeToExportFile(',');
                            } else {
                                $insertCommaBefore = true;
                            }

                            $parentFolder = $row['storage'] == $this->getImagePoolStorageUid()
                                ? static::IMAGE_POOL_STORAGE_PATH : static::FILEADMIN_STORAGE_PATH;

                            $file = [
                                'uid' => $row['uid'],
                                'identifier' => $parentFolder . $row['identifier'],
                                'name' => $row['name'],
                                'security_group' => $securityGroup,
                                'metadata' => [
                                    'title' => $row['title'] ?? '',
                                    'description' => $row['description'] ?? '',
                                    'alternative' => $row['alternative'] ?? '',
                                    'copyright' => $row['copyright'] ?? '',
                                    'caption' => $row['caption'] ?? '',
                                    'publisher' => $row['publisher'] ?? '',
                                    'content_creation_date' => $row['crdate'] ?? 0,
                                    'content_modification_date' => $row['tstamp'] ?? 0,
                                ]
                            ];

                            $exportedFiles++;
                            $this->writeToExportFile(json_encode($file));

                            // If JSON file is completed, start a new one
                            if ($exportedFiles % self::JSON_ENTRIES_PER_FILE === 0) {
                                $this->writeToExportFile(']');
                                $this->writeToExportFile('[', true);
                                $insertCommaBefore = false;
                            }
                        } else {
                            // If security group was not found
                            // and storage is not the default one and is not the deleted "AHK-Sandbox"
                            // write current sys_file entry to log
                            if ($row['storage'] != 0 && $row['storage'] !== 2) {
                                $this->writeToLogFile(sprintf(
                                    '%s, %s, %s',
                                    $row['uid'],
                                    $row['storage'],
                                    $row['identifier']
                                ));
                            }
                        }
                    }
                } else {
                    // If there isn't any result more, the export is done
                    $continue = false;
                }
            }

            if ($iteration === static::MAXIMUM_ITERATION) {
                throw new RuntimeException(
                    'Error exporting sys_file metadata for AdmiralCloud. Maximum iteration was reached.'
                );
            }
        } catch (\Throwable $exception) {
            throw $exception;
        } finally {
            // Finish JSON array
            $this->writeToExportFile(']');

            // If exception was triggered write it to log file
            if (isset($exception)) {
                $this->writeToLogFile(
                    '************************' . PHP_EOL
                    . 'Error exporting sys_file with metadata.' . PHP_EOL
                    . 'Exception: ' . $exception->getMessage() . PHP_EOL
                    . 'File: ' . $exception->getFile() . ':' . $exception->getLine() . PHP_EOL
                    . '************************'
                );
            }

            $duration = (time() - $duration) . ' s';

            $this->writeToLogFile(
                '############################' . PHP_EOL
                . 'Export finish' . PHP_EOL
                . 'Exported sys_file entries: ' . $exportedFiles . PHP_EOL
                . 'Count of exported files: ' . $this->currentFileNumber . PHP_EOL
                . 'Time: ' . $duration . PHP_EOL
                . '############################'
            );
        }
    }

    /**
     * Get security group for sys_file row
     *
     * @param array $row
     * @param array $securityGroupMapping
     * @return int
     */
    protected function getSecurityGroup(array $row, array $securityGroupMapping): int
    {
        if ($row['storage'] == $this->getImagePoolStorageUid()) {
            $parentFolder = static::IMAGE_POOL_STORAGE_NAME;
        } else {
            $identifier = $row['identifier'];

            if (strpos($identifier, '/') === 0) {
                $identifier = substr($identifier, 1);
            }

            $parentFolder = substr($identifier, 0, strpos($identifier, '/') ?: null);
        }

        if (isset($securityGroupMapping[$parentFolder])) {
            return (int) $securityGroupMapping[$parentFolder];
        }

        return 0;
    }

    /**
     * Get image pool storage uid
     *
     * @return int
     */
    protected function getImagePoolStorageUid(): int
    {
        if ($this->imagePoolStorageUid) {
            return $this->imagePoolStorageUid;
        }

        $con = $this->getConnectionPool()->getConnectionForTable('sys_file_storage');

        $res = $con->select(['uid'], 'sys_file_storage', ['name' => static::IMAGE_POOL_STORAGE_NAME])->fetch();

        if ($res) {
            $this->imagePoolStorageUid = (int) $res['uid'];
        } else {
            throw new RuntimeException(sprintf(
                'Storage with name "%s" was not found.',
                static::IMAGE_POOL_STORAGE_NAME
            ));
        }

        return $this->imagePoolStorageUid;
    }

    /**
     * @param string $relativePath
     */
    protected function setAbsoluteFilesPath(string $relativePath): void
    {
        // Get absolute export file path
        $preparedAbsoluteExportFilePath = Environment::getProjectPath() . '/' .  $relativePath;

        // Get absolute log path
        $this->logFilePath = $preparedAbsoluteExportFilePath . '.log';

        // Prepare absoluteExportFilePath for several files
        $index = strrpos($preparedAbsoluteExportFilePath, '.');

        if ($index !== false) {
            $extension = substr($preparedAbsoluteExportFilePath, $index);
            $pathWithoutExtension = substr($preparedAbsoluteExportFilePath, 0, $index);
        } else {
            $extension = '';
            $pathWithoutExtension = $preparedAbsoluteExportFilePath;
        }

        $this->absoluteExportFilePath = $pathWithoutExtension . '.%d' . $extension;
    }

    /**
     * Write to export file
     *
     * @param string $text
     * @param bool $newFile
     * @return false|int
     */
    protected function writeToExportFile(string $text, bool $newFile = false)
    {
        if ($newFile) {
            $this->currentFileNumber++;
        }

        return file_put_contents(
            sprintf($this->absoluteExportFilePath, $this->currentFileNumber),
            $text,
            $newFile ? 0 : FILE_APPEND
        );
    }

    /**
     * Write message to log file
     *
     * @param string $message
     * @param bool $append
     * @return false|int
     */
    protected function writeToLogFile(string $message, bool $append = true)
    {
        return file_put_contents($this->logFilePath, $message . PHP_EOL, $append ? FILE_APPEND : 0);
    }

    /**
     * @return ConnectionPool
     */
    protected function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }
}
