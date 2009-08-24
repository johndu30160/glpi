<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2009 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 --------------------------------------------------------------------------
 */

// ----------------------------------------------------------------------
// Original Author of file:
// Purpose of file:
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 *  Computer class
 */
class Computer extends CommonDBTM {

   ///Device container - format $device = array(ID,"device type","ID in device table","specificity value")
   var $devices	= array();

   /**
   * Constructor
   **/
   function __construct () {
      $this->table="glpi_computers";
      $this->type=COMPUTER_TYPE;
      $this->dohistory=true;
      $this->entity_assign=true;
   }

   function defineTabs($ID,$withtemplate) {
      global $LANG,$CFG_GLPI;

      if ($ID>0) {
         $ong[1]=$LANG['title'][30];
         $ong[20]=$LANG['computers'][8];
         if (haveRight("software","r")) {
            $ong[2]=$LANG['Menu'][4];
         }
         if (haveRight("networking","r") || haveRight("printer","r") || haveRight("monitor","r")
             || haveRight("peripheral","r") || haveRight("phone","r")) {
            $ong[3]=$LANG['title'][27];
         }
         if (haveRight("contract","r") || haveRight("infocom","r")) {
            $ong[4]=$LANG['Menu'][26];
         }
         if (haveRight("document","r")) {
            $ong[5]=$LANG['Menu'][27];
         }

         if (empty($withtemplate)) {
            if ($CFG_GLPI["use_ocs_mode"]) {
               $ong[14]=$LANG['title'][43];
            }
            if (haveRight("show_all_ticket","1")) {
               $ong[6]=$LANG['title'][28];
            }
            if (haveRight("link","r")) {
               $ong[7]=$LANG['title'][34];
            }
            if (haveRight("notes","r")) {
               $ong[10]=$LANG['title'][37];
            }
            if (haveRight("reservation_central","r")) {
               $ong[11]=$LANG['Menu'][17];
            }

            $ong[12]=$LANG['title'][38];

            if ($CFG_GLPI["use_ocs_mode"]
                && (haveRight("sync_ocsng","w") ||haveRight("computer","w"))) {
               $ong[13]=$LANG['Menu'][33];
            }
         }
      } else { // New item
         $ong[1]=$LANG['title'][26];
      }
      return $ong;
   }

   /**
   * Retrieve an item from the database with device associated
   *
   *@param $ID ID of the item to get
   *@return true if succeed else false
   **/
   function getFromDBwithDevices ($ID) {
      global $DB;

      if ($this->getFromDB($ID)) {
         $query = "SELECT count(*) AS NB, `id`, `devicetype`, `devices_id`, `specificity`
                   FROM `glpi_computers_devices`
                   WHERE `computers_id` = '$ID'
                   GROUP BY `devicetype`, `devices_id`, `specificity`";
         if ($result = $DB->query($query)) {
            if ($DB->numrows($result)>0) {
               $i = 0;
               while($data = $DB->fetch_array($result)) {
                  $this->devices[$i] = array("compDevID"=>$data["id"],
                                             "devType"=>$data["devicetype"],
                                             "devID"=>$data["devices_id"],
                                             "specificity"=>$data["specificity"],
                                             "quantity"=>$data["NB"]);
                  $i++;
               }
            }
            return true;
         }
      }
      return false;
   }

