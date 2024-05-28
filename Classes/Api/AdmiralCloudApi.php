<?php

namespace CPSIT\AdmiralCloudConnector\Api;

use CPSIT\AdmiralCloudConnector\Api\Oauth\Credentials;
use CPSIT\AdmiralCloudConnector\Exception\InvalidPropertyException;
use CPSIT\AdmiralCloudConnector\Exception\RuntimeException;
use CPSIT\AdmiralCloudConnector\Utility\ConfigurationUtility;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2020
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
class AdmiralCloudApi
{
    /**
     * @var string Base Url necessary for API calls.
     */
    protected string $baseUrl;

    /**
     * @var string code token
     */
    protected string $code;

    /**
     * @var string device
     */
    protected string $device;

    /**
     * @var string data
     */
    protected string $data;

    /**
     * Initialises a new instance of the class.
     *
     * @param string $requestHandler Instance of the request handler used to communicate with the API.
     *
     */
    public function __construct($data)
    {
        $context = GeneralUtility::makeInstance(Context::class);
        $backendUserId = $context->getPropertyFromAspect('backend.user', 'id');

        $this->baseUrl = getenv('ADMIRALCLOUD_BASE_URL');
        $this->device = md5($backendUserId);
        $this->data = $data;
    }

    /**
     * Creates an instance of AdmiralCloudApi
     *
     * @param array $settings
     * @param string $method
     * @return AdmiralCloudApi instance.
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public static function create(array $settings,string $method = 'post')
    {
        $credentials = new Credentials();

        if (!self::validateSettings($credentials)) {
            throw new InvalidArgumentException("Settings passed for AdmiralCloudApi service creation are not valid.");
        }

        $curl = curl_init();

        $params = [
            "accessSecret" => $credentials->getAccessSecret(),
            "controller" => $settings['controller'],
            "action" => $settings['action'],
            "payload" => $settings['payload']
        ];

        $signedValues = self::acSignatureSign($params,'v5');

        $routeUrl = ConfigurationUtility::getApiUrl() . 'v5/' . $settings['route'];

        $curlOptArray = array(
            CURLOPT_URL => $routeUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => json_encode($params['payload']),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                "x-admiralcloud-accesskey: " . $credentials->getAccessKey(),
                "x-admiralcloud-rts: " . $signedValues['timestamp'],
                "x-admiralcloud-hash: " . $signedValues['hash']
            ),
        );
        if($method == 'get'){
            unset($curlOptArray[CURLOPT_POST]);
            unset($curlOptArray[CURLOPT_POSTFIELDS]);
        }
        curl_setopt_array($curl,$curlOptArray );

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $isFailedSearch = isset($settings['action']) && $settings['action'] == 'search' && $response == '{"message":"error_search_search_failed"}';
        // Log error
        if (!$httpCode || ($httpCode >= 400 && !$isFailedSearch)) {
            /** @var LoggerInterface $logger */
            $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            $logger->error(sprintf(
                'Error in AdmiralCloud route process. URL: %s. HTTP code: %d. Error message: %s',
                $routeUrl,
                $httpCode,
                $response ?: $err
            ));

