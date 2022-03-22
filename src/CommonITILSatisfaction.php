<?php

/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2022 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

abstract class CommonITILSatisfaction extends CommonDBTM
{

    public $dohistory         = true;
    public $history_blacklist = ['date_answered'];

    /**
     * Survey is done internally
     */
    public const TYPE_INTERNAL = 1;

    /**
     * Survey is done externally
     */
    public const TYPE_EXTERNAL = 2;

    public static function getTypeName($nb = 0)
    {
        return __('Satisfaction');
    }

    /**
     * Get the itemtype this satisfaction is for
     * @return string
     */
    public static function getItemtype(): string
    {
        // Return itemtype extracted from current class name (Remove 'Satisfaction' suffix)
        return preg_replace('/Satisfaction$/', '', static::class);
    }

    /**
     * for use showFormHeader
     **/
    public static function getIndexName()
    {
        return static::getItemtype()::getForeignKeyField();
    }

    public function getLogTypeID()
    {
        /** @var CommonITILObject $itemtype */
        $itemtype = static::getItemtype();
        return [$itemtype, $this->fields[$itemtype::getForeignKeyField()]];
    }

    public static function canUpdate()
    {
        /** @var CommonITILObject $itemtype */
        $itemtype = static::getItemtype();
        return (Session::haveRight($itemtype::$rightname, READ));
    }

    /**
     * Is the current user have right to update the current satisfaction
     *
     * @return boolean
     **/
    public function canUpdateItem()
    {
        /** @var CommonITILObject $itemtype */
        $itemtype = static::getItemtype();
        $item = new $itemtype();
        if (!$item->getFromDB($this->fields[$itemtype::getForeignKeyField()])) {
            return false;
        }

        // you can't change if your answer > 12h
        if (
            !is_null($this->fields['date_answered'])
            && ((time() - strtotime($this->fields['date_answered'])) > (12 * HOUR_TIMESTAMP))
        ) {
            return false;
        }

        if (
            $item->isUser(CommonITILActor::REQUESTER, Session::getLoginUserID())
            || ($item->fields["users_id_recipient"] === Session::getLoginUserID() && Session::haveRight($itemtype::$rightname, Ticket::SURVEY))
            || (isset($_SESSION["glpigroups"])
                && $item->haveAGroup(CommonITILActor::REQUESTER, $_SESSION["glpigroups"]))
        ) {
            return true;
        }
        return false;
    }

    /**
     * form for satisfaction
     *
     * @param CommonITILObject $item The item this satisfaction is for
     **/
    public function showSatisactionForm($item)
    {
        $tid                 = $item->fields['id'];
        $options             = [];
        $options['colspan']  = 1;

        // for external inquest => link
        if ((int) $this->fields["type"] === self::TYPE_EXTERNAL) {
            $url = Entity::generateLinkSatisfaction($item);
            echo "<div class='center spaced'>" .
                "<a href='$url'>" . __('External survey') . "</a><br>($url)</div>";
        } else { // for internal inquest => form
            $this->showFormHeader($options);

            // Set default satisfaction to 3 if not set
            if (is_null($this->fields["satisfaction"])) {
                $this->fields["satisfaction"] = 3;
            }
            echo "<tr class='tab_bg_2'>";
            echo "<td>" . sprintf(__('Satisfaction with the resolution of the %s'), strtolower($item::getTypeName(1))) . "</td>";
            echo "<td>";
            echo "<input type='hidden' name='{$item::getForeignKeyField()}' value='$tid'>";

            echo "<select id='satisfaction_data' name='satisfaction'>";

            for ($i = 0; $i <= 5; $i++) {
                echo "<option value='$i' " . (($i == $this->fields["satisfaction"]) ? 'selected' : '') .
                    ">$i</option>";
            }
            echo "</select>";
            echo "<div class='rateit' id='stars'></div>";
            echo  "<script type='text/javascript'>";
            echo "$(function() {";
            echo "$('#stars').rateit({value: " . $this->fields["satisfaction"] . ",
                                   min : 0,
                                   max : 5,
                                   step: 1,
                                   backingfld: '#satisfaction_data',
                                   ispreset: true,
                                   resetable: false});";
            echo "});</script>";

            echo "</td></tr>";

            echo "<tr class='tab_bg_2'>";
            echo "<td rowspan='1'>" . __('Comments') . "</td>";
            echo "<td rowspan='1' class='middle'>";
            echo "<textarea class='form-control' rows='7' name='comment'>" . $this->fields["comment"] . "</textarea>";
            echo "</td></tr>";

            if ($this->fields["date_answered"] > 0) {
                echo "<tr class='tab_bg_2'>";
                echo "<td>" . __('Response date to the satisfaction survey') . "</td><td>";
                echo Html::convDateTime($this->fields["date_answered"]) . "</td></tr>\n";
            }

            $options['candel'] = false;
            $this->showFormButtons($options);
        }
    }

    public function prepareInputForUpdate($input)
    {
        if ($input['satisfaction'] >= 0) {
            $input["date_answered"] = $_SESSION["glpi_currenttime"];
        }

        return $input;
    }

    public function post_addItem()
    {
        global $CFG_GLPI;

        if (!isset($this->input['_disablenotif']) && $CFG_GLPI["use_notifications"]) {
            /** @var CommonDBTM $itemtype */
            $itemtype = static::getItemtype();
            $item = new $itemtype();
            if ($item->getFromDB($this->fields[$itemtype::getForeignKeyField()])) {
                NotificationEvent::raiseEvent("satisfaction", $item);
            }
        }
    }

    public function post_UpdateItem($history = 1)
    {
        global $CFG_GLPI;

        if (!isset($this->input['_disablenotif']) && $CFG_GLPI["use_notifications"]) {
            /** @var CommonDBTM $itemtype */
            $itemtype = static::getItemtype();
            $item = new $itemtype();
            if ($item->getFromDB($this->fields[$itemtype::getForeignKeyField()])) {
                NotificationEvent::raiseEvent("replysatisfaction", $item);
            }
        }
    }

    /**
     * display satisfaction value
     *
     * @param int $value Between 0 and 5
     **/
    public static function displaySatisfaction($value)
    {

        if ($value < 0) {
            $value = 0;
        }
        if ($value > 5) {
            $value = 5;
        }

        $out = "<div class='rateit' data-rateit-value='$value' data-rateit-ispreset='true'
               data-rateit-readonly='true'></div>";

        return $out;
    }


    /**
     * Get name of inquest type
     *
     * @param int $value Survey type ID
     **/
    public static function getTypeInquestName($value)
    {

        switch ($value) {
            case self::TYPE_INTERNAL:
                return __('Internal survey');

            case self::TYPE_EXTERNAL:
                return __('External survey');

            default:
                // Get value if not defined
                return $value;
        }
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {

        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'type':
                return self::getTypeInquestName($values[$field]);
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {

        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;

        switch ($field) {
            case 'type':
                $options['value'] = $values[$field];
                $typeinquest = [
                    self::TYPE_INTERNAL => __('Internal survey'),
                    self::TYPE_EXTERNAL => __('External survey')
                ];
                return Dropdown::showFromArray($name, $typeinquest, $options);
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    public static function getFormURLWithID($id = 0, $full = true)
    {

        $satisfaction = new static();
        if (!$satisfaction->getFromDB($id)) {
            return '';
        }

        /** @var CommonDBTM $itemtype */
        $itemtype = static::getItemtype();
        return $itemtype::getFormURLWithID($satisfaction->fields[$itemtype::getForeignKeyField()]) . '&forcetab=' . $itemtype::getType() . '$3';
    }
}
