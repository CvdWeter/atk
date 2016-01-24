<?php namespace Sintattica\Atk\Security\Auth;

use Sintattica\Atk\Security\SecurityManager;
use Sintattica\Atk\Core\Config;
use Sintattica\Atk\Core\Tools;

/**
 * Driver for authentication using pop3.
 *
 * Does not support authorization.
 *
 * @author Ivo Jansch <ivo@achievo.org>
 * @package atk
 * @subpackage security
 *
 */
class Pop3Auth extends AuthInterface
{

    /**
     * Validate user.
     * @param string $user the username
     * @param string $passwd the password
     * @return int SecurityManager::AUTH_SUCCESS - Authentication succesful
     *             SecurityManager::AUTH_MISMATCH - Authentication failed, wrong
     *                             user/password combination
     *             SecurityManager::AUTH_LOCKED - Account is locked, can not login
     *                           with current username.
     *             SecurityManager::AUTH_ERROR - Authentication failed due to some
     *                          error which cannot be solved by
     *                          just trying again. If you return
     *                          this value, you *must* also
     *                          fill the m_fatalError variable.
     */
    function validateUser($user, $passwd)
    {
        if ($user == "") {
            return SecurityManager::AUTH_UNVERIFIED;
        } // can't verify if we have no userid

        global $g_pop3_responses;

        /* if it's a virtual mail server add @<domain> to the username */
        if (Config::getGlobal("auth_mail_virtual") == true) {
            $user = $user . "@" . Config::getGlobal("auth_mail_suffix");
        }

        $server = Config::getGlobal("auth_mail_server");

        // Special feature
        if ($server == "[db]") {
            // if server is set to [db], that means we have a different server per
            // user. We lookup in the database what server we need to call.
            $db = Tools::atkGetDb();
            $res = $db->getrows("SELECT auth_server
                               FROM " . Config::getGlobal("auth_usertable") . "
                              WHERE " . Config::getGlobal("auth_userfield") . "='" . $user . "'");
            if (count($res) == 0) {
                // User not found.
                return SecurityManager::AUTH_MISMATCH;
            }
            $server = $res[0]["auth_server"];
        }

        $secMgr = SecurityManager::getInstance();

        if ($server == "") {
            $secMgr->log(1, "pop3auth error: No server specified");
            Tools::atkdebug("pop3auth error: No server specified");
            $this->m_fatalError = Tools::atktext("auth_no_server");
            return SecurityManager::AUTH_ERROR;
        }

        /* connect */
        $port = Config::getGlobal("auth_mail_port");
        $link_id = fsockopen($server, $port, $errno, $errstr, 30);
        if (!$link_id) {
            $secMgr->log(1, "pop3auth serverconnect error $server: $errstr");
            Tools::atkdebug("Error connecting to server $server: $errstr");
            $this->m_fatalError = Tools::atktext("auth_unable_to_connect");
            return SecurityManager::AUTH_ERROR;
        }

        /* authenticate */
        fgets($link_id, 1000);
        fputs($link_id, "USER " . $user . "\r\n");
        fgets($link_id, 1000);
        fputs($link_id, "PASS " . $passwd . "\r\n");
        $auth = fgets($link_id, 1000);
        fputs($link_id, "QUIT\r\n");
        fclose($link_id);

        $secMgr->log(1, "pop3auth response for user $user: " . trim($auth));

        // search application specified pop3 responses..
        if (is_array($g_pop3_responses)) {
            foreach ($g_pop3_responses as $substring => $message) {
                if (stristr($auth, $substring) != false) {
                    $this->m_fatalError = $message;
                    return SecurityManager::AUTH_ERROR;
                }
            }
        }

        /* login ok? */
        if (!stristr($auth, "ERR")) {
            return SecurityManager::AUTH_SUCCESS;
        } else {
            return SecurityManager::AUTH_MISMATCH;
        }
    }

    /**
     * Pop3 can't handle md5 passwords since they must be sent to the server
     * as plain text.
     * @return boolean False
     */
    function canMd5()
    {
        return false;
    }

}