            throw new RuntimeException('Error in AdmiralCloud route process. HTTP Code: ' . curl_getinfo($curl, CURLINFO_HTTP_CODE));
        }

        curl_close($curl);

        return new AdmiralCloudApi($response);
    }

    /**
     * Creates an instance of AdmiralCloudApi
     *
     * @param array $settings
     * @return string
     * @throws InvalidArgumentException Oauth settings not valid, consumer key or secret not in array.
     */
    public static function auth(array $settings): string
    {
        $credentials = new Credentials();
        $device = $settings['device'] ?? md5($GLOBALS['BE_USER']->user['uid']);

        static::validateAuthData($credentials);

        $curl = curl_init();

        $state = '0.' . base_convert(rand() . '00', 10, 36);
        $params = [
            "accessSecret" => $credentials->getAccessSecret(),
            "controller" => $settings['controller'],
            "action" => $settings['action'],
            "payload" => [
                "email" => $GLOBALS['BE_USER']->user['email'],
                "firstname" => $GLOBALS['BE_USER']->user['first_name'] ?: $GLOBALS['BE_USER']->user['realName'],
                "lastname" => $GLOBALS['BE_USER']->user['last_name'] ?: $GLOBALS['BE_USER']->user['realName'],
                "state" => $state,
                "client_id" => $credentials->getClientId(),
                "callbackUrl" => base64_encode($settings['callbackUrl']),
                "settings" => [
                    "typo3group" => self::getSecurityGroup()
                ],
                "poc" => true
            ]
        ];
        $signedValues = self::acSignatureSign($params,'v5');

        $loginUrl = ConfigurationUtility::getAuthUrl() . "v4/login/app?poc=true";

        curl_setopt_array($curl, array(
            CURLOPT_URL => $loginUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => json_encode($params['payload']),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                "x-admiralcloud-accesskey: " . $credentials->getAccessKey(),
                "x-admiralcloud-rts: " . $signedValues['timestamp'],
                "x-admiralcloud-hash: " . $signedValues['hash'],
                "x-admiralcloud-debugsignature: 1",
                "x-admiralcloud-clientid: " . $credentials->getClientId(),
                "x-admiralcloud-device: " . $device
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Log error
        if (!$httpCode || $httpCode >= 400) {
            /** @var LoggerInterface $logger */
            $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            $logger->error(sprintf(
                'Error in AdmiralCloud login process. URL: %s. HTTP Code: %d. Error message: %s',
                $loginUrl,
                $httpCode,
                $response ?: $err
            ));

            throw new RuntimeException('Error in AdmiralCloud login process. HTTP Code: ' . curl_getinfo($curl, CURLINFO_HTTP_CODE));
        }

        curl_close($curl);

        $codeParams = [
            'state' => $params['payload']['state'],
            'device' => $device,
            'client_id' => $credentials->getClientId()

        ];

        $authUrl = ConfigurationUtility::getAuthUrl() . "v4/requestCode?" . http_build_query($codeParams);

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $authUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Log error
        if (!$httpCode || $httpCode >= 400) {
            /** @var LoggerInterface $logger */
            $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            $logger->error(sprintf(
                'Error in AdmiralCloud auth process. URL: %s. HTTP Code: %d. Error message: %s',
                $authUrl,
                $httpCode,
                $response ?: $err
            ));

            throw new RuntimeException('Error in AdmiralCloud auth process. HTTP Code: ' . curl_getinfo($curl, CURLINFO_HTTP_CODE));
        }

        curl_close($curl);

        $code = json_decode($response);

        if ($response && !$code) {
            $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            $logger->error('Error decoding JSON from auth response. JSON: ' . $response);

            throw new RuntimeException('Error decoding JSON from auth response.');
        }

        if (empty($code->code)) {
            throw new RuntimeException('There is not any code in the response of the AUTH process');
        }

        return $code->code;
    }

    public static function acSignatureSign($params, $version='v5')
    {
        $accessSecret = $params['accessSecret'];
        if (!$accessSecret) return 'accessSecretMissing';
        $controller = $params['controller'];
        if (!$controller) return 'controllerMissing';
        $action = $params['action'];
        if (!$action) return 'actionMissing';
        $data = $params['payload'];
        if (!$data) return 'payloadMustBeObject';

        ksort($data);
        $payload = [];
        foreach ($data as $key => $value) {
            $payload[$key] = $data[$key];
        }

        $ts = time();

        if ($version !== 'v4' && $version !== 'v5') {
            throw new InvalidArgumentException('Version for acSignatureSign should be v4 or v5. Version given: ' . $version);
        }

        if ($version === 'v4') {
            $valueToHash = strtolower($params['controller']) . PHP_EOL .
                strtolower($params['action']) . PHP_EOL . $ts . (empty($payload) ? '' : PHP_EOL . '{}');
        }
        if ($version === 'v5') {
            $valueToHash = strtolower($params['controller']) . PHP_EOL .
                strtolower($params['action']) . PHP_EOL . $ts . (empty($payload) ? '' : PHP_EOL . json_encode($payload));
        }

        $hash = hash_hmac('sha256', $valueToHash, $accessSecret);

        return [
            'hash' => $hash,
            'timestamp' => $ts
        ];
    }

    public static function isJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    public static function random()
    {
        return (float)rand() / (float)getrandmax();
    }

    public static function getSecurityGroup(){
        if(isset($GLOBALS['BE_USER']->user['security_group']) && $GLOBALS['BE_USER']->user['security_group']){
            return $GLOBALS['BE_USER']->user['security_group'];
        }
        $groups = array_map(
            'strval',
            GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('backend.user', 'groupIds', [])
        );
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_admiralcloudconnector_security_groups');
        $queryBuilder->getRestrictions()->removeAll();
        $res = $queryBuilder->select('sg.*')
            ->from('tx_admiralcloudconnector_security_groups', 'sg')
            ->where(
                $queryBuilder->expr()->eq('sg.deleted', $queryBuilder->createNamedParameter(0, \PDO::PARAM_INT))
            )
            ->addOrderBy('ac_security_group_id', 'DESC')
            ->execute();
        $sgs = [];
        while ($row = $res->fetch()) {
            $sgs[$row['ac_security_group_id']] = $row['be_groups'];
        }
        foreach ($sgs as $sgId=>$be_groups){
            $containsAllValues = !array_diff(explode(',', (string)$be_groups), $groups);
            if($containsAllValues){
                return (string)$sgId;
            }
        }
        return '';
    }

    /**
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @param string $baseUrl
     */
    public function setBaseUrl(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode(string $code)
    {
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function getDevice(): string
    {
        return $this->device;
    }

    /**
     * @param string $device
     */
    public function setDevice(string $device)
    {
        $this->device = $device;
    }

    /**
     * @return string
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * @param string $data
     */
    public function setData(string $data)
    {
        $this->data = $data;
    }

    /**
     * Validate data before authentication
     *
     * @param Credentials $credentials
     */
    protected static function validateAuthData(Credentials $credentials): void
    {
        if (!self::validateSettings($credentials)) {
            throw new InvalidArgumentException("Settings passed for AdmiralCloudApi service creation are not valid.");
        }

        /** @var LoggerInterface $logger */
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $errors = [];

        if (empty($GLOBALS['BE_USER']->user['email'])) {
            $errors[] = 'The field "email" cannot be empty.';
        }

        if (empty($GLOBALS['BE_USER']->user['first_name'])) {
            $logger->warning(sprintf(
                'Field "first_name" is empty for the BE user with username "%s".',
                $GLOBALS['BE_USER']->user['username']
            ));
        }

        if (empty($GLOBALS['BE_USER']->user['last_name'])) {
            $logger->warning(sprintf(
                'Field "last_name" is empty for the BE user with username "%s".',
                $GLOBALS['BE_USER']->user['username']
            ));
        }

        if (empty($GLOBALS['BE_USER']->user['first_name'])
            && empty($GLOBALS['BE_USER']->user['last_name'])
            && empty($GLOBALS['BE_USER']->user['realName'])) {
            $errors[] = 'First name and last name information is empty.';
        }

        if (!self::getSecurityGroup()) {
            $errors[] = 'The current user has not an associated security group.';
        }

        if ($errors) {
            throw new InvalidPropertyException(
                sprintf(
                    'AdmiralCloud authentication for user "%s" was not possible because: * ',
                    $GLOBALS['BE_USER']->user['username']
                )
                . implode("\n* ", $errors)
            );
        }
    }


    /**
     * Checks if the settings array passed is valid.
     * @param Credentials $credentials
     * @return bool Whether the settings array is valid.
     */
    protected static function validateSettings($credentials)
    {
        return $credentials->getAccessKey() && $credentials->getAccessSecret() && $credentials->getClientId();
    }
}
