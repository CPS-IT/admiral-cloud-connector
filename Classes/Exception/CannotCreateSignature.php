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

namespace CPSIT\AdmiralCloudConnector\Exception;

/**
 * CannotCreateSignature
 *
 * @author Elias Häußler <e.haeussler@familie-redlich.de>
 * @license GPL-2.0-or-later
 */
final class CannotCreateSignature extends \RuntimeException implements AdmiralCloudConnectorException
{
    public function __construct(string $path, string $reason)
    {
        parent::__construct(
            sprintf('Cannot create a signature for AdmiralCloud request to "%s". Reason: %s', $path, $reason),
            1763533976,
        );
    }
}
