<?php
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
        'cruser_id' => 'cruser_id',
        'dividers2tabs' => true,
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
        'iconfile' => 'EXT:ahk_contacts/Resources/Public/Icons/tx_ahkcontacts_domain_model_contact.gif',
    ],

    'interface' => [
        'showRecordFieldList' => 'ac_security_group_id, be_groups',
    ],
    'types' => [
        '1' => [
            'showitem' => '--div--;Security Group,ac_security_group_id,be_groups',
        ],
    ],
    'palettes' => [
    ],
    'columns' => [
        'hidden' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
            ],
        ],
        'starttime' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/Resources/Private/Language/locallang_general.xlf:LGL.starttime',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 13,
                'max' => 20,
                'eval' => 'datetime',
                'checkbox' => 0,
                'default' => 0,
                'range' => [
                    'lower' => mktime(0, 0, 0, date('m'), date('d'), date('Y')),
                ],
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'endtime' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:lang/Resources/Private/Language/locallang_general.xlf:LGL.endtime',
            'config' => [
                'type' => 'input',
                'renderType' => 'inputDateTime',
                'size' => 13,
                'max' => 20,
                'eval' => 'datetime',
                'checkbox' => 0,
                'default' => 0,
                'range' => [
                    'lower' => mktime(0, 0, 0, date('m'), date('d'), date('Y')),
                ],
                'behaviour' => [
                    'allowLanguageSynchronization' => true,
                ],
            ],
        ],
        'ac_security_group_id' => [
            'exclude' => 1,
            'label' => 'AC Security Group Id',
            'config' => [
                'type' => 'input',
                'eval' => 'int'
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
                'enableMultiSelectFilterTextfield' => true,
            ],
        ],
    ],
];
