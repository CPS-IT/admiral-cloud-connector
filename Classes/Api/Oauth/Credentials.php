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

namespace CPSIT\AdmiralCloudConnector\Api\Oauth;

/**
 * Class to hold Oauth tokens necessary for every API request.
 */
final class Credentials
{
    private string $accessKey;
    private string $accessSecret;
    private string $clientId;

    public function __construct()
    {
        $this->accessKey = (string)getenv('ADMIRALCLOUD_ACCESS_KEY');
        $this->accessSecret = (string)getenv('ADMIRALCLOUD_ACCESS_SECRET');
        $this->clientId = (string)getenv('ADMIRALCLOUD_CLIENT_ID');
    }

    public function getAccessKey(): string
    {
        return $this->accessKey;
    }

    public function setAccessKey(string $accessKey): void
    {
        $this->accessKey = $accessKey;
    }

    public function getAccessSecret(): string
    {
        return $this->accessSecret;
    }

    public function setAccessSecret(string $accessSecret): void
    {
        $this->accessSecret = $accessSecret;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }
}
