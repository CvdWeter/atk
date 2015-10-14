<?php
/**
 * This file is part of the ATK distribution on GitHub.
 * Detailed copyright and licensing information can be found
 * in the doc/COPYRIGHT and doc/LICENSE files which should be
 * included in the distribution.
 *
 * @package atk
 * @subpackage utils
 *
 * @copyright (c)2005 Ibuildings.nl BV
 * @license http://www.achievo.org/atk/licensing ATK Open Source License
 *
 * @version $Revision: 2955 $
 * $Id$
 */


/**
 * The atkEventLog is a ready to use atkActionListener for logging events
 * in a table.
 *
 * You can use the atkEventLog by adding an instance to a node using
 * Atk_Node's addListener() method.
 *
 * In order to use the atkEventLog, you have to have a table in the database
 * named 'atkeventlog' with the following structure:
 *
 * CREATE TABLE atkeventlog
 * (
 *   id INT(10),
 *   userid INT(10),
 *   stamp DATETIME,
 *   node VARCHAR(100),
 *   action VARCHAR(100),
 *   primarykey VARCHAR(255)
 * }
 *
 * The current implementation only supports the logging.
 * @todo Add visualisation of the log.
 *
 * @author Ivo Jansch <ivo@achievo.org>
 * @package atk
 * @subpackage utils
 */
class Atk_EventLog extends Atk_ActionListener
{

    /**
     * This method handles the storage of the action in the database.
     *
     * @param String $action The action being performed
     * @param array $record The record on which the action is performed
     */
    function actionPerformed($action, $record)
    {
        $user = &Atk_SecurityManager::atkGetUser();
        $userid = $user[Atk_Config::getGlobal("auth_userpk")];
        if ($userid == "")
            $userid = 0; // probably administrator
        $node = $this->m_node->atkNodeType();
        $db = &$this->m_node->getDb();
        $primarykey = $db->escapeSQL($this->m_node->primaryKey($record));

        $db->query("INSERT INTO atkeventlog (id, userid, stamp, node, action, primarykey)
                    VALUES(" . $db->nextid("atkeventlog") . ", $userid, " . $db->func_now() . ", '$node', '$action', '$primarykey')");
        $db->commit();
    }

}

