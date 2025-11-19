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

use CPSIT\AdmiralCloudConnector\Api\Oauth\Credentials;
use CPSIT\AdmiralCloudConnector\Exception\CannotCreateSignature;
use CPSIT\AdmiralCloudConnector\Exception\InvalidPropertyException;
use CPSIT\AdmiralCloudConnector\Exception\RuntimeException;
use CPSIT\AdmiralCloudConnector\Utility\ConfigurationUtility;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AdmiralCloudApi
{
    protected string $baseUrl;
    protected string $code;
    protected string $device;

    public function __construct(
        protected string $data,
    ) {
        $context = GeneralUtility::makeInstance(Context::class);
        $backendUserId = $context->getPropertyFromAspect('backend.user', 'id');

        $this->baseUrl = (string)getenv('ADMIRALCLOUD_BASE_URL');
        $this->device = md5((string)$backendUserId);
    }

    public static function create(string $route, array $payload, ?string $action = null, string $method = 'POST'): self
    {
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(self::class);
        $credentials = new Credentials();
        $method = strtoupper($method);

        if (!self::validateSettings($credentials)) {
            throw new \InvalidArgumentException('Settings passed for AdmiralCloudApi service creation are not valid.', 1744626269);
        }

        $routeUrl = new Uri(ConfigurationUtility::getApiUrl() . 'v5/' . ltrim($route, '/'));

        try {
            $signature = Signature\AdmiralCloudSignature::sign($credentials, $routeUrl->getPath(), $payload);
        } catch (CannotCreateSignature $exception) {
            $logger->error(
                'Error while trying to sign request parameters for AdmiralCloud route process: {error}',
                [
                    'url' => $routeUrl,
                    'error' => $exception->getMessage(),
                ],
            );

            throw new RuntimeException(
                'Error while trying to sign request parameters for AdmiralCloud route process: ' . $exception->getMessage(),
                1758782772,
                $exception,
            );
        }

        $requestOptions = [
            RequestOptions::HEADERS => [
                'Content-Type' => 'application/json',
                'X-Admiralcloud-Accesskey' => $credentials->getAccessKey(),
                'X-Admiralcloud-Version' => $signature->version,
                'X-Admiralcloud-Rts' => $signature->timestamp,
                'X-Admiralcloud-Hash' => $signature->hash,
            ],
        ];

        if ($method === 'POST') {
            $requestOptions[RequestOptions::JSON] = $signature->payload;
        }

        try {
            $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
            $response = $requestFactory->request((string)$routeUrl, $method, $requestOptions);
            $content = $response->getBody()->getContents();
            $statusCode = $response->getStatusCode();
            $isFailedSearch = $action === 'search' && $content === '{"message":"error_search_search_failed"}';

            if ($statusCode >= 400 && !$isFailedSearch) {
                $logger->error(
                    'Error in AdmiralCloud route process. URL: {url}. HTTP code: {httpCode}. Error message: {error}',
                    [
                        'url' => $routeUrl,
                        'httpCode' => $statusCode,
                        'error' => $content,
                    ],
                );

                throw new RuntimeException('Error in AdmiralCloud route process. HTTP Code: ' . $statusCode, 1744626526);
            }
        } catch (GuzzleException $exception) {
            throw new RuntimeException('Error in AdmiralCloud route process: ' . $exception->getMessage(), 1747820476);
        }

        return new AdmiralCloudApi($content);
    }

    /**
     * @throws \InvalidArgumentException Oauth settings not valid, consumer key or secret not in array.
     */
    public static function auth(string $callbackUrl, ?string $device = null): string
    {
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(self::class);
        $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
        $credentials = new Credentials();
        $device ??= md5((string)$GLOBALS['BE_USER']->user['uid']);

        static::validateAuthData($credentials);

        $loginUrl = new Uri(ConfigurationUtility::getAuthUrl() . 'v4/login/app');
        $payload = [
            'email' => $GLOBALS['BE_USER']->user['email'],
            'firstname' => $GLOBALS['BE_USER']->user['first_name'] ?: $GLOBALS['BE_USER']->user['realName'],
            'lastname' => $GLOBALS['BE_USER']->user['last_name'] ?: $GLOBALS['BE_USER']->user['realName'],
            'state' => '0.' . base_convert(random_int(0, mt_getrandmax()) . '00', 10, 36),
            'client_id' => $credentials->getClientId(),
            'callbackUrl' => base64_encode($callbackUrl),
            'settings' => [
                'typo3group' => self::getSecurityGroup(),
            ],
            'poc' => true,
        ];

        try {
            $signature = Signature\AdmiralCloudSignature::sign($credentials, $loginUrl->getPath(), $payload);
        } catch (CannotCreateSignature $exception) {
            $logger->error(
                'Error while trying to sign request parameters for AdmiralCloud login process: {error}',
                [
                    'url' => $loginUrl,
                    'error' => $exception->getMessage(),
                ],
            );

            throw new RuntimeException(
                'Error while trying to sign request parameters for AdmiralCloud login process: ' . $exception->getMessage(),
                1758782635,
                $exception,
            );
        }

        try {
            $response = $requestFactory->request((string)$loginUrl, 'POST', [
                RequestOptions::HEADERS => [
                    'X-Admiralcloud-Accesskey' => $credentials->getAccessKey(),
                    'X-Admiralcloud-Debugsignature' => true,
                    'X-Admiralcloud-Clientid' => $credentials->getClientId(),
                    'X-Admiralcloud-Device' => $device,
                    'X-Admiralcloud-Version' => $signature->version,
                    'X-Admiralcloud-Rts' => $signature->timestamp,
                    'X-Admiralcloud-Hash' => $signature->hash,
                ],
                RequestOptions::JSON => $signature->payload,
            ]);

            $content = $response->getBody()->getContents();
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                $logger->error(
                    'Error in AdmiralCloud login process. URL: {url}. HTTP Code: {httpCode}. Error message: {error}',
                    [
                        'url' => $loginUrl,
                        'httpCode' => $statusCode,
                        'error' => $content,
                    ],
                );

                throw new RuntimeException('Error in AdmiralCloud login process. HTTP Code: ' . $statusCode, 1744626689);
            }
        } catch (GuzzleException $exception) {
            throw new RuntimeException('Error in AdmiralCloud login process: ' . $exception->getMessage(), 1747820790);
        }

        $codeParams = [
            'state' => $signature->payload['state'],
            'device' => $device,
            'client_id' => $credentials->getClientId(),
        ];

        $authUrl = ConfigurationUtility::getAuthUrl() . 'v4/requestCode?' . http_build_query($codeParams);

        try {
            $response = $requestFactory->request(
                $authUrl,
                'GET',
                [
                    RequestOptions::HEADERS => [
                        'Content-Type' => 'application/json',
                    ],
                ],
            );

            $content = $response->getBody()->getContents();
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                $logger->error(
                    'Error in AdmiralCloud auth process. URL: {url}. HTTP Code: {httpCode}. Error message: {error}',
                    [
                        'url' => $authUrl,
                        'httpCode' => $statusCode,
                        'error' => $content,
                    ],
                );

                throw new RuntimeException('Error in AdmiralCloud auth process. HTTP Code: ' . $statusCode, 1744626753);
            }

            $code = json_decode($content, false);
        } catch (GuzzleException $exception) {
            throw new RuntimeException('Error in AdmiralCloud auth process: ' . $exception->getMessage(), 1747820467);
        }

        if ($content && !$code) {
            $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(self::class);
            $logger->error('Error decoding JSON from auth response. JSON: ' . $content);

            throw new RuntimeException('Error decoding JSON from auth response.', 1744626760);
        }

        if (empty($code->code)) {
            throw new RuntimeException('There is not any code in the response of the AUTH process', 1744626764);
        }

        return $code->code;
    }

    public static function getSecurityGroup(): string
    {
        $securityGroup = $GLOBALS['BE_USER']->user['security_group'] ?? null;

        if (is_string($securityGroup) && trim($securityGroup) !== '') {
            return $securityGroup;
        }

        $groups = array_map(
            strval(...),
            GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('backend.user', 'groupIds', []),
        );

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_admiralcloudconnector_security_groups');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
        ;

        $sgs = [];
        $res = $queryBuilder->select('*')
            ->from('tx_admiralcloudconnector_security_groups')
            ->orderBy('ac_security_group_id', 'DESC')
            ->executeQuery()
        ;

        while ($row = $res->fetchAssociative()) {
            $sgs[$row['ac_security_group_id']] = $row['be_groups'];
        }

        foreach ($sgs as $sgId => $be_groups) {
            $containsAllValues = !array_diff(explode(',', (string)$be_groups), $groups);

            if ($containsAllValues) {
                return (string)$sgId;
            }
        }

        return '';
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getDevice(): string
    {
        return $this->device;
    }

    public function setDevice(string $device): void
    {
        $this->device = $device;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function setData(string $data): void
    {
        $this->data = $data;
    }

    /**
     * Validate data before authentication
     */
    protected static function validateAuthData(Credentials $credentials): void
    {
        if (!self::validateSettings($credentials)) {
            throw new \InvalidArgumentException('Settings passed for AdmiralCloudApi service creation are not valid.', 1744627161);
        }

        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(self::class);
        $errors = [];

        if (empty($GLOBALS['BE_USER']->user['email'])) {
            $errors[] = 'The field "email" cannot be empty.';
        }

        if (empty($GLOBALS['BE_USER']->user['first_name'])) {
            $logger->warning(
                'Field "first_name" is empty for the BE user with username "{username}".',
                [
                    'username' => $GLOBALS['BE_USER']->user['username'],
                ],
            );
        }

        if (empty($GLOBALS['BE_USER']->user['last_name'])) {
            $logger->warning(
                'Field "last_name" is empty for the BE user with username "{username}".',
                [
                    'username' => $GLOBALS['BE_USER']->user['username'],
                ],
            );
        }

        if (empty($GLOBALS['BE_USER']->user['first_name'])
            && empty($GLOBALS['BE_USER']->user['last_name'])
            && empty($GLOBALS['BE_USER']->user['realName'])
        ) {
            $errors[] = 'First name and last name information is empty.';
        }

        if (!self::getSecurityGroup()) {
            $errors[] = 'The current user has not an associated security group.';
        }

        if ($errors) {
            throw new InvalidPropertyException(
                sprintf(
                    'AdmiralCloud authentication for user "%s" was not possible because: * %s',
                    $GLOBALS['BE_USER']->user['username'],
                    implode("\n* ", $errors),
                ),
                1744627238,
            );
        }
    }

    protected static function validateSettings(Credentials $credentials): bool
    {
        return $credentials->getAccessKey() && $credentials->getAccessSecret() && $credentials->getClientId();
    }
}
