<?php

/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

return [
    'ctrl' => [
        'title' => 'LLL:EXT:awesome_url/Resources/Private/Language/locallang.xlf:tableDomainTitle',
        'label' => 'path_prefix',
        'label_alt' => 'sys_language_uid',
        'label_alt_force' => true,
//		'formattedLabel_userFunc' => 'TODO',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'languageField' => -1,
        'sortby' => 'sorting',
        'delete' => 'deleted',
        'hideTable' => true,
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'iconfile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('awesome_url').'ext_icon.png',
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:lang/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'select',
                'foreign_table' => 'sys_language',
                'foreign_table_where' => 'ORDER BY sys_language.title',
                'items' => [
                    ['LLL:EXT:lang/Resources/Private/Language/locallang_general.xlf:LGL.allLanguages', -1],
                    ['LLL:EXT:lang/Resources/Private/Language/locallang_general.xlf:LGL.default_value', 0],
                ],
				'renderType' => 'selectSingle'
            ],
        ],
        'path_prefix' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:awesome_url/Resources/Private/Language/locallang.xlf:labelPathPrefix',
            'config' => [
                'type' => 'input',
                'size' => '40',
                'eval' => 'WV\AwesomeUrl\Validate\Alias',
            ],
        ],
        'page404' => [
            'exclude' => 0,
            'label' => 'LLL:EXT:awesome_url/Resources/Private/Language/locallang.xlf:labelPage404',
            'config' => [
                'type' => 'input',
                'eval' => 'trim',
                'wizards' => [
                    'link' => [
                        'type' => 'popup',
                        'title' => 'LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:header_link_formlabel',
                        'icon' => 'EXT:backend/Resources/Public/Images/FormFieldWizard/wizard_link.gif',
                        'module' => [
                            'name' => 'wizard_link',
                        ],
                        'JSopenParams' => 'width=800,height=600,status=0,menubar=0,scrollbars=1',
                        'params' => [
                            'blindLinkOptions' => 'url,folder,file,mail,spec',
                            'blindLinkFields' => 'class,params,target,title',
                        ],
                    ],
                ],
                'softref' => 'typolink',
            ],
        ],
    ],
    'types' => [
        '0' => ['showitem' => 'sys_language_uid,path_prefix,page404'],
    ],
    'palettes' => [
        '1' => ['showitem' => ''],
    ],
];