   function post_updateItem($input,$updates,$history=1) {
      global $DB,$LANG,$CFG_GLPI;

      // Manage changes for OCS if more than 1 element (date_mod)
      // Need dohistory==1 if dohistory==2 no locking fields
      if ($this->fields["is_ocs_import"] && $history==1 && count($updates)>1) {
         mergeOcsArray($this->fields["id"],$updates,"computer_update");
      }

      if (isset($input["_auto_update_ocs"])){
         $query="UPDATE
                 `glpi_ocslinks`
                 SET `use_auto_update`='".$input["_auto_update_ocs"]."'
                 WHERE `computers_id`='".$input["id"]."'";
         $DB->query($query);
      }

      for ($i=0; $i < count($updates); $i++) {
         // Update contact of attached items
         if (($updates[$i]=="contact"  || $updates[$i]=="contact_num")
              && $CFG_GLPI["is_contact_autoupdate"]) {
            $items=array(PRINTER_TYPE,
                         MONITOR_TYPE,
                         PERIPHERAL_TYPE,
                         PHONE_TYPE);
            $ci=new CommonItem();
            $update_done=false;
            $updates3[0]="contact";
            $updates3[1]="contact_num";

            foreach ($items as $t) {
               $query = "SELECT *
                         FROM `glpi_computers_items`
                         WHERE `computers_id`='".$this->fields["id"]."'
                               AND `itemtype`='".$t."'";
               if ($result=$DB->query($query)) {
                  $resultnum = $DB->numrows($result);
                  if ($resultnum>0) {
                     for ($j=0; $j < $resultnum; $j++) {
                        $tID = $DB->result($result, $j, "items_id");
                        $ci->getFromDB($t,$tID);
                        if (!$ci->getField('is_global')) {
                           if ($ci->getField('contact')!=$this->fields['contact']
                               || $ci->getField('contact_num')!=$this->fields['contact_num']){
                              $tmp["id"]=$ci->getField('id');
                              $tmp['contact']=$this->fields['contact'];
                              $tmp['contact_num']=$this->fields['contact_num'];
                              $ci->obj->update($tmp);
                              $update_done=true;
                           }
                        }
                     }
                  }
               }
            }

            if ($update_done) {
               addMessageAfterRedirect($LANG['computers'][49],true);
            }
         }

         // Update users and groups of attached items
         if (($updates[$i]=="users_id"  && $this->fields["users_id"]!=0
                                        && $CFG_GLPI["is_user_autoupdate"])
              ||($updates[$i]=="groups_id" && $this->fields["groups_id"]!=0
                                           && $CFG_GLPI["is_group_autoupdate"])) {
            $items=array(PRINTER_TYPE,
                         MONITOR_TYPE,
                         PERIPHERAL_TYPE,
                         PHONE_TYPE);
            $ci=new CommonItem();
            $update_done=false;
            $updates4[0]="users_id";
            $updates4[1]="groups_id";

            foreach ($items as $t) {
               $query = "SELECT *
                         FROM `glpi_computers_items`
                         WHERE `computers_id`='".$this->fields["id"]."'
                               AND `itemtype`='".$t."'";

               if ($result=$DB->query($query)) {
                  $resultnum = $DB->numrows($result);

                  if ($resultnum>0) {
                     for ($j=0; $j < $resultnum; $j++) {
                        $tID = $DB->result($result, $j, "items_id");
                        $ci->getFromDB($t,$tID);
                        if (!$ci->getField('is_global')) {
                           if ($ci->getField('users_id')!=$this->fields["users_id"]
                               ||$ci->getField('groups_id')!=$this->fields["groups_id"]) {
                              $tmp["id"]=$ci->getField('id');
                              if ($CFG_GLPI["is_user_autoupdate"]) {
                                 $tmp["users_id"]=$this->fields["users_id"];
                              }
                              if ($CFG_GLPI["is_group_autoupdate"]) {
                                 $tmp["groups_id"]=$this->fields["groups_id"];
                              }
                              $ci->obj->update($tmp);
                              $update_done=true;
                           }
                        }
                     }
                  }
               }
            }
            if ($update_done) {
               addMessageAfterRedirect($LANG['computers'][50],true);
            }
         }

         // Update state of attached items
         if ($updates[$i]=="states_id" && $CFG_GLPI["state_autoupdate_mode"]<0) {
            $items=array(PRINTER_TYPE,
                         MONITOR_TYPE,
                         PERIPHERAL_TYPE,
                         PHONE_TYPE);
            $ci=new CommonItem();
            $update_done=false;

            foreach ($items as $t) {
               $query = "SELECT *
                         FROM `glpi_computers_items`
                         WHERE `computers_id`='".$this->fields["id"]."'
                               AND `itemtype`='".$t."'";

               if ($result=$DB->query($query)) {
                  $resultnum = $DB->numrows($result);

                  if ($resultnum>0) {
                     for ($j=0; $j < $resultnum; $j++) {
                        $tID = $DB->result($result, $j, "items_id");
                        $ci->getFromDB($t,$tID);
                        if (!$ci->getField('is_global')){
                           if ($ci->getField('states_id')!=$this->fields["states_id"]){
                              $tmp["id"]=$ci->getField('id');
                              $tmp["states_id"]=$this->fields["states_id"];
                              $ci->obj->update($tmp);
                              $update_done=true;
                           }
                        }
                     }
                  }
               }
            }
            if ($update_done) {
               addMessageAfterRedirect($LANG['computers'][56],true);
            }
         }

         // Update loction of attached items
         if ($updates[$i]=="locations_id" && $this->fields["locations_id"]!=0
                                          && $CFG_GLPI["is_location_autoupdate"]) {
            $items=array(PRINTER_TYPE,
                         MONITOR_TYPE,
                         PERIPHERAL_TYPE,
                         PHONE_TYPE);
            $ci=new CommonItem();
            $update_done=false;
            $updates2[0]="locations_id";

            foreach ($items as $t) {
               $query = "SELECT *
                         FROM `glpi_computers_items`
                         WHERE `computers_id`='".$this->fields["id"]."'
                               AND `itemtype`='".$t."'";

               if ($result=$DB->query($query)) {
                  $resultnum = $DB->numrows($result);

                  if ($resultnum>0) {
                     for ($j=0; $j < $resultnum; $j++) {
                        $tID = $DB->result($result, $j, "items_id");
                        $ci->getFromDB($t,$tID);
                        if (!$ci->getField('is_global')) {
                           if ($ci->getField('locations_id')!=$this->fields["locations_id"]) {
                              $tmp["id"]=$ci->getField('id');
                              $tmp["locations_id"]=$this->fields["locations_id"];
                              $ci->obj->update($tmp);
                              $update_done=true;
                           }
                        }
                     }
                  }
               }
            }
            if ($update_done) {
               addMessageAfterRedirect($LANG['computers'][48],true);
            }
         }
      }
   }

