<?php

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

$EM_CONF[$_EXTKEY] = [
    'title' => 'AdmiralCloud Connector',
    'description' => 'AdmiralCloud Connector',
    'category' => 'plugin',
    'author' => 'coding. powerful. systems. CPS GmbH',
    'author_email' => 'info@cps-it.de',
    'author_company' => 'coding. powerful. systems. CPS GmbH',
    'state' => 'stable',
    'version' => '3.0.2',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'php' => '8.2.0-8.4.99',
        ],
    ],
];
