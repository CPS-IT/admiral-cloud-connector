<?php

namespace CPSIT\AdmiralCloudConnector\Service;

use CPSIT\AdmiralCloudConnector\Exception\InvalidArgumentException;
use CPSIT\AdmiralCloudConnector\Exception\RuntimeException;
use Doctrine\DBAL\FetchMode;
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
    protected $logFilePath = '';

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

        // Get absolute export file path
        $absoluteExportFilePath = Environment::getProjectPath() . '/' .  $exportFilePath;

        // Get absolute log path
        $this->logFilePath = $absoluteExportFilePath . '.log';

        // Empty export file
        if (file_put_contents($absoluteExportFilePath, '') === false) {
            throw new RuntimeException('It was not possible to write in the export file: ' . $absoluteExportFilePath);
        }

        // Empty log file
        if ($this->writeToLogFile('uid, storage, identifier', false) === false) {
            throw new RuntimeException('It was not possible to write in the log file: ' . $this->logFilePath);
        }

        // Start JSON array
        file_put_contents($absoluteExportFilePath, '[', FILE_APPEND);

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
            $first = true;

            while ($continue && $iteration < static::MAXIMUM_ITERATION) {
                // Execute the query with current offset
                $result = $queryBuilder
                    ->setFirstResult($iteration * static::EXPORT_SYS_FILE_ITERATION_LIMIT)
                    ->execute();

                $iteration++;

                if ($result->rowCount()) {
                    while ($row = $result->fetch(FetchMode::ASSOCIATIVE)) {
                        // Get security group for current file
                        $securityGroup = $this->getSecurityGroup($row, $securityGroupMapping);

                        // If security group was found, export file
                        if ($securityGroup) {
                            // Add coma for JSON array
                            if (!$first) {
                                file_put_contents($absoluteExportFilePath, ',', FILE_APPEND);
                            } else {
                                $first = false;
                            }

                            $parentFolder = $row['storage'] == $this->getImagePoolStorageUid()
                                ? static::IMAGE_POOL_STORAGE_PATH : static::FILEADMIN_STORAGE_PATH;

                            $file = [
                                'uid' => $row['uid'],
                                'identifier' => $parentFolder . $row['identifier'],
                                'name' => $row['name'],
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
                            file_put_contents($absoluteExportFilePath, json_encode($file), FILE_APPEND);
                        } else {
                            // If security group was not found, write current file to log
                            $this->writeToLogFile(sprintf(
                                '%s, %s, %s',
                                $row['uid'],
                                $row['storage'],
                                $row['identifier']
                            ));
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
            file_put_contents($absoluteExportFilePath, ']', FILE_APPEND);

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
                . 'Exported files: ' . $exportedFiles . PHP_EOL
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