   function prepareInputForAdd($input) {

      if (isset($input["id"]) && $input["id"]>0) {
         $input["_oldID"]=$input["id"];
      }
      unset($input['id']);
      unset($input['withtemplate']);

      return $input;
   }

   function post_addItem($newID,$input) {
      global $DB;

      // Manage add from template
      if (isset($input["_oldID"])) {
         // ADD Devices
         $this->getFromDBwithDevices($input["_oldID"]);
         foreach($this->devices as $key => $val) {
            for ($i=0;$i<$val["quantity"];$i++) {
               compdevice_add($newID,$val["devType"],$val["devID"],$val["specificity"],0);
            }
         }

         // ADD Infocoms
         $ic= new Infocom();
         if ($ic->getFromDBforDevice(COMPUTER_TYPE,$input["_oldID"])) {
            $ic->fields["items_id"]=$newID;
            unset ($ic->fields["id"]);
            if (isset($ic->fields["immo_number"])) {
               $ic->fields["immo_number"] = autoName($ic->fields["immo_number"],
                                            "immo_number", 1, INFOCOM_TYPE, $input['entities_id']);
            }
            if (empty($ic->fields['use_date'])) {
               unset($ic->fields['use_date']);
            }
            if (empty($ic->fields['buy_date'])) {
               unset($ic->fields['buy_date']);
            }
            $ic->addToDB();
         }

         // ADD volumes
         $query="SELECT `id`
                 FROM `glpi_computersdisks`
                 WHERE `computers_id`='".$input["_oldID"]."'";
         $result=$DB->query($query);
         if ($DB->numrows($result)>0) {
            while ($data=$DB->fetch_array($result)) {
               $disk=new ComputerDisk();
               $disk->getfromDB($data['id']);
               unset($disk->fields["id"]);
               $disk->fields["computers_id"]=$newID;
               $disk->addToDB();
            }
         }

         // ADD software
         $query="SELECT `softwaresversions_id`
                 FROM `glpi_computers_softwaresversions`
                 WHERE `computers_id`='".$input["_oldID"]."'";
         $result=$DB->query($query);
         if ($DB->numrows($result)>0) {
            while ($data=$DB->fetch_array($result)) {
               installSoftwareVersion($newID,$data['softwaresversions_id']);
            }
         }

         // ADD Contract
         $query="SELECT `contracts_id`
                 FROM `glpi_contracts_items`
                 WHERE `items_id`='".$input["_oldID"]."'
                       AND `itemtype`='".COMPUTER_TYPE."';";
         $result=$DB->query($query);
         if ($DB->numrows($result)>0) {
            while ($data=$DB->fetch_array($result)) {
               addDeviceContract($data["contracts_id"],COMPUTER_TYPE,$newID);
            }
         }

         // ADD Documents
         $query="SELECT `documents_id`
                 FROM `glpi_documents_items`
                 WHERE `items_id`='".$input["_oldID"]."'
                       AND `itemtype`='".COMPUTER_TYPE."';";
         $result=$DB->query($query);
         if ($DB->numrows($result)>0) {
            while ($data=$DB->fetch_array($result)) {
               addDeviceDocument($data["documents_id"],COMPUTER_TYPE,$newID);
            }
         }

         // ADD Ports
         $query="SELECT `id`
                 FROM `glpi_networkports`
                 WHERE `items_id`='".$input["_oldID"]."'
                       AND `itemtype`='".COMPUTER_TYPE."';";
         $result=$DB->query($query);
         if ($DB->numrows($result)>0) {
            while ($data=$DB->fetch_array($result)) {
               $np= new Netport();
               $np->getFromDB($data["id"]);
               unset($np->fields["id"]);
               unset($np->fields["ip"]);
               unset($np->fields["mac"]);
               unset($np->fields["netpoints_id"]);
               $np->fields["items_id"]=$newID;
               $np->addToDB();
            }
         }

         // Add connected devices
         $query="SELECT *
                 FROM `glpi_computers_items`
                 WHERE `computers_id`='".$input["_oldID"]."';";

         $result=$DB->query($query);
         if ($DB->numrows($result)>0) {
            while ($data=$DB->fetch_array($result)) {
               Connect($data["items_id"],$newID,$data["itemtype"]);
            }
         }
      }
   }

