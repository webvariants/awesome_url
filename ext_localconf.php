<?php

if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tstemplate.php']['linkData-PostProc']['wv_awesome_url'] = '&WV\\AwesomeUrl\\Hooks\\Url->linkDataPost';
$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_fe.php']['checkAlternativeIdMethods-PostProc']['wv_awesome_url'] = '&WV\\AwesomeUrl\\Hooks\\Url->checkAlternativeIdMethodsPost';

$TYPO3_CONF_VARS['FE']['pageOverlayFields'] .= ',tx_awesome_url_alias,tx_awesome_url_exclude_sub';

$TYPO3_CONF_VARS['SC_OPTIONS']['tce']['formevals']['\WV\AwesomeUrl\Validate\Alias'] = 'EXT:awesome_url/Classes/Validate/Alias.php';

$_EXTCONF = unserialize($_EXTCONF);

foreach (
array(
	'charsWhitelist' => 'A-Za-z0-9_\-',
	'charsWhitelistAlias' => 'A-Za-z0-9_\-',
	'charsReplaceWith' => '-_',
	'titleMax' => 40
) as $key => $value) {
	if (array_key_exists($key, $_EXTCONF) && trim($_EXTCONF[$key])) {
		$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY][$key] = trim($_EXTCONF[$key]);
	} else {
		$TYPO3_CONF_VARS['EXTCONF'][$_EXTKEY][$key] = $value;
	}
}