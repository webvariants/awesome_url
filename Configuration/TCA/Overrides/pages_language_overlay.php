<?php

/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

use \TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$additionalPageColumns = [
	'tx_awesome_url_alias' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:awesome_url/Resources/Private/Language/locallang.xlf:labelAlias',
        'config' => [
            'type' => 'input',
            'size' => '40',
            'eval' => 'WV\AwesomeUrl\Validate\Alias',
        ],
    ],
	'tx_awesome_url_exclude_sub' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:awesome_url/Resources/Private/Language/locallang.xlf:labelExcludeSub',
        'config' => [
            'type' => 'check',
            'items' => [
                '1' => [
                    '0' => 'LLL:EXT:awesome_url/Resources/Private/Language/locallang.xlf:labelExcludeSubHide',
                ],
            ],
        ],
	]
];

ExtensionManagementUtility::addTCAcolumns(
	'pages_language_overlay', $additionalPageColumns
);

ExtensionManagementUtility::addToAllTCAtypes('pages_language_overlay', 'tx_awesome_url_alias', '', 'after:subtitle');
ExtensionManagementUtility::addToAllTCAtypes('pages_language_overlay', 'tx_awesome_url_exclude_sub', '', 'after:tx_awesome_url_alias');
