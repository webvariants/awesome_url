<?php

namespace WV\AwesomeUrl\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;

// Hook done in ext_localconf.php

class UriBuilder {

	const CHAR_WHILTELIST = 'A-Za-z0-9_\-';

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

	/**
	 *
	 * @param array $domain_info array with pid, path_prefix, name
	 * @param int $uid of page
	 * @param int $sys_language_uid of page
	 * @return string path
	 */
	public function pathFromPage($domain_info, $uid, $sys_language_uid = null) {
		return $this->privatePathFromPage($domain_info, $uid, $sys_language_uid);
	}

	private function privatePathFromPage($domain_info, $uid, $sys_language_uid = null, $private_recursion = 0) {
		if ($uid == $domain_info['pid']) {
			$path = $domain_info['path_prefix'];
		} else {
			$page = $this->pageContext->getPage($uid);
			if ($sys_language_uid) {
				$page = $this->pageContext->getPageOverlay($page, $sys_language_uid);
			}

			$alias = trim($page['tx_awesome_url_alias']);
			$exclude = $private_recursion && $page['tx_awesome_url_exclude_sub'];
			if (!$exclude && mb_strpos($alias, '/') === 0) {
				$path = mb_substr($alias, 1, 255, 'UTF-8');
			} else {
				$path = '';
				if ($page['pid'] > 0) {
					$path = $this->privatePathFromPage($domain_info, $page['pid'], $sys_language_uid, 1, $uid);
				}

				if ($exclude) {
					// on exclusion we do not need to save the path, so just return the $path now

					return $path;
				} else {
					$path .= (strlen($path) ? '/' : '') . $this->fileNameASCIIPrefix($alias ? : $page['title']);
				}
			}
		}

		$uri_entry = $this->findUriByDomaiNameUri($domain_info['name'], $path);
		if ($uri_entry) {
			// found entry

			if ($uri_entry['uid_foreign'] == $uid && $uri_entry['sys_language_uid_foreign'] == $sys_language_uid) {
				// matching uid and language

				if ($uri_entry['status'] == 1) {
					// status active -> just return path (best case)
				} else {
					// not active -> let's reactivate the old entry
					$this->reactivate($uri_entry);
				}
			} else {
				// entry is matching an other object (uid/sys_language_uid)
				if ($uri_entry['status'] == 1) {
					// the entry is active, so we must use a different name
					$path = $this->name_suffix($domain_info['name'], $path, $uid, $sys_language_uid);
				} else {
					// the entry is not active, so we can take it over
					$this->reuse($uri_entry, $uid, $sys_language_uid);
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
	public function fileNameASCIIPrefix($inTitle, $maxTitleChars = 40) {
		$out = $GLOBALS['TSFE']->csConvObj->specCharsToASCII($GLOBALS['TSFE']->renderCharset, $inTitle);

		// Get replacement character
		$replacementChar = '-';
		$replacementChars = '_\-' . ($replacementChar != '_' && $replacementChar != '-' ? $replacementChar : '');
		$out = preg_replace('/[^' . self::CHAR_WHILTELIST . ']/', $replacementChar, trim(substr($out, 0, $maxTitleChars)));
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
		$uri_depth = count(explode('/', $uri));
		$domain_name_safe = $db->fullQuoteStr($domain_name, 'tx_awesome_url_uri');
		$uri_safe = $db->fullQuoteStr($uri, 'tx_awesome_url_uri');
		$res = $db->exec_SELECTquery('uid,status,uid_foreign,sys_language_uid_foreign', 'tx_awesome_url_uri', "domain_name = $domain_name_safe AND uri = $uri_safe AND uri_depth = $uri_depth");
		$row = $db->sql_fetch_assoc($res);
		$db->sql_free_result($res);

		return $row;
	}

	public function findStartingUriByDomaiNameUriFor($domain_name, $uri_start, $uid_foreign, $sys_language_uid_foreign) {
		$entries = array();
		$db = $this->db();
		$uri_length = mb_strlen($uri_start, 'UTF-8');
		$uri_depth = count(explode('/', $uri_start));
		$domain_name_safe = $db->fullQuoteStr($domain_name, 'tx_awesome_url_uri');
		$uri_like = strtr($uri_start, array('_' => '\_', '%' => '\%')) . '%';
		$uri_like_safe = $db->fullQuoteStr($uri_like, 'tx_awesome_url_uri');
		$where = "domain_name = $domain_name_safe AND uri LIKE $uri_like_safe AND uri_depth = $uri_depth AND uid_foreign = $uid_foreign AND sys_language_uid_foreign = $sys_language_uid_foreign";
		$res = $db->exec_SELECTquery('uid,uri,status,uid_foreign,sys_language_uid_foreign', 'tx_awesome_url_uri', $where);

		while ($row = $db->sql_fetch_assoc($res)) {
			$entries[mb_substr($row['uri'], $uri_length, 255, 'UTF-8')] = $row;
		}

		$db->sql_free_result($res);

		return $entries;
	}

	public function findStartingUriByDomaiNameUri($domain_name, $uri_start, $not_uid_foreign = null, $not_sys_language_uid_foreign = null) {
		$entries = array();
		$db = $this->db();
		$uri_length = mb_strlen($uri_start, 'UTF-8');
		$uri_depth = count(explode('/', $uri_start));
		$domain_name_safe = $db->fullQuoteStr($domain_name, 'tx_awesome_url_uri');
		$uri_like = strtr($uri_start, array('_' => '\_', '%' => '\%')) . '%';
		$uri_like_safe = $db->fullQuoteStr($uri_like, 'tx_awesome_url_uri');
		$where = "domain_name = $domain_name_safe AND uri LIKE $uri_like_safe AND uri_depth = $uri_depth";
		if ($not_uid_foreign !== null && $not_sys_language_uid_foreign !== null) {
			$where .= " AND uid_foreign != $not_uid_foreign AND sys_language_uid_foreign != $not_sys_language_uid_foreign";
		}
		$res = $db->exec_SELECTquery('uid,uri,status,uid_foreign,sys_language_uid_foreign', 'tx_awesome_url_uri', $where);

		while ($row = $db->sql_fetch_assoc($res)) {
			$entries[mb_substr($row['uri'], $uri_length, 255, 'UTF-8')] = $row;
		}

		$db->sql_free_result($res);

		return $entries;
	}

	private function deactivate($uid_foreign, $sys_language_uid_foreign, $without_uid) {
		$db = $this->db();

		$res = $db->exec_UPDATEquery('tx_awesome_url_uri', "status = 1 AND uid_foreign = $uid_foreign AND sys_language_uid_foreign = $sys_language_uid_foreign AND uid != $without_uid", array('status' => 0));
		$db->sql_free_result($res);
	}

	private function insert($domain_name, $uri, $status, $uid_foreign, $sys_language_uid_foreign) {
		$db = $this->db();
		$uri_depth = count(explode('/', $uri));

		$db->exec_INSERTquery('tx_awesome_url_uri', array(
			'domain_name' => $domain_name,
			'uri' => $uri,
			'uri_depth' => $uri_depth,
			'status' => $status,
			'uid_foreign' => $uid_foreign,
			'sys_language_uid_foreign' => $sys_language_uid_foreign
		));

		$uid = $db->sql_insert_id();
		$db->sql_free_result($res);

		if ($uid) {
			$this->deactivate($uid_foreign, $sys_language_uid_foreign, $uid);
		}

		return $uid;
	}

	private function reactivate($uri_entry) {
		$db = $this->db();
		$uid = $uri_entry['uid'];
		$uid_foreign = $uri_entry['uid_foreign'];
		$sys_language_uid_foreign = $uri_entry['sys_language_uid_foreign'];

		$res = $db->exec_UPDATEquery('tx_awesome_url_uri', "uid = $uid", array('status' => 1));
		$db->sql_free_result($res);

		$this->deactivate($uid_foreign, $sys_language_uid_foreign, $uid);
	}

	private function reuse($uri_entry, $uid_foreign, $sys_language_uid_foreign) {
		$db = $this->db();
		$uid = $uri_entry['uid'];

		$res = $db->exec_UPDATEquery('tx_awesome_url_uri', "uid = $uid", array(
			'status' => 1,
			'uid_foreign' => $uid_foreign,
			'sys_language_uid_foreign' => $sys_language_uid_foreign
		));
		$db->sql_free_result($res);

		$this->deactivate($uid_foreign, $sys_language_uid_foreign, $uid);
	}

	private function name_suffix($domain_name, $uri, $uid_foreign, $sys_language_uid_foreign) {
		// reuse entries for same target
		$entries = $this->findStartingUriByDomaiNameUriFor($domain_name, $uri, $uid_foreign, $sys_language_uid_foreign);
		$shortest = null;
		foreach ($entries as $suffix => $entry) {
			if ($entry['status'] == 1) {
				return $entry['uri'];
			}

			if ($shortest === null || strlen($suffix) < strlen($shortest)) {
				$shortest = $suffix;
			}
		}

		if ($shortest !== null) {
			$this->reactivate($entries[$shortest]);
			return $entries[$shortest]['uri'];
		}

		// reuse entries for other targets or create new one

		$db = $this->db();

		$existing = $this->findStartingUriByDomaiNameUri($domain_name, $uri);
		$i = 1;

		while (true) {
			$i++; // we want to start with 2
			$suffix = '-' . $i;
			if (array_key_exists($suffix, $existing)) {
				if ($existing[$suffix]['status'] == 0) {
					$this->reuse($existing[$suffix], $uid_foreign, $sys_language_uid_foreign);
					return $existing[$suffix]['uri'];
				}
			} else {
				$uri_suffix = $uri . $suffix;
				$entry_uid = $this->insert($domain_name, $uri_suffix, 1, $uid_foreign, $sys_language_uid_foreign);

				if ($entry_uid) {
					return $uri_suffix;
				}
			}
		}
	}

}
