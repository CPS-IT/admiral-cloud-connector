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

namespace CPSIT\AdmiralCloudConnector\Api\Signature;

use CPSIT\AdmiralCloudConnector\Api\Oauth\Credentials;
use CPSIT\AdmiralCloudConnector\Exception\CannotCreateSignature;

/**
 * AdmiralCloudSignature
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
final readonly class AdmiralCloudSignature
{
    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        public string $hash,
        public int $timestamp,
        public array $payload,
        public int $version,
    ) {}

    /**
     * @param array<string, mixed> $payload
     * @throws CannotCreateSignature
     */
    public static function sign(Credentials $credentials, string $path, array $payload): self
    {
        if ($credentials->getAccessSecret() === '') {
            throw new CannotCreateSignature($path, 'Access secret is missing.');
        }

        if ($path === '') {
            throw new CannotCreateSignature($path, 'URL path is empty.');
        }

        $payload = self::sortPayload($payload);
        $timestamp = time();
        $valueToHash = strtolower($path) . PHP_EOL . $timestamp . ($payload === [] ? '' : (PHP_EOL . json_encode($payload)));
        $hash = hash_hmac('sha256', $valueToHash, $credentials->getAccessSecret());

        return new self($hash, $timestamp, $payload, 5);
    }

    private static function sortPayload(mixed $payload): mixed
    {
        // If it's a list (numeric array), recursively sort each item
        if (is_array($payload) && array_is_list($payload)) {
            return array_map(self::sortPayload(...), $payload);
        }

        // If it's an associative array, sort keys recursively
        if (is_array($payload)) {
            ksort($payload, SORT_STRING);

            foreach ($payload as $key => $value) {
                $payload[$key] = self::sortPayload($value);
            }
        }

        return $payload;
    }
}