   function cleanDBonPurge($ID) {
      global $DB,$CFG_GLPI;

      $job=new Job;

      $query = "SELECT *
                FROM `glpi_tickets`
                WHERE (`items_id` = '$ID'
                      AND `itemtype`='".COMPUTER_TYPE."')";
      $result = $DB->query($query);

      if ($DB->numrows($result)) {
         while ($data=$DB->fetch_array($result)) {
            if ($CFG_GLPI["keep_tickets_on_delete"]==1) {
               $query = "UPDATE
                         `glpi_tickets`
                         SET `items_id` = '0', `itemtype` = '0'
                         WHERE `id`='".$data["id"]."';";
               $DB->query($query);
            } else {
                $job->delete(array("id"=>$data["id"]));
            }
         }
      }

      $query = "DELETE
                FROM `glpi_computers_softwaresversions`
                WHERE `computers_id` = '$ID'";
      $result = $DB->query($query);

      $query = "DELETE
                FROM `glpi_contracts_items`
                WHERE (`items_id` = '$ID'
                       AND `itemtype`='".COMPUTER_TYPE."')";
      $result = $DB->query($query);

      $query = "DELETE
                FROM `glpi_infocoms`
                WHERE (`items_id` = '$ID'
                       AND `itemtype`='".COMPUTER_TYPE."')";
      $result = $DB->query($query);

      $query = "SELECT `id`
                FROM `glpi_networkports`
                WHERE (`items_id` = '$ID'
                       AND `itemtype` = '".COMPUTER_TYPE."')";
      $result = $DB->query($query);
      while ($data = $DB->fetch_array($result)) {
         $q = "DELETE
               FROM `glpi_networkports_networkports`
               WHERE (`networkports_id_1` = '".$data["id"]."'
                      OR `networkports_id_2` = '".$data["id"]."')";
         $result2 = $DB->query($q);
      }

      $query = "DELETE
                FROM `glpi_networkports`
                WHERE (`items_id` = '$ID'
                       AND `itemtype` = '".COMPUTER_TYPE."')";
      $result = $DB->query($query);

      $query="SELECT *
              FROM `glpi_computers_items`
              WHERE `computers_id`='$ID'";
      if ($result = $DB->query($query)) {
         if ($DB->numrows($result)>0) {
            while ($data = $DB->fetch_array($result)) {
               // Disconnect without auto actions
               Disconnect($data["id"],1,false);
            }
         }
         }

      $query = "DELETE
                FROM `glpi_registrykeys`
                WHERE `computers_id` = '$ID'";
      $result = $DB->query($query);

      $query="SELECT *
              FROM `glpi_reservationsitems`
              WHERE (`itemtype`='".COMPUTER_TYPE."'
                     AND `items_id`='$ID')";
      if ($result = $DB->query($query)) {
         if ($DB->numrows($result)>0) {
            $rr=new ReservationItem();
            $rr->delete(array("id"=>$DB->result($result,0,"id")));
         }
      }

      $query = "DELETE
                FROM `glpi_computers_devices`
                WHERE `computers_id` = '$ID'";
      $result = $DB->query($query);

      $query = "DELETE
                FROM `glpi_ocslinks`
                WHERE `computers_id` = '$ID'";
      $result = $DB->query($query);

      $query = "DELETE
                FROM `glpi_computersdisks`
                WHERE `computers_id` = '$ID'";
      $result = $DB->query($query);
   }

