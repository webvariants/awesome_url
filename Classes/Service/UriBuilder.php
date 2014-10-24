<?php

namespace WV\AwesomeUrl\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;

// Hook done in ext_localconf.php

class UriBuilder {

	/**
	 *
	 * @var \TYPO3\CMS\Frontend\Page\PageRepository
	 */
	private $pageContext = null;
	private $sys_language_uid = null;

	public function __construct() {
		if (isset($GLOBALS['TSFE']) && is_object($GLOBALS['TSFE']) && is_object($GLOBALS['TSFE']->sys_page)) {
			$this->pageContext = $GLOBALS['TSFE']->sys_page;
			$this->sys_language_uid = (int) $GLOBALS['TSFE']->sys_language_uid;
		} else {
			$this->pageContext = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
		}
	}

	public function pathFromPage($domain_info, $uid, $sys_language_uid = null) {
		if ($uid == $domain_info['pid']) {
			$path = $domain_info['path_prefix'];
		} else {
			$page = $this->pageContext->getPage($uid);
			if ($sys_language_uid) {
				$page = $this->pageContext->getPageOverlay($page, $sys_language_uid);
			}

			$parent_path = '';
			if ($page['pid'] > 0) {
				$parent_path = $this->pathFromPage($domain_info, $page['pid'], $sys_language_uid);
				if (strlen($parent_path)) {
					$parent_path .= '/';
				}
			}

			$path = $parent_path . $this->fileNameASCIIPrefix($page['title']);
		}

		$uri_entry = $this->findUriByDomaiNameUri($domain_info['name'], $path);
		if ($uri_entry) {
			if ($uri_entry['uid_forign'] == $uid && $uri_entry['sys_language_uid_foreign'] == $sys_language_uid) {
				if ($uri_entry['status'] == 1) {
					return $path;
				} else {
					// update status
				}
			} else {
				if ($uri_entry['status'] == 1) {
					// append number
				} else {
					// reuse 
				}
			}
		} else {
			$this->insert($domain_info['name'], $path, 1, $uid, $sys_language_uid);
		}

		return $path;
	}

	/**
	 * Converts input string to an ASCII based file name prefix
	 *
	 * @param	string		String to base output on
	 * @param	integer		Number of characters in the string
	 * @param	string		Character to put in the end of string to merge it with the next value.
	 * @return	string		Converted string
	 */
	public function fileNameASCIIPrefix($inTitle, $maxTitleChars = 20) {
		$out = $GLOBALS['TSFE']->csConvObj->specCharsToASCII($GLOBALS['TSFE']->renderCharset, $inTitle);

		// Get replacement character
		$replacementChar = '-';
		$replacementChars = '_\-' . ($replacementChar != '_' && $replacementChar != '-' ? $replacementChar : '');
		$out = preg_replace('/[^A-Za-z0-9_-]/', $replacementChar, trim(substr($out, 0, $maxTitleChars)));
		$out = preg_replace('/([' . $replacementChars . ']){2,}/', '\1', $out);
		$out = preg_replace('/[' . $replacementChars . ']?$/', '', $out);
		$out = preg_replace('/^[' . $replacementChars . ']?/', '', $out);

		return strtolower($out);
	}

	/**
	 *
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection;
	 */
	private function db() {
		return $GLOBALS['TYPO3_DB'];
	}

	public function findUriByDomaiNameUri($domain_name, $uri) {
		$db = $this->db();
		$domain_name_safe = $db->fullQuoteStr($domain_name, 'tx_awesome_url_uri');
		$uri_safe = $db->fullQuoteStr($uri, 'tx_awesome_url_uri');
		$res = $db->exec_SELECTquery('uid,status,uid_foreign,sys_language_uid_foreign', 'tx_awesome_url_uri', "domain_name = $domain_name_safe AND uri = $uri_safe");
		$row = $db->sql_fetch_assoc($res);
		$db->sql_free_result($res);

		return $row;
	}

	private function insert($domain_name, $uri, $status, $uid_foreign, $sys_language_uid_foreign) {
		$db = $this->db();
		$db->exec_INSERTquery('tx_awesome_url_uri', array(
			'domain_name' => $domain_name,
			'uri' => $uri,
			'status' => $status,
			'uid_foreign' => $uid_foreign,
			'sys_language_uid_foreign' => $sys_language_uid_foreign
		));

		$uid = $db->sql_insert_id();
		$db->sql_free_result($res);

		return $uid;
	}

}
