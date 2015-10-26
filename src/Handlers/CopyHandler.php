<?php namespace Sintattica\Atk\Handlers;

use Sintattica\Atk\Core\Tools;


/**
 * Handler for the 'tcopy' action of a node. It copies the selected
 * record, and then redirects back to the calling page.
 *
 * @author Ivo Jansch <ivo@achievo.org>
 * @package atk
 * @subpackage handlers
 *
 */
class CopyHandler extends ActionHandler
{

    /**
     * The action handler.
     *
     */
    function action_copy()
    {
        $this->invoke("nodeCopy");
    }

    /**
     * Copies a record, based on parameters passed in the url.
     */
    function nodeCopy()
    {
        Tools::atkdebug("CopyHandler::nodeCopy()");
        $recordset = $this->m_node->selectDb($this->m_postvars['atkselector'], "", "", "", "", "copy");
        $db = $this->m_node->getDb();
        if (count($recordset) > 0) {
            // allowed to copy record?
            if (!$this->allowed($recordset[0])) {
                $this->renderAccessDeniedPage();
                return;
            }

            if (!$this->m_node->copyDb($recordset[0])) {
                Tools::atkdebug("node::action_copy() -> Error");
                $db->rollback();
                $location = $this->m_node->feedbackUrl("save", self::ACTION_FAILED, $recordset[0], $db->getErrorMsg());
                Tools::atkdebug("node::action_copy() -> Redirect");
                $this->m_node->redirect($location);
            } else {
                $db->commit();
                $this->notify("copy", $recordset[0]);
                $this->clearCache();
            }
        }
        $this->m_node->redirect();
    }

}

