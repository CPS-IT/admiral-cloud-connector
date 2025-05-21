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

if (!defined('TYPO3')) {
    die('Access denied.');
}

return [
    'ctrl' => [
        'title' => 'AC Security Group',
        'label' => 'ac_security_group_id',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'rootLevel' => 1,
        'versioningWS' => true,
        'sortby' => 'sorting',
        'default_sortby' => 'ORDER BY ac_security_group_id',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ],
        'searchFields' => 'uid, ac_security_group_id',
        'iconfile' => 'EXT:admiral_cloud_connector/Resources/Public/Icons/ac.svg',
    ],
    'types' => [
        '1' => [
            'showitem' => '--div--;Security Group,ac_security_group_id,be_groups',
        ],
    ],
    'palettes' => [
    ],
    'columns' => [
        'ac_security_group_id' => [
            'exclude' => 1,
            'label' => 'AC Security Group Id',
            'config' => [
                'type' => 'number',
            ],
        ],
        'be_groups' => [
            'exclude' => 0,
            'l10n_mode' => 'exclude',
            'label' => 'Be Group',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'be_groups',
                'foreign_table_where' => 'AND true',
                'size' => 10,
                'autoSizeMax' => 30,
                'maxitems' => 9999,
                'multiple' => 1,
            ],
        ],
    ],
];
