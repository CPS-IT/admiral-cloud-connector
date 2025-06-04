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
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AssetFactory implements SingletonInterface
{
    /**
     * @var array<string, Asset>
     */
    protected static array $instances = [];

    public static function attach(Asset $asset): bool
    {
        if (static::has($asset->getIdentifier())) {
            return false;
        }

        static::$instances[$asset->getIdentifier()] = $asset;

        return true;
    }

    public static function has(string $identifier): bool
    {
        return isset(static::$instances[$identifier]);
    }

    /**
     * @throws InvalidAssetException
     */
    public static function create(string $identifier): Asset
    {
        return GeneralUtility::makeInstance(Asset::class, $identifier);
    }

    /**
     * @throws InvalidAssetException
     */
    public function get(string $identifier): Asset
    {
        if (!static::has($identifier)) {
            throw new InvalidAssetException('No asset found', 1558432065393);
        }

        return static::$instances[$identifier];
    }

    /**
     * @throws InvalidAssetException
     */
    public function getOrCreate(string $identifier): Asset
    {
        if (!static::has($identifier)) {
            $asset = static::create($identifier);
            static::attach($asset);
        }

        return static::$instances[$identifier];
    }
}
