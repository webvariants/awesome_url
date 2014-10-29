<?php

namespace WV\AwesomeUrl\Validate;

// Registered in ext_localconf.php

use WV\AwesomeUrl\Service\UriBuilder;

class Alias {

	public function __construct() {
		
	}

	public function returnFieldJS() {
		$whitelist = UriBuilder::CHAR_WHILTELIST;
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

		$whitelist = UriBuilder::CHAR_WHILTELIST;

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
