<?php

/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

$additionalColumns = [
	'tx_awesome_url_domain' => [
        'exclude' => 1,
        'label' => 'LLL:EXT:awesome_url/Resources/Private/Language/locallang.xlf:tableDomainTitle',
        'config' => [
            // http://docs.typo3.org/typo3cms/TCAReference/Reference/Columns/Inline/
            'type' => 'inline',
            'minitems' => 0,
            'foreign_table' => 'tx_awesome_url_domain',
            'foreign_field' => 'uid_foreign',
            'appearance' => [
                'collapseAll' => true,
                'newRecordLinkTitle' => 'LLL:EXT:awesome_url/Resources/Private/Language/locallang.xlf:addMapping',
                'enabledControls' => [
                    'info' => false,
                    'new' => true,
                    'dragdrop' => true,
                    'sort' => true,
                    'hide' => false,
                    'delete' => true,
                    'localize' => false,
                ],
            ],
            'behaviour' => [
                'enableCascadingDelete' => true,
            ],
        ],
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns(
	'sys_domain', $additionalColumns
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('sys_domain', 'tx_awesome_url_domain', '', 'after:forced');
