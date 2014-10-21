<?php

if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tstemplate.php']['linkData-PostProc']['wv_awesome_url'] = '&WV\\AwesomeUrl\\Hooks\\Url->linkDataPost';
