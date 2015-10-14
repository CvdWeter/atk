<?php
/**
 * This file is part of the ATK distribution on GitHub.
 * Detailed copyright and licensing information can be found
 * in the doc/COPYRIGHT and doc/LICENSE files which should be
 * included in the distribution.
 *
 * @package atk
 * @subpackage attributes
 *
 * @copyright (c)2006 Ibuildings.nl BV
 * @license http://www.achievo.org/atk/licensing ATK Open Source License
 *
 * @version $Revision: 4173 $
 * $Id$
 */
/**
 * The atkRowCounter can be added to a node to have a column in listviews
 * that sequentially numbers records.
 *
 * The attribute evolved from a discussion at http://achievo.org/forum/viewtopic.php?t=478
 * and was added to ATK based on suggestion and documentation by Jorge Garifuna.
 *
 * @author Przemek Piotrowski <przemek.piotrowski@nic.com.pl>
 * @author Ivo Jansch <ivo@achievo.org>
 *
 * @package atk
 * @subpackage attributes
 *
 */

class Atk_RowCounter extends Atk_DummyAttribute
{

    /**
     * Constructor
     * @param String $name Name of the attribute
     * @param int $flags Flags for this attribute
     */
    function atkRowCounter($name, $flags = 0)
    {
        $this->atkDummyAttribute($name, '', $flags | AF_HIDE_VIEW | AF_HIDE_EDIT | AF_HIDE_ADD);
    }

    /**
     * Returns a number corresponding to the row count per record.
     * @return int Counter, starting at 1
     */
    function display()
    {
        static $s_counter = 0;
        $node = &$this->m_ownerInstance;
        return $node->m_postvars["atkstartat"] + ( ++$s_counter);
    }

}

