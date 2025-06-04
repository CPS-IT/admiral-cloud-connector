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

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

final readonly class PermissionUtility
{
    public static function userHasPermissionForAdmiralCloud(): bool
    {
        $backendUser = self::getBackendUser();

        // If user is admin or has access to file with AdmiralCloud
        return $backendUser?->isAdmin() || ($backendUser?->getFilePermissions()['addFileViaAdmiralCloud'] ?? false);
    }

    /**
     * is site secured check
     */
    public static function getPageFeGroup(): string
    {
        $pageRepository = GeneralUtility::makeInstance(PageRepository::class);
        $tsfe = self::getTypoScriptFrontendController();

        if ($tsfe === null) {
            return '';
        }

        $page = $pageRepository->getPage($tsfe->id);
        $feGroup = (string)($page['fe_group'] ?? '');

        if ($feGroup === '-1') {
            return '';
        }

        return $feGroup;
    }

    /**
     * get content fe group from reference
     */
    public static function getContentFeGroupFromReference(int $uid): string
    {
        $content = self::getContent($uid);

        if ($content) {
            $feGroup = (string)($content['fe_group'] ?? '');

            if ($feGroup === '-1') {
                return '';
            }

            return $feGroup;
        }

        return '';
    }

    public static function getContent(int $uid)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');

        return $queryBuilder
            ->select('fe_group')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAssociative()
        ;
    }

    private static function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }

    private static function getTypoScriptFrontendController(): ?TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'] ?? null;
    }
}
