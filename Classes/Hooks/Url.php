<?php

/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

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

	/**
	 *
	 * @var \WV\AwesomeUrl\Service\UriBuilder
	 */
	private $uri_builder = null;

	public function __construct() {
		$this->pageContext = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
		$this->uri_builder = GeneralUtility::makeInstance('WV\\AwesomeUrl\\Service\\UriBuilder');
	}

	/**
	 *
	 * @param array $params with keys LD, args, typeNum
	 * @param TYPO3\CMS\Core\TypoScript\TemplateService $ts
	 */
	public function linkDataPost(&$params, $ts) {
		/* @var $ts \TYPO3\CMS\Core\TypoScript\TemplateService */
		$page = $params['args']['page'];
		$sys_language_uid = (int) $GLOBALS['TSFE']->sys_language_uid;

		if ($page) {
			$current_site_uid = (int) $ts->rootLine[0]['uid'];
			$target_rootline = $this->pageContext->getRootLine($page['uid']);
			$target_site_uid = (int) $target_rootline[0]['uid'];
			$linkVars = $params['LD']['linkVars'];
			$linkVarsArray = GeneralUtility::explodeUrl2Array($linkVars);
			$target_language_uid = $this->extractLanguage($linkVarsArray);

			$new_domain = null;
			$new_scheme = null;

			$tstamp_rootline = 0;
			foreach ($target_rootline as $page_i) {
				$tstamp_rootline = max($tstamp_rootline, $page_i['tstamp']);
			}

			if ($params['LD']['type']) {
				$type = array();
				parse_str($params['LD']['type'], $type);
				foreach ($type as $t_key => $t_value) {
					$linkVarsArray[$t_key] = $t_value;
				}
			}

			$domain_info = $this->fetchDomainInfo($target_site_uid, $target_language_uid);

			if (!$domain_info) {
				// without domain info we can not generate urls

				return;
			}

			if ($domain_info['is_language_domain']) {
				$this->unsetLinkVar('L', $linkVarsArray);
			}

			if ($current_site_uid != $target_site_uid || $sys_language_uid != $target_language_uid) {
				if ($domain_info['name'] != GeneralUtility::getIndpEnv('HTTP_HOST')) {
					$new_domain = $domain_info['name'];
					$new_scheme = $this->scheme($page);
				}
			}

			$urlParts = parse_url($params['LD']['totalURL']);

			$urlParts['path'] = $this->uri_builder->pathForPage($domain_info, $page['uid'], $target_language_uid, $tstamp_rootline);
			$this->unsetLinkVar('id', $linkVarsArray);

			if ($new_domain) {
				$urlParts['host'] = $new_domain;
			}
			if ($new_scheme) {
				if (array_key_exists('port', $urlParts)) {
					if (array_key_exists('scheme', $urlParts) && $urlParts['scheme'] != $new_scheme) {
						unset($urlParts['port']);
					}
				}
				$urlParts['scheme'] = $new_scheme;
			}

			$urlParts['query'] = $linkVarsArray ? ltrim(GeneralUtility::implodeArrayForUrl('', $linkVarsArray, '', false, true), '&') : '';

			$params['LD']['totalURL'] = $this->build_url($urlParts);
		}
	}

	/**
	 *
	 * @param array $parameters
	 * @param \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $parentObject
	 * @return void
	 */
	public function checkAlternativeIdMethodsPost(array &$parameters, &$parentObject) {
		$siteScript = $parentObject->siteScript;

		$can_redirect = $_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'HEAD';
		$hit = false;
		$redirect_language_uid = null;
		$exclude_query_params = array();

		if (substr($siteScript, 0, 9) == 'index.php') {
			if ($can_redirect && $parentObject->id && $parentObject->id == GeneralUtility::_GET('id')) {
				$redirect_language_uid = (int) GeneralUtility::_GET('L');
				$exclude_query_params[] = 'id';
				$exclude_query_params[] = 'L';
				$hit = true;
			}
		} else {
			$path = parse_url($siteScript, PHP_URL_PATH);

			$simulatestatic = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['awesome_url']['simulatestatic'];
			if ($simulatestatic && $path) {
				if (substr($path, -5) == '.html') {
					if (preg_match('/(.(\d+))?\.(\d+)\.html$/', $path, $matches)) {
						if ($matches[2] === '') {
							$parentObject->type = 0;
							$parentObject->id = $matches[3];
						} else {
							$parentObject->type = $matches[3];
							$parentObject->id = $matches[2];
						}

						$hit = true;

						if ($can_redirect) {
							if ($parentObject->type) {
								$_GET['type'] = $parentObject->type;
							}
						} else {
							return;
						}
					}
				}
			}

			if (!$hit) {
				$uri_entry = $this->uri_builder->findUriByDomaiNameUri(GeneralUtility::getIndpEnv('HTTP_HOST'), $path);
				if ($uri_entry) {
//					$parentObject->type = 0;
					$parentObject->id = $uri_entry['uid_foreign'];
//					$parentObject->sys_language_uid = $uri_entry['sys_language_uid_foreign']; // seems not nessesary
					if ($uri_entry['sys_language_uid_foreign']) {
						$_GET['L'] = $uri_entry['sys_language_uid_foreign'];
					}

					if ($uri_entry['status'] == 1) {
						$can_redirect = false;
					}

					$hit = true;
					$redirect_language_uid = (int) GeneralUtility::_GET('L');
				}
			}
		}

		if ($can_redirect && $hit) {
			$this->redirect((int) $parentObject->id, $redirect_language_uid, $exclude_query_params);
		}
	}

	private function redirect($uid, $sys_language_uid = null, $exlcude_query_params = array()) {
		$target_rootline = $this->pageContext->getRootLine($uid);
		if (!$target_rootline) {

			return;
		}
		$target_site_uid = (int) $target_rootline[0]['uid'];

		$domain_info = $this->fetchDomainInfo($target_site_uid, $sys_language_uid, $sys_language_uid === null ? GeneralUtility::getIndpEnv('HTTP_HOST') : null);

		if (!$domain_info) {
			// without domain info we can not generate urls

			return;
		}

		if ($sys_language_uid === null) {
			if ($domain_info['is_language_domain']) {
				$sys_language_uid = $domain_info['sys_language_uid'];
			} else {
				$sys_language_uid = 0;
			}
		}

		$query_array = $_GET;
		if ($exlcude_query_params) {
			foreach ($exlcude_query_params as $param) {
				if (array_key_exists($param, $query_array)) {
					unset($query_array[$param]);
				}
			}
		}

		$query = ltrim(GeneralUtility::implodeArrayForUrl('', $query, '', false, true), '&');
		if ($query !== '') {
			$query = '?' . $query;
		}

		$requestUrlScheme = parse_url(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'), PHP_URL_SCHEME);
		$path = $this->uri_builder->pathForPage($domain_info, $uid, $sys_language_uid);
		HttpUtility::redirect(($requestUrlScheme ? : 'http') . '://' . $domain_info['name'] . '/' . $path . $query, HttpUtility::HTTP_STATUS_301);
	}

	/**
	 *
	 * @return \TYPO3\CMS\Core\Database\DatabaseConnection;
	 */
	private function db() {
		return $GLOBALS['TYPO3_DB'];
	}

	private function fetchDomainInfo($target_site_uid, $target_language_uid = null, $domain_name = null) {
		$db = $this->db();
		$target_site_uid_safe = $db->fullQuoteStr($target_site_uid, 'sys_domain');
		$res = $db->exec_SELECTquery('sys_domain.domainName,tx_awesome_url_domain.path_prefix,tx_awesome_url_domain.sys_language_uid', 'sys_domain,tx_awesome_url_domain', "sys_domain.uid = tx_awesome_url_domain.uid_foreign AND sys_domain.hidden = 0 AND tx_awesome_url_domain.deleted = 0 AND sys_domain.pid = $target_site_uid_safe", '', 'sys_domain.sorting ASC,tx_awesome_url_domain.sorting ASC');

		$targetDomain = null;
		$path_prefix = '';
		$is_language_domain = false;
		while ((!$targetDomain) && ($row = $db->sql_fetch_assoc($res))) {
			$row_sys_language_uid = $row['sys_language_uid'];

			if (($row_sys_language_uid == -1 || $target_language_uid === null || $row_sys_language_uid == $target_language_uid) && ($domain_name === null || $domain_name == $row['domainName'])) {
				$targetDomain = $row['domainName'];
				$path_prefix = trim($row['path_prefix'], "/ \t");
				if ($row_sys_language_uid > -1) {
					$is_language_domain = true;
				}
			}
		}
		$db->sql_free_result($res);

		if ($targetDomain === null) {

			return null;
		}

		return array(
			'name' => $targetDomain,
			'pid' => $target_site_uid,
			'path_prefix' => $path_prefix,
			'is_language_domain' => $is_language_domain,
			'sys_language_uid' => $row_sys_language_uid
		);
	}

	private function extractLanguage($linkVarsArray) {
		$sys_language_uid = (int) $GLOBALS['TSFE']->sys_language_uid;
		$L = array_key_exists('L', $linkVarsArray) ? $linkVarsArray['L'] : null;
		$target_language_uid = $sys_language_uid;
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

	private function unsetLinkVar($var, &$linkVarsArray) {
		if (array_key_exists($var, $linkVarsArray)) {
			unset($linkVarsArray[$var]);
		}
	}

}
