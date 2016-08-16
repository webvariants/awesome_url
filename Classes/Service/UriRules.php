<?php

/*
 * Copyright (c) 2016, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

namespace WV\AwesomeUrl\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class UriRules implements \TYPO3\CMS\Core\SingletonInterface
{
    /**
     * @var array
     */
    protected $rules = array();

    /**
     * @var array
     */
    protected $tables = array();

    public function addRule($rule)
    {
        $this->rules[] = $rule;

        if (array_key_exists('table', $rule)) {
            if (!in_array($rule['table'], $this->tables)) {
                $this->tables[] = $rule['table'];
            }
        }

        return $this;
    }

    private function matchVars($rule, $linkVarsArray)
    {
        if (array_key_exists('params', $rule) && $linkVarsArray) {
            $intersect = array_intersect_key($linkVarsArray, $rule['params']);
            if (count($intersect) === count($rule['params'])) {
                return $intersect;
            }
        }

        return false;
    }

    public function doRule(&$linkVarsArray, $sys_language_uid)
    {
        foreach ($this->rules as $rule) {
            $matchVars = $this->matchVars($rule, $linkVarsArray);
            if ($matchVars) {
                $doneRule = null;
                foreach ($rule['params'] as $param => $paramHandle) {
                    $value = $linkVarsArray[$param];

                    if (is_array($paramHandle)) {
                        switch ($paramHandle['type']) {
                            case 'table':
                                $doneRule = $this->tableRule($paramHandle, $value, $sys_language_uid);

                                break;
                            default:
                                throw new \Exception('UriRules: wrong type');
                        }
                    }
                }

                if ($doneRule && is_array($doneRule)) {
                    if (!array_key_exists('title', $doneRule)) {
                        return false;
                    }

                    foreach ($matchVars as $key => $value) {
                        unset($linkVarsArray[$key]);
                    }

                    ksort($matchVars, SORT_STRING);

                    $doneRule['vars'] = $matchVars;
                    $doneRule['name'] = $rule['name'];
                    $doneRule['get_params'] = ltrim(GeneralUtility::implodeArrayForUrl('', $matchVars, '', false, true), '&');
                    if (!array_key_exists('table', $doneRule)) {
                        $doneRule['table'] = '';
                    }
                    if (!array_key_exists('uid', $doneRule)) {
                        $doneRule['uid'] = 0;
                    }

                    return $doneRule;
                }
            }
        }

        return false;
    }

    private function tableRule($params, $uid, $sys_language_uid)
    {
        $db = $this->db();
        $uid_field = $params['uid'];
        $table = $params['table'];
        $title_fields = $params['title'];
        $L_field = array_key_exists('L', $params) ? $params['L'] : null;
        $Lall = array_key_exists('Lall', $params) ? $params['Lall'] : null;
        $l10n_parent = array_key_exists('l10n_parent', $params) ? $params['l10n_parent'] : null;
        $uid_safe = $db->fullQuoteStr($uid, $table);
        $L_uid = $db->fullQuoteStr($sys_language_uid, $table);
        $select = $params['title'];
        $select[] = $uid_field;
        $where = array();

        if ($l10n_parent) {
            $select[] = $l10n_parent;
        }

        if ($l10n_parent && $sys_language_uid) {
            $where[] = '('.$uid_field.'='.$uid_safe.' OR '.$l10n_parent.'='.$uid_safe.')';
        } else {
            $where[] = $uid_field.' = '.$uid_safe;
        }

        if ($L_field) {
            if ($Lall) {
                $where[] = '('.$L_field.'='.$L_uid.' OR '.$L_field.'='.$db->fullQuoteStr($Lall, $table).')';
            } else {
                $where[] = $L_field.'='.$L_uid;
            }
        }

        $row = null;
        $res = $db->exec_SELECTquery(implode(',', $select), $table, implode(' AND ', $where));
        while ($i = $db->sql_fetch_assoc($res)) {
            if ($Lall && $l10n_parent && $i[$L_field] != $Lall && $i[$l10n_parent] == $uid) {
                $row = $i;
                // perfect match, so break loop
                break;
            } elseif ($Lall && $l10n_parent && $i[$L_field] != $Lall) {
                $row = $i;
            } elseif (!$Lall && $l10n_parent && $i[$l10n_parent] == $uid) {
                $row = $i;
                // perfect match, so break loop
                break;
            } elseif (!$row) {
                $row = $i;
            }
        }
        $db->sql_free_result($res);

        if (!$row) {
            return false;
        }

        $title = '';
        foreach ($title_fields as $field) {
            if (trim($row[$field])) {
                $title = $row[$field];
                break;
            }
        }

        return array(
            'title' => $title ?: $row[$uid_field],
            'uid' => $row[$uid_field],
            'table' => $table,
        );
    }

    public function hasTable($table)
    {
        return in_array($table, $this->tables);
    }

    /**
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection;
     */
    private function db()
    {
        return $GLOBALS['TYPO3_DB'];
    }
}