   /**
   * Print the computer form
   *
   * Print general computer form
   *
   *@param $target form target
   *@param $ID Integer : Id of the computer or the template to print
   *@param $withtemplate template or basic computer
   *
   *@return Nothing (display)
   *
   **/
   function showForm($target,$ID,$withtemplate='') {
      global $LANG,$CFG_GLPI,$DB;

      if (!haveRight("computer","r")) {
        return false;
      }

      if ($ID > 0) {
         $this->check($ID,'r');
      } else {
         // Create item
         $this->check(-1,'w');
         $this->getEmpty();
      }

      if(!empty($withtemplate) && $withtemplate == 2) {
         $template = "newcomp";
         $datestring = $LANG['computers'][14]." : ";
         $date = convDateTime($_SESSION["glpi_currenttime"]);
      } elseif(!empty($withtemplate) && $withtemplate == 1) {
         $template = "newtemplate";
         $datestring = $LANG['computers'][14]." : ";
         $date = convDateTime($_SESSION["glpi_currenttime"]);
      } else {
         $datestring = $LANG['common'][26].": ";
         $date = convDateTime($this->fields["date_mod"]);
         $template = false;
      }

      $this->showTabs($ID, $withtemplate,$_SESSION['glpi_tab']);
      $this->showFormHeader($target, $ID, $withtemplate, 2);

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['common'][16].($template?"*":"")."&nbsp;:</td>";
      echo "<td>";
      $objectName = autoName($this->fields["name"], "name", ($template === "newcomp"),COMPUTER_TYPE,
                    $this->fields["entities_id"]);
      autocompletionTextField("name","glpi_computers","name",$objectName,40,
                               $this->fields["entities_id"]);
      echo "</td><td>".$LANG['common'][18]." :</td>";
      echo "<td>";
      autocompletionTextField("contact","glpi_computers","contact",
                              $this->fields["contact"],40,$this->fields["entities_id"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".$LANG['common'][17]."&nbsp;: </td>";
      echo "<td >";
      dropdownValue("glpi_computerstypes", "computerstypes_id", $this->fields["computerstypes_id"]);
      echo "</td><td>".$LANG['common'][21]."&nbsp;: </td>";
      echo "<td >";
      autocompletionTextField("contact_num","glpi_computers","contact_num",
                              $this->fields["contact_num"],40,$this->fields["entities_id"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".$LANG['common'][22]."&nbsp;: </td>";
      echo "<td >";
      dropdownValue("glpi_computersmodels", "computersmodels_id",$this->fields["computersmodels_id"]);
      echo "</td><td >".$LANG['common'][34]."&nbsp;: </td>";
      echo "<td >";
      dropdownAllUsers("users_id", $this->fields["users_id"],1, $this->fields["entities_id"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td >".$LANG['common'][15]."&nbsp;: </td>";
      echo "<td >";
      dropdownValue("glpi_locations", "locations_id", $this->fields["locations_id"],1,
                     $this->fields["entities_id"]);
      echo "</td><td>".$LANG['common'][35]."&nbsp;:</td>";
      echo "<td >";
      dropdownValue("glpi_groups", "groups_id", $this->fields["groups_id"],1,
                     $this->fields["entities_id"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['common'][5]."&nbsp;: </td><td>";
      dropdownValue("glpi_manufacturers","manufacturers_id", $this->fields["manufacturers_id"]);
      echo "</td><td >".$LANG['common'][10]."&nbsp;: </td>";
      echo "<td >";
      dropdownUsersID("users_id_tech",$this->fields["users_id_tech"],"interface",1,
                       $this->fields["entities_id"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['computers'][9]."&nbsp;:</td><td>";
      dropdownValue("glpi_operatingsystems", "operatingsystems_id",
                     $this->fields["operatingsystems_id"]);
      echo "</td><td>".$LANG['setup'][88]." :</td>";
      echo "<td >";
      dropdownValue("glpi_networks", "networks_id", $this->fields["networks_id"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['computers'][52]."&nbsp;:</td>";
      echo "<td >";
      dropdownValue("glpi_operatingsystemsversions", "operatingsystemsversions_id",
                     $this->fields["operatingsystemsversions_id"]);
      echo "</td><td>".$LANG['setup'][89]."&nbsp;:</td>";
      echo "<td >";
      dropdownValue("glpi_domains", "domains_id", $this->fields["domains_id"]);
      echo "</td></tr>";


      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['computers'][53]."&nbsp;:</td>";
      echo "<td >";
      dropdownValue("glpi_operatingsystemsservicepacks", "operatingsystemsservicepacks_id",
                     $this->fields["operatingsystemsservicepacks_id"]);
      echo "</td><td>".$LANG['common'][19]."&nbsp;:</td>";
      echo "<td >";
      autocompletionTextField("serial","glpi_computers","serial",$this->fields["serial"],40,
                               $this->fields["entities_id"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['computers'][10]."&nbsp;:</td>";
      echo "<td >";
      autocompletionTextField("os_license_number","glpi_computers","os_license_number",
                              $this->fields["os_license_number"],40, $this->fields["entities_id"]);
      echo "</td><td>".$LANG['common'][20].($template?"*":"")."&nbsp;:</td>";
      echo "<td >";
      $objectName = autoName($this->fields["otherserial"], "otherserial", ($template === "newcomp"),
                             COMPUTER_TYPE,$this->fields["entities_id"]);
      autocompletionTextField("otherserial","glpi_computers","otherserial",$objectName,40,
                               $this->fields["entities_id"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['computers'][11]."&nbsp;:</td>";
      echo "<td >";
      autocompletionTextField("os_licenseid","glpi_computers","os_licenseid",
                               $this->fields["os_licenseid"],40,$this->fields["entities_id"]);
      echo "</td><td>".$LANG['state'][0]."&nbsp;:</td>";
      echo "<td >";
      dropdownValue("glpi_states", "states_id",$this->fields["states_id"]);
      echo "</td></tr>";

      // Get OCS Datas :
      $dataocs=array();
      if (!empty($ID) && $this->fields["is_ocs_import"] && haveRight("view_ocsng","r")) {
         $query="SELECT *
                 FROM `glpi_ocslinks`
                 WHERE `computers_id`='$ID'";

         $result=$DB->query($query);
         if ($DB->numrows($result)==1) {
            $dataocs=$DB->fetch_array($result);
         }
      }

      echo "<tr class='tab_bg_1'>";
      if (!empty($ID) && $this->fields["is_ocs_import"] && haveRight("view_ocsng","r")
          && haveRight("sync_ocsng","w") && count($dataocs)) {
         echo "<td >".$LANG['ocsng'][6]." ".$LANG['Menu'][33]."&nbsp;:</td>";
         echo "<td >";
         dropdownYesNo("_auto_update_ocs",$dataocs["use_auto_update"]);
         echo "</td>";
      } else {
         echo "<td colspan=2></td>";
      }
      echo "<td>".$LANG['computers'][51]."&nbsp;:</td>";
      echo "<td >";
      dropdownValue("glpi_autoupdatesystems", "autoupdatesystems_id",
                     $this->fields["autoupdatesystems_id"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td colspan='2' class='center'>".$datestring.$date;
      if (!$template && !empty($this->fields['template_name'])) {
         echo "&nbsp;&nbsp;&nbsp;(".$LANG['common'][13]."&nbsp;: ".$this->fields['template_name'].")";
      }
      if (!empty($ID) && $this->fields["is_ocs_import"] && haveRight("view_ocsng","r")
          && count($dataocs)) {
         echo "<br>";
         echo $LANG['ocsng'][14]."&nbsp;: ".convDateTime($dataocs["last_ocs_update"]);
         echo "<br>";
         echo $LANG['ocsng'][13]."&nbsp;: ".convDateTime($dataocs["last_update"]);
         echo "<br>";
         if (haveRight("ocsng","r")) {
            echo $LANG['common'][52]." <a href='".$CFG_GLPI["root_doc"]."/front/ocsng.form.php?id="
                 .getOCSServerByMachineID($ID)."'>".getOCSServerNameByID($ID)."</a>";
            $query = "SELECT `ocs_agent_version`, `ocsid`
                      FROM `glpi_ocslinks`
                      WHERE `computers_id` = '$ID'";
            $result_agent_version = $DB->query($query);
            $data_version = $DB->fetch_array($result_agent_version);

            $ocs_config = getOcsConf(getOCSServerByMachineID($ID));

            //If have write right on OCS and ocsreports url is not empty in OCS config
            if (haveRight("ocsng","w") && $ocs_config["ocs_url"] != '') {
               echo ", ".getComputerLinkToOcsConsole (getOCSServerByMachineID($ID),
               $data_version["ocsid"],$LANG['ocsng'][57]);
            }

            if ($data_version["ocs_agent_version"] != NULL) {
               echo " , ".$LANG['ocsng'][49]."&nbsp;: ".$data_version["ocs_agent_version"];
            }
         } else {
            echo $LANG['common'][52]." ".getOCSServerNameByID($ID)."</td>";
         }
      }
      echo "</td>";
      echo "<td class='middle'>".$LANG['common'][25]."&nbsp;:</td>";
      echo "<td class='middle'><textarea  cols='50' rows='3' name='comment' >".
                                $this->fields["comment"]."</textarea></td></tr>";

      $this->showFormButtons($ID,$withtemplate,2);

      echo "<div id='tabcontent'></div>";
      echo "<script type='text/javascript'>loadDefaultTab();</script>";

      return true;
   }

   /*
    * Return the SQL command to retrieve linked object
    *
    * @return a SQL command which return a set of (itemtype, items_id)
    */
   function getSelectLinkedItem () {
      return "SELECT `itemtype`, `items_id`
              FROM `glpi_computers_items`
              WHERE `computers_id`='" . $this->fields['id']."'";
   }
}

/// Disk class
class ComputerDisk extends CommonDBTM {
   /**
   * Constructor
   **/
   function __construct() {
      $this->table = "glpi_computersdisks";
      $this->type = COMPUTERDISK_TYPE;
      $this->entity_assign=true;
   }

   function prepareInputForAdd($input) {
      // Not attached to software -> not added
      if (!isset($input['computers_id']) || $input['computers_id'] <= 0) {
         return false;
      }
      return $input;
   }

   function post_getEmpty () {
      $this->fields["totalsize"]='0';
      $this->fields["freesize"]='0';
   }

   function getEntityID () {
      if (isset($this->fields['computers_id']) && $this->fields['computers_id'] >0) {
         $computer=new Computer();

         $computer->getFromDB($this->fields['computers_id']);
         return $computer->fields['entities_id'];
      }
      return -1;
   }

   /**
   * Print the version form
   *
   *@param $target form target
   *@param $ID Integer : Id of the version or the template to print
   *@param $computers_id ID of the computer for add process
   *
   *@return true if displayed  false if item not found or not right to display
   **/
   function showForm($target,$ID,$computers_id=-1) {
      global $CFG_GLPI,$LANG;

      if (!haveRight("computer","w")) {
        return false;
      }

      if ($ID > 0) {
         $this->check($ID,'r');
      } else {
         // Create item
         $this->check(-1,'w');
         $this->getEmpty();
      }

      $this->showTabs($ID, false, $_SESSION['glpi_tab'],array(),"computers_id="
                      .$this->fields['computers_id']);
      $this->showFormHeader($target,$ID,'',2);

      if ($ID>0) {
        $computers_id=$this->fields["computers_id"];
      } else {
         echo "<input type='hidden' name='computers_id' value='$computers_id'>";
      }

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['help'][25]."&nbsp;:</td>";
      echo "<td colspan='3'>";
      echo "<a href='computer.form.php?id=".$computers_id."'>".
             getDropdownName("glpi_computers",$computers_id)."</a>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['common'][16]."&nbsp;:</td>";
      echo "<td>";
      autocompletionTextField("name","glpi_computersdisks","name",$this->fields["name"],40);
      echo "</td><td>".$LANG['computers'][6]."&nbsp;:</td>";
      echo "<td>";
      autocompletionTextField("device","glpi_computersdisks","device", $this->fields["device"],40);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['computers'][5]."&nbsp;:</td>";
      echo "<td>";
      autocompletionTextField("mountpoint","glpi_computersdisks","mountpoint",
                              $this->fields["mountpoint"],40);
      echo "</td><td>".$LANG['computers'][4]."&nbsp;:</td>";
      echo "<td>";
      dropdownValue("glpi_filesystems", "filesystems_id", $this->fields["filesystems_id"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'>";
      echo "<td>".$LANG['computers'][3]."&nbsp;:</td>";
      echo "<td>";
      autocompletionTextField("totalsize","glpi_computersdisks","totalsize",
                              $this->fields["totalsize"],40);
      echo "&nbsp;".$LANG['common'][82]."</td>";

      echo "<td>".$LANG['computers'][2]."&nbsp;:</td>";
      echo "<td>";
      autocompletionTextField("freesize","glpi_computersdisks","freesize",
                              $this->fields["freesize"],40);
      echo "&nbsp;".$LANG['common'][82]."</td></tr>";

      $this->showFormButtons($ID,'',2);

      echo "<div id='tabcontent'></div>";
      echo "<script type='text/javascript'>loadDefaultTab();</script>";

      return true;

   }

   function defineTabs($ID,$withtemplate) {
      global $LANG,$CFG_GLPI;

      $ong[1]=$LANG['title'][26];

      return $ong;
   }
}

?>