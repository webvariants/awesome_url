<?php

/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace WV\AwesomeUrl\Validate;

// Registered in ext_localconf.php

use TYPO3\CMS\Core\Utility\GeneralUtility;

class Alias {

	/**
	 *
	 * @var \WV\AwesomeUrl\Service\UriBuilder
	 */
	private $uri_builder = null;

	public function __construct() {
		$this->uri_builder = GeneralUtility::makeInstance('WV\\AwesomeUrl\\Service\\UriBuilder');
	}

	public function returnFieldJS() {
		$whitelist = $this->uri_builder->getCharWhitelistAlias();
		$length = $this->getLength();
		return ""
				. "var awesome_url_alias = value.replace(/[^$whitelist\/]/g, '').replace(/\/{2,}/g, '/');"
				. "var awesome_url_alias_match = awesome_url_alias.match(/^(.*)\/$/);"
				. "return (awesome_url_alias_match ? awesome_url_alias_match[1] : awesome_url_alias).substr(0, $length);";
	}

	public function evaluateFieldValue($value, $is_in, &$set) {
		$trim = rtrim(trim($value), '/');
		if (mb_strlen($trim, 'UTF-8') === 0) {
			return $trim;
		}

		$set = true;

		$whitelist = $this->uri_builder->getCharWhitelistAlias();

		if ($set && !preg_match("#^[$whitelist/]*$#", $trim)) {
			$set = false;
		}

		if ($set && mb_strpos($trim, '//') !== false) {
			$set = false;
		}

		if ($set && mb_strlen($trim, 'UTF-8') > $this->getLength()) {
			$set = false;
		}

		return $trim;
	}

	private function getLength() {
		return (int) $GLOBALS['TCA']['pages']['columns']['tx_awesome_url_alias']['config']['size'];
	}

}
