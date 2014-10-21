<?php

namespace WV\AwesomeUrl\Hooks;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;

// Hook done in ext_localconf.php

class Url {

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
	 * @param array $params with keys LD, args, typeNum
	 * @param TYPO3\CMS\Core\TypoScript\TemplateService $ts
	 */
	public function linkDataPost(&$params, $ts) {
		/* @var $ts \TYPO3\CMS\Core\TypoScript\TemplateService */
		$page = $params['args']['page'];

		if ($page) {
			$current_site_uid = (int) $ts->rootLine[0]['uid'];
			$target_rootline = $this->pageContext->getRootLine($page['uid']);
			$target_site_uid = (int) $target_rootline[0]['uid'];
			$linkVars = $params['LD']['linkVars'];
			$linkVarsArray = GeneralUtility::explodeUrl2Array($linkVars);
			$target_language_uid = $this->extractLanguage($linkVarsArray);

			$new_linkVars = null;
			$new_domain = null;
			$new_scheme = null;

			list($targetDomain, $path_prefix, $is_language_domain) = $this->fetchDomainInfo($target_site_uid, $target_language_uid);

			if ($is_language_domain && array_key_exists('L', $linkVarsArray)) {
				unset($linkVarsArray['L']);
				$new_linkVars = GeneralUtility::implodeArrayForUrl('', $linkVarsArray, '', false, true);
			}

			if ($current_site_uid != $target_site_uid || $this->sys_language_uid != $target_language_uid) {
				if ($targetDomain) {
					$new_domain = $targetDomain;
					$new_scheme = $this->scheme($page);
				}
			}

			$urlParts = parse_url($params['LD']['totalURL']);
			$change = false;
			if ($new_domain) {
				$urlParts['host'] = $new_domain;
				$change = true;
			}
			if ($new_scheme) {
				if (array_key_exists('port', $urlParts)) {
					if (array_key_exists('scheme', $urlParts) && $urlParts['scheme'] != $new_scheme) {
						unset($urlParts['port']);
					}
				}
				$urlParts['scheme'] = $new_scheme;
				$change = true;
			}
			if ($path_prefix) {
				$urlParts['path'] = $path_prefix . (array_key_exists('path', $urlParts) ? $urlParts['path'] : '');
				$change = true;
			}
			if ($new_linkVars !== null) {
				$urlParts['query'] = $new_linkVars;
				$change = true;
			}

			if ($change) {
				$params['LD']['totalURL'] = $this->build_url($urlParts);
			}
		}
	}

	/**
	 *
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection;
	 */
	private function db() {
		return $GLOBALS['TYPO3_DB'];
	}

	private function fetchDomainInfo($target_site_uid, $target_language_uid) {
		$db = $this->db();
		$target_site_uid_safe = $db->fullQuoteStr($target_site_uid, 'sys_domain');
		$res = $db->exec_SELECTquery('sys_domain.domainName,tx_awesome_url_domain.path_prefix,tx_awesome_url_domain.sys_language_uid', 'sys_domain,tx_awesome_url_domain', "sys_domain.uid = tx_awesome_url_domain.uid_foreign AND sys_domain.hidden = 0 AND tx_awesome_url_domain.deleted = 0 AND sys_domain.pid = $target_site_uid_safe", '', 'sys_domain.sorting ASC,tx_awesome_url_domain.sorting ASC');

		$targetDomain = null;
		$path_prefix = '';
		$is_language_domain = false;
		while ((!$targetDomain) && ($row = $db->sql_fetch_assoc($res))) {
			$row_sys_language_uid = $row['sys_language_uid'];

			if ($row_sys_language_uid == -1 || $row_sys_language_uid == $target_language_uid) {
				$targetDomain = $row['domainName'];
				$path_prefix = trim($row['path_prefix'], "/ \t");
				if ($path_prefix) {
					$path_prefix = '/' . $path_prefix;
				}
				if ($row_sys_language_uid > -1) {
					$is_language_domain = true;
				}
			}
		}
		$db->sql_free_result($res);

		return array($targetDomain, $path_prefix, $is_language_domain);
	}

	private function extractLanguage($linkVarsArray) {
		$L = array_key_exists('L', $linkVarsArray) ? $linkVarsArray['L'] : null;
		$target_language_uid = $this->sys_language_uid;
		if ($L !== null && strlen($L) && is_numeric($L)) {
			$target_language_uid = (int) $L;
		}

		return $target_language_uid;
	}

	private function scheme($page) {
		if ($page['url_scheme'] > 0) {
			if ((int) $page['url_scheme'] === HttpUtility::SCHEME_HTTP) {
				return 'http';
			} elseif ((int) $page['url_scheme'] === HttpUtility::SCHEME_HTTPS) {
				return 'https';
			}
		}

		return parse_url(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'), PHP_URL_SCHEME);
	}

	private function build_url($parts) {
		$url = '';
		if (array_key_exists('scheme', $parts)) {
			$url = $parts['scheme'] . ':';
		}
		if (array_key_exists('host', $parts)) {
			$url .= '//' . $parts['host'];
		} elseif ($url) { // do not let scheme stand alone without host
			$url .= '//' . GeneralUtility::getIndpEnv('HTTP_HOST');
		}
		if (array_key_exists('port', $parts) && $url) { // port only with host
			$url .= ':' . $parts['port'];
		}
		if (array_key_exists('path', $parts) && $parts['path']) {
			$url .= $parts['path'][0] == '/' ? $parts['path'] : '/' . $parts['path'];
		}
		if (array_key_exists('query', $parts) && strlen($parts['query'])) {
			$url .= '?' . $parts['query'];
		}

		return $url;
	}

}
