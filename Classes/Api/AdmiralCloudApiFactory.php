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

namespace CPSIT\AdmiralCloudConnector\Api;

/**
 * Static Factory class used to create instances of AdmiralCloudApi.
 */
class AdmiralCloudApiFactory
{
    /**
     * @throws \InvalidArgumentException Oauth settings not valid, consumer key or secret not in array.
     */
    public static function create(array $settings, string $method = 'POST'): AdmiralCloudApi
    {
        return AdmiralCloudApi::create($settings, $method);
    }

    /**
     * @throws \InvalidArgumentException Oauth settings not valid, consumer key or secret not in array.
     */
    public static function auth(array $settings): string
    {
        return AdmiralCloudApi::auth($settings);
    }
}
