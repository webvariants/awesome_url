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

// Hook done in ext_localconf.php

class DataHandler
{
    /**
     * @var \WV\AwesomeUrl\Service\UriRules
     */
    private $uri_rules = null;

    public function __construct()
    {
        $this->uri_rules = GeneralUtility::makeInstance('WV\\AwesomeUrl\\Service\\UriRules');
    }

    public function processCmdmap_deleteAction($table, $id, $recordToDelete, $recordWasDeleted, $data_handler)
    {
        /* @var $data_handler \TYPO3\CMS\Core\DataHandling\DataHandler */

        if ($table === 'pages') {
            $this->deactivate($id);
        } elseif ($table === 'pages_language_overlay') {
            $this->deactivate($recordToDelete['pid'], $recordToDelete['sys_language_uid']);
        } elseif ($this->uri_rules->hasTable($table)) {
            $this->deactivateTable($table, $id);
        }
    }

    /**
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection;
     */
    private function db()
    {
        return $GLOBALS['TYPO3_DB'];
    }

    private function deactivate($uid_foreign, $sys_language_uid_foreign = null)
    {
        $db = $this->db();

        $where = "status = 1 AND uid_foreign = $uid_foreign";
        if ($sys_language_uid_foreign !== null) {
            $where .= " AND sys_language_uid_foreign = $sys_language_uid_foreign";
        }

        $res = $db->exec_UPDATEquery('tx_awesome_url_uri', $where, array('status' => 0));
        $db->sql_free_result($res);
    }

    private function deactivateTable($table, $uid)
    {
        $db = $this->db();

        $where = 'status = 1 AND rule_table = '.$db->fullQuoteStr($table, 'tx_awesome_url_uri');
        $where .= ' AND rule_uid = '.$db->fullQuoteStr($uid, 'tx_awesome_url_uri');

        $res = $db->exec_UPDATEquery('tx_awesome_url_uri', $where, array('status' => 0));
        $db->sql_free_result($res);
    }
}
