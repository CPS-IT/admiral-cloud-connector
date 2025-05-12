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

namespace CPSIT\AdmiralCloudConnector\Resource\Index;

use Psr\EventDispatcher\EventDispatcherInterface;

class FileIndexRepository extends \TYPO3\CMS\Core\Resource\Index\FileIndexRepository
{
    protected array $extendedFields = [
        'tx_admiralcloudconnector_linkhash',
    ];

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct($eventDispatcher);
        $this->fields = array_merge($this->fields, $this->extendedFields);
    }
}
