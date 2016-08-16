<?php

/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace WV\AwesomeUrl\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;

// Hook done in ext_localconf.php

class UriBuilder implements \TYPO3\CMS\Core\SingletonInterface
{
    /**
     * @var \TYPO3\CMS\Frontend\Page\PageRepository
     */
    private $pageContext = null;

    /**
     * @var \TYPO3\CMS\Core\Charset\CharsetConverter
     */
    private $csConvObj = null;
    private $time = null;

    public function __construct()
    {
        $this->pageContext = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
        $this->csConvObj = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Charset\\CharsetConverter');
    }

    /**
     * @param array $domain_info      array with pid, path_prefix, name
     * @param int   $uid              of page
     * @param int   $sys_language_uid of page
     * @param array $rule
     * @param int   $last_change      last change of elements in the rootline for better performance
     *
     * @return string path
     */
    public function pathForPage($domain_info, $uid, $sys_language_uid = null, $rule = false, $last_change = null)
    {
        return $this->privatePathForPage($domain_info, $uid, $sys_language_uid, $rule, $last_change);
    }

    private function privatePathForPage($domain_info, $uid, $sys_language_uid = null, $rule = false, $last_change = null, $private_recursion = 0)
    {
        if ($last_change) {
            $active_entry = $this->findActive($uid, $sys_language_uid, $rule);
            if ($active_entry && $last_change <= $active_entry['tstamp'] && $domain_info['name'] == $active_entry['domain_name']) {
                return $active_entry['uri'];
            }
        }

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
                $path = mb_substr($alias, 1, 220, 'UTF-8');
            } else {
                $path = '';
                if ($page['pid'] > 0) {
                    $path = $this->privatePathForPage($domain_info, $page['pid'], $sys_language_uid, false, $last_change, $uid);
                }

                if ($exclude) {
                    // on exclusion we do not need to save the path, so just return the $path now

                    return $path;
                } else {
                    $path .= (strlen($path) ? '/' : '').($alias ?: $this->titleConvert($page['title']));
                }
            }
        }

        $rule_name = '';
        $rule_get_params = '';
        $rule_table = '';
        $rule_uid = 0;

        if ($rule) {
            $path .= (strlen($path) ? '/' : '').$this->titleConvert($rule['title']);
            $rule_name = $rule['name'];
            $rule_get_params = $rule['get_params'];
        }

        $uri_entry = $this->findUriByDomaiNameUri($domain_info['name'], $path);
        if ($uri_entry) {
            // found entry

            if ($uri_entry['uid_foreign'] == $uid
                    && $uri_entry['sys_language_uid_foreign'] == $sys_language_uid
                    && $uri_entry['rule'] === $rule_name
                    && $uri_entry['get_params'] == $rule_get_params
                ) {
                // matching uid and language

                if ($uri_entry['status'] == 1) {
                    // status active -> just return path (best case)

                    if ($last_change) {
                        // caching did not hit, we must update tstamp
                        $this->reactivate($uri_entry, $rule);
                    }
                } else {
                    // not active -> let's reactivate the old entry
                    $this->reactivate($uri_entry, $rule);
                }
            } else {
                // entry is matching an other object (uid/sys_language_uid/rule/get_params)
                if ($uri_entry['status'] == 1) {
                    // the entry is active, so we must use a different name
                    $path = $this->name_suffix($domain_info['name'], $path, $uid, $sys_language_uid, $rule, $last_change);
                } else {
                    // the entry is not active, so we can take it over
                    $this->reuse($uri_entry, $uid, $sys_language_uid, $rule_name, $rule_get_params);
                }
            }
        } else {
            $this->insert($domain_info['name'], $path, 1, $uid, $sys_language_uid, $rule);
        }

        return $path;
    }

    private function titleConvert($title)
    {
        $ret = $this->csConvObj->specCharsToASCII('utf-8', $title);

        // Get replacement character
        $replacementChar = $this->getCharReplaceWith(true);
        $replacementChars = $this->getCharReplaceWith(false);
        $ret = preg_replace('/[^'.$this->getCharWhitelist().']/', $replacementChar, trim(substr($ret, 0, $this->getTitleMax())));
        $ret = preg_replace('/(['.$replacementChars.']){2,}/', $replacementChar, $ret); // groups of replacement chars
        $ret = preg_replace('/['.$replacementChars.']?$/', '', $ret); // replacement chars at end
        $ret = preg_replace('/^['.$replacementChars.']?/', '', $ret); // replacement chars at start

        return strtolower($ret);
    }

    /**
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection;
     */
    private function db()
    {
        return $GLOBALS['TYPO3_DB'];
    }

    public function findUriByDomaiNameUri($domain_name, $uri)
    {
        $db = $this->db();
        $uri_depth = count(explode('/', $uri));
        $domain_name_safe = $db->fullQuoteStr($domain_name, 'tx_awesome_url_uri');
        $uri_safe = $db->fullQuoteStr($uri, 'tx_awesome_url_uri');
        $where = "domain_name = $domain_name_safe AND uri = $uri_safe AND uri_depth = $uri_depth";
        $res = $db->exec_SELECTquery('uid,status,uid_foreign,sys_language_uid_foreign,rule,get_params', 'tx_awesome_url_uri', $where);
        $row = $db->sql_fetch_assoc($res);
        $db->sql_free_result($res);

        return $row;
    }

    public function findActive($uid_foreign, $sys_language_uid_foreign, $rule = false)
    {
        $db = $this->db();
        $where = "status = 1 AND uid_foreign = $uid_foreign AND sys_language_uid_foreign = $sys_language_uid_foreign";
        if ($rule) {
            $where .= ' AND get_params = '.$db->fullQuoteStr($rule['get_params'], 'tx_awesome_url_uri');
            $where .= ' AND rule = '.$db->fullQuoteStr($rule['name'], 'tx_awesome_url_uri');
        } else {
            $where .= ' AND get_params = "" AND rule = ""';
        }
        $res = $db->exec_SELECTquery('uid,domain_name,uri,status,uid_foreign,sys_language_uid_foreign,tstamp', 'tx_awesome_url_uri', $where);
        $row = $db->sql_fetch_assoc($res);
        $db->sql_free_result($res);

        return $row;
    }

    private function findStartingUriByDomaiNameUriFor($domain_name, $uri_start, $uid_foreign, $sys_language_uid_foreign, $rule = false)
    {
        $entries = array();
        $db = $this->db();
        $uri_length = mb_strlen($uri_start, 'UTF-8');
        $uri_depth = count(explode('/', $uri_start));
        $domain_name_safe = $db->fullQuoteStr($domain_name, 'tx_awesome_url_uri');
        $uri_like = strtr($uri_start, array('_' => '\_', '%' => '\%')).'%';
        $uri_like_safe = $db->fullQuoteStr($uri_like, 'tx_awesome_url_uri');
        $where = "domain_name = $domain_name_safe AND uri LIKE $uri_like_safe AND uri_depth = $uri_depth AND uid_foreign = $uid_foreign AND sys_language_uid_foreign = $sys_language_uid_foreign";
        $where .= ' AND rule = '.$db->fullQuoteStr($rule ? $rule['name'] : '', 'tx_awesome_url_uri');
        $where .= ' AND get_params = '.$db->fullQuoteStr($rule ? $rule['get_params'] : '', 'tx_awesome_url_uri');
        $res = $db->exec_SELECTquery('uid,uri,status,uid_foreign,sys_language_uid_foreign', 'tx_awesome_url_uri', $where);

        while ($row = $db->sql_fetch_assoc($res)) {
            $entries[mb_substr($row['uri'], $uri_length, 220, 'UTF-8')] = $row;
        }

        $db->sql_free_result($res);

        return $entries;
    }

    public function findStartingUriByDomaiNameUri($domain_name, $uri_start, $not_uid_foreign = null, $not_sys_language_uid_foreign = null, $rule = false)
    {
        $entries = array();
        $db = $this->db();
        $uri_length = mb_strlen($uri_start, 'UTF-8');
        $uri_depth = count(explode('/', $uri_start));
        $domain_name_safe = $db->fullQuoteStr($domain_name, 'tx_awesome_url_uri');
        $uri_like = strtr($uri_start, array('_' => '\_', '%' => '\%')).'%';
        $uri_like_safe = $db->fullQuoteStr($uri_like, 'tx_awesome_url_uri');
        $where = "domain_name = $domain_name_safe AND uri LIKE $uri_like_safe AND uri_depth = $uri_depth";
        if ($not_uid_foreign !== null && $not_sys_language_uid_foreign !== null) {
            $where .= " AND uid_foreign != $not_uid_foreign AND sys_language_uid_foreign != $not_sys_language_uid_foreign";
        }
        $where .= ' AND rule = '.$db->fullQuoteStr($rule ? $rule['name'] : '', 'tx_awesome_url_uri');
        $where .= ' AND get_params = '.$db->fullQuoteStr($rule ? $rule['get_params'] : '', 'tx_awesome_url_uri');
        $res = $db->exec_SELECTquery('uid,uri,status,uid_foreign,sys_language_uid_foreign', 'tx_awesome_url_uri', $where);

        while ($row = $db->sql_fetch_assoc($res)) {
            $entries[mb_substr($row['uri'], $uri_length, 220, 'UTF-8')] = $row;
        }

        $db->sql_free_result($res);

        return $entries;
    }

    public function getCharWhitelist()
    {
        return $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['awesome_url']['charsWhitelist'];
    }

    public function getCharWhitelistAlias()
    {
        return $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['awesome_url']['charsWhitelistAlias'];
    }

    public function getCharReplaceWith($first_char = false)
    {
        $chars = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['awesome_url']['charsReplaceWith'];
        if ($first_char) {
            return mb_substr($chars, 0, 1, 'UTF-8');
        } else {
            return $chars;
        }
    }

    public function getTitleMax()
    {
        return (int) $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['awesome_url']['titleMax'];
    }

    private function deactivate($uid_foreign, $sys_language_uid_foreign, $without_uid, $rule = false)
    {
        $db = $this->db();
        $where = "status = 1 AND uid_foreign = $uid_foreign AND sys_language_uid_foreign = $sys_language_uid_foreign AND uid != $without_uid";
        $where .= ' AND rule = '.$db->fullQuoteStr($rule ? $rule['name'] : '', 'tx_awesome_url_uri');
        $where .= ' AND get_params = '.$db->fullQuoteStr($rule ? $rule['get_params'] : '', 'tx_awesome_url_uri');

        $res = $db->exec_UPDATEquery('tx_awesome_url_uri', $where, array('status' => 0));
        $db->sql_free_result($res);
    }

    private function insert($domain_name, $uri, $status, $uid_foreign, $sys_language_uid_foreign, $rule = false)
    {
        $db = $this->db();
        $uri_depth = count(explode('/', $uri));

        $db->exec_INSERTquery('tx_awesome_url_uri', array(
            'domain_name' => $domain_name,
            'uri' => $uri,
            'uri_depth' => $uri_depth,
            'status' => $status,
            'uid_foreign' => $uid_foreign,
            'sys_language_uid_foreign' => $sys_language_uid_foreign,
            'tstamp' => $this->time(),
            'rule' => $rule ? $rule['name'] : '',
            'get_params' => $rule ? $rule['get_params'] : '',
            'rule_table' => $rule ? $rule['table'] : '',
            'rule_uid' => $rule ? $rule['uid'] : 0,
        ));

        $uid = $db->sql_insert_id();
        $db->sql_free_result($res);

        if ($uid) {
            $this->deactivate($uid_foreign, $sys_language_uid_foreign, $uid, $rule);
        }

        return $uid;
    }

    private function reactivate($uri_entry, $rule = false)
    {
        $db = $this->db();
        $uid = $uri_entry['uid'];
        $uid_foreign = $uri_entry['uid_foreign'];
        $sys_language_uid_foreign = $uri_entry['sys_language_uid_foreign'];

        $res = $db->exec_UPDATEquery('tx_awesome_url_uri', "uid = $uid", array(
            'status' => 1,
            'tstamp' => $this->time(),
        ));
        $db->sql_free_result($res);

        $this->deactivate($uid_foreign, $sys_language_uid_foreign, $uid, $rule);
    }

    private function reuse($uri_entry, $uid_foreign, $sys_language_uid_foreign, $rule = false)
    {
        $db = $this->db();
        $uid = $uri_entry['uid'];

        $res = $db->exec_UPDATEquery('tx_awesome_url_uri', "uid = $uid", array(
            'status' => 1,
            'uid_foreign' => $uid_foreign,
            'sys_language_uid_foreign' => $sys_language_uid_foreign,
            'tstamp' => $this->time(),
            'rule' => $rule ? $rule['name'] : '',
            'get_params' => $rule ? $rule['get_params'] : '',
            'rule_table' => $rule ? $rule['table'] : '',
            'rule_uid' => $rule ? $rule['uid'] : 0,
        ));
        $db->sql_free_result($res);

        $this->deactivate($uid_foreign, $sys_language_uid_foreign, $uid, $rule);
    }

    private function name_suffix($domain_name, $uri, $uid_foreign, $sys_language_uid_foreign, $rule = false, $last_change = null)
    {
        // reuse entries for same target
        $entries = $this->findStartingUriByDomaiNameUriFor($domain_name, $uri, $uid_foreign, $sys_language_uid_foreign, $rule);
        $shortest = null;
        foreach ($entries as $suffix => $entry) {
            if ($entry['status'] == 1) {
                if ($last_change) {
                    // caching did not hit, we must update tstamp
                    $this->reactivate($entry, $rule);
                }

                return $entry['uri'];
            }

            if ($shortest === null || strlen($suffix) < strlen($shortest)) {
                $shortest = $suffix;
            }
        }

        if ($shortest !== null) {
            $this->reactivate($entries[$shortest], $rule);

            return $entries[$shortest]['uri'];
        }

        // reuse entries for other targets or create new one

        $db = $this->db();

        $existing = $this->findStartingUriByDomaiNameUri($domain_name, $uri, null, null, $rule);
        $i = 1;

        while (true) {
            ++$i; // we want to start with 2
            $suffix = '-'.$i;
            if (array_key_exists($suffix, $existing)) {
                if ($existing[$suffix]['status'] == 0) {
                    $this->reuse($existing[$suffix], $uid_foreign, $sys_language_uid_foreign, $rule);

                    return $existing[$suffix]['uri'];
                }
            } else {
                $uri_suffix = $uri.$suffix;
                $entry_uid = $this->insert($domain_name, $uri_suffix, 1, $uid_foreign, $sys_language_uid_foreign, $rule);

                if ($entry_uid) {
                    return $uri_suffix;
                }
            }
        }
    }

    private function time()
    {
        // safe some kernel calls

        if ($this->time === null) {
            $this->time = time();
        }

        return $this->time;
    }
}
