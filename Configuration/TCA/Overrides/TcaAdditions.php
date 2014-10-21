<?php

if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

$GLOBALS['TCA']['tx_awesome_url_domain'] = array(
	'ctrl' => array(
		'title' => 'Kontakt',
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
		'enablecolumns' => array(
			'disabled' => 'hidden',
		),
		'iconfile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('awesome_url') . 'ext_icon.gif',
	),
	'columns' => array(
		'sys_language_uid' => array(
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.xml:LGL.language',
			'config' => array(
				'type' => 'select',
				'foreign_table' => 'sys_language',
				'foreign_table_where' => 'ORDER BY sys_language.title',
				'items' => array(
					array('LLL:EXT:lang/locallang_general.xml:LGL.allLanguages', -1),
					array('LLL:EXT:lang/locallang_general.xml:LGL.default_value', 0)
				)
			)
		),
		'path_prefix' => array(
			'exclude' => 0,
			'label' => 'URL Prefix',
			'config' => array(
				'type' => 'input',
				'size' => '40',
//				'eval' => 'required',
			)
		),
	),
	'types' => array(
		'0' => array('showitem' => 'sys_language_uid,path_prefix')
	),
	'palettes' => array(
		'1' => array('showitem' => '')
	)
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('sys_domain', array(
	'tx_awesome_url_domain' => array(
		'exclude' => 1,
		'label' => 'Awesome URL Mapping',
		'config' => array(
			// http://docs.typo3.org/typo3cms/TCAReference/Reference/Columns/Inline/
			'type' => 'inline',
			'minitems' => 0,
			'foreign_table' => 'tx_awesome_url_domain',
			'foreign_field' => 'uid_foreign',
			'appearance' => array(
				'collapseAll' => true,
				'newRecordLinkTitle' => 'Mapping hinzufügen',
				'enabledControls' => array(
					'info' => false,
					'new' => true,
					'dragdrop' => true,
					'sort' => true,
					'hide' => false,
					'delete' => true,
					'localize' => false
				)
			),
			'behaviour' => array(
				'enableCascadingDelete' => true,
			),
		)
	)
));


\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('sys_domain', 'tx_awesome_url_domain');
