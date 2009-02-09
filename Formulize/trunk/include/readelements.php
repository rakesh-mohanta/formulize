<?php
###############################################################################
##     Formulize - ad hoc form creation and reporting module for XOOPS       ##
##                    Copyright (c) 2004 Freeform Solutions                  ##
###############################################################################
##                    XOOPS - PHP Content Management System                  ##
##                       Copyright (c) 2000 XOOPS.org                        ##
##                          <http://www.xoops.org/>                          ##
###############################################################################
##  This program is free software; you can redistribute it and/or modify     ##
##  it under the terms of the GNU General Public License as published by     ##
##  the Free Software Foundation; either version 2 of the License, or        ##
##  (at your option) any later version.                                      ##
##                                                                           ##
##  You may not change or alter any portion of this comment or credits       ##
##  of supporting developers from this source code or any supporting         ##
##  source code which is considered copyrighted (c) material of the          ##
##  original comment or credit authors.                                      ##
##                                                                           ##
##  This program is distributed in the hope that it will be useful,          ##
##  but WITHOUT ANY WARRANTY; without even the implied warranty of           ##
##  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            ##
##  GNU General Public License for more details.                             ##
##                                                                           ##
##  You should have received a copy of the GNU General Public License        ##
##  along with this program; if not, write to the Free Software              ##
##  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307 USA ##
###############################################################################
##  Author of this file: Freeform Solutions                                  ##
##  Project: Formulize                                                       ##
###############################################################################

// display element data has the following format:
// de_[form]_[entry]_[elementid]
// form is the form id
// entry is the entry_id from the form's table
// elementid is the ele_id from the form elements table

// also out there...
// denosave_ which should be catalogued and then ignored
// desubformX_ where X is 0 through n, a number indicating which of the subform blank default entries this was
// userprofile_ sent back when a user profile form is being displayed (because regcodes has been applied to the system)

// proxy entries are indicated by the proxy entry box
// $_POST['proxyuser'] is an array of the proxy users selected
// default is 'noproxy'
// this would apply to all "new" entries received from this save

// This logic will process ALL form submissions from Formulize, all elements, no matter where they have appeared.

// Should always be included from the global scope!!  So all declared variables in here are in the global namespace.

if(isset($formulize_readElementsWasRun)) { return false; } // intended to make sure this file is only executed once.

include_once XOOPS_ROOT_PATH . "/modules/formulize/include/functions.php";

// if we're being called from pageworks, or elsewhere, then certain values won't be set so we'll need to check for them in other ways...
if(!$gperm_handler) {
	$gperm_handler =& xoops_gethandler('groupperm');
}
if(!isset($mid)) {
	$mid = getFormulizeModId();
}

if(!$myts) { $myts =& MyTextSanitizer::getInstance(); }

//$formulize_submittedElementCaptions = array(); // put into global scope and pulled down by readform.php when determining what elements have been submitted, so we don't blank data that is sent this way

$groups = $xoopsUser ? $xoopsUser->getGroups() : array(0=>XOOPS_GROUP_ANONYMOUS); // for some reason, even though this is set in pageworks index.php file, depending on how/when this file gets executed, it can have no value (in cases where there are pageworks blocks on pageworks pages, for instance?!)
$uid = $xoopsUser ? $xoopsUser->getVar('uid') : 0;
$uid = isset($GLOBALS['userprofile_uid']) ? $GLOBALS['userprofile_uid'] : $uid; // if the userprofile form is in play and a new user has been set, then use that uid

if(!$element_handler) {
	$element_handler =& xoops_getmodulehandler('elements', 'formulize');
}

$formulize_up = array(); // container for user profile info
$formulize_elementData = array(); // this array has multiple dimensions, in this order:  form id, entry id, element id.  "new" means a nea entry.  Multiple new entries will be recorded as new1, new2, etc
$formulize_subformBlankCues = array();
// loop through POST and catalogue everything that we need to do something with
foreach($_POST as $k=>$v) {

	if(substr($k, 0, 9) == "denosave_") { // handle no save elements
		$element_metadata = explode("_", $k);
		$element =& $element_handler->get($element_metadata[3]);
		$noSaveHandle = $element->getVar('ele_colhead') ? $element->getVar('ele_colhead') : $element->getVar('ele_caption');
		$noSaveHandle = str_replace(" ", "", ucwords(strtolower($noSaveHandle)));
		// note this will assign the raw value from POST to these globals.  It will not be human readable in many cases.
		$GLOBALS['formulizeEleSub_' . $noSaveHandle] = $v;
		$GLOBALS['formulizeEleSub_' . $element_metadata[3]] = $v;
		unset($element);
		
	} elseif(substr($k, 0, 9) == "desubform") { // handle blank subform elements
		$elementMetaData = explode("_", $k);
		$elementObject = $element_handler->get($elementMetaData[3]);
		$v = prepDataForWrite($elementObject, $v);
		if($v == "{SKIPTHISDATE}") { $v = ""; }
		if($v === "" AND $elementMetaData[2] == "new") { continue; } // don't store blank values for new entries, we don't want to write those (if desubform is used only for blank defaults, then it will always be "new" but we'll keep this as is for now, can't hurt)
		$blankSubformCounter = trim(substr($k, 9, 2), "_"); // grab up to two spaces after the "desubform" text, since that will have the unique identifier of this new entry (ie: which blank subform entry this value belongs to)
		$formulize_elementData[$elementMetaData[1]][$elementMetaData[2].$blankSubformCounter][$elementMetaData[3]] = $v;
		if(!isset($formulize_subformBlankCues[$elementMetaData[1]])) {
			$formulize_subformBlankCues[$elementMetaData[1]] = $elementMetaData[1]; // we will watch for entries being written to this form, and store the resulting entries in global space so we can synch them later
		}

		// also...the entry id that the new entries received was stored after writing in this array:
		// this is the subform id, and the subform placeholder, which must receive the last insert id when it's values are saved
		//$GLOBALS['formulize_subformCreateEntry'][$element->getVar('id_form')][$desubformEntryIndex]
		
	} elseif(substr($k, 0, 6) == "decue_") {
		// store values according to form, entry and element ID 
		// prep them all for writing
		$elementMetaData = explode("_", $k);
		if(isset($_POST["de_".$elementMetaData[1]."_".$elementMetaData[2]."_".$elementMetaData[3]])) {
			$elementObject = $element_handler->get($elementMetaData[3]);
			$v = prepDataForWrite($elementObject, $_POST["de_".$elementMetaData[1]."_".$elementMetaData[2]."_".$elementMetaData[3]]);
			if($v == "{SKIPTHISDATE}") { $v = ""; } // blank the value if something invalid was found when processing it
			$formulize_elementData[$elementMetaData[1]][$elementMetaData[2]][$elementMetaData[3]] = $v;
		} else {
			$formulize_elementData[$elementMetaData[1]][$elementMetaData[2]][$elementMetaData[3]] = ""; // no value returned for this element that was included (cue was found) so we write it as blank to the db
		}		
	
	} elseif(substr($k, 0, 12) == "userprofile_") {
		$formulize_up[substr($k, 12)] = $v;
	}
}

// write all the user profile info
if(count($formulize_up)>0) {
	  $formulize_up['uid'] = $GLOBALS['userprofile_uid'];
		writeUserProfile($formulize_up, $uid);
}

// figure out proxy user situation
global $xoopsUser;
$creation_users = array();
if(isset($_POST['proxyuser'])) {
	foreach($_POST['proxyuser'] as $puser) {
		if($puser == "noproxy") { continue; }
		$creation_users[] = $puser;
	}
}
if(count($creation_users) == 0) { // no proxy users specified
	$creation_users[] = $uid;
}

// do all the actual writing through the formulize_writeEntry function
// log the new entry ids created
// log the notification events
$formulize_newEntryIds = array();
$formulize_newEntryUsers = array();
$formulize_allWrittenEntryIds = array();
$notEntriesList = array();
if(count($formulize_elementData) > 0 ) { // do security check if it looks like we're going to be writing things...
	$cururl = getCurrentURL();
	$module_handler =& xoops_gethandler('module');
	$config_handler =& xoops_gethandler('config');
  $formulizeModule =& $module_handler->getByDirname("formulize");
  $formulizeConfig =& $config_handler->getConfigsByCat(0, $formulizeModule->getVar('mid'));
  $modulePrefUseToken = $formulizeConfig['useToken'];
	$useToken = $screen ? $screen->getVar('useToken') : $modulePrefUseToken; 
	if(isset($GLOBALS['xoopsSecurity']) AND $useToken) { // avoid security check for versions of XOOPS that don't have that feature, or for when it's turned off
		if (!$GLOBALS['xoopsSecurity']->check() AND (!strstr($cururl, "modules/wfdownloads") AND !strstr($cururl, "modules/smartdownload"))) { // skip the security check if we're in wfdownloads/smartdownloads since that module should already be handling the security checking
			print "<b>Error: the data you submitted could not be saved in the database.</b>";
			return false;
		}
	}
}
foreach($formulize_elementData as $fid=>$entryData) { // for every form we found data for...
	
	// figure out permissions on the forms
	$add_own_entry = $gperm_handler->checkRight("add_own_entry", $fid, $groups, $mid);
	$add_proxy_entries = $gperm_handler->checkRight("add_proxy_entries", $fid, $groups, $mid);
	$update_own_entry = $gperm_handler->checkRight("update_own_entry", $fid, $groups, $mid);
	$update_other_entries = $gperm_handler->checkRight("update_other_entries", $fid, $groups, $mid);

	foreach($entryData as $entry=>$values) { // for every entry in the form...
		if(substr($entry, 0 , 3) == "new") { // handle entries in the form that are new...if there is more than one new entry in a dataset, they will be listed as new1, new2, new3, etc
			if(strlen($entry) > 3) { $entry = "new"; } // remove the number from the end of any new entry flags that have numbers
			foreach($creation_users as $creation_user) {
				if(($creation_user == $uid AND $add_own_entry) OR ($creation_user != $uid AND $add_proxy_entries)) { // only proceed if the user has the right permissions
					$writtenEntryId = formulize_writeEntry($values, $entry, "", $creation_user);
					if(isset($formulize_subformBlankCues[$fid])) {
						$GLOBALS['formulize_subformCreateEntry'][$fid][] = $writtenEntryId;
					}
					$formulize_newEntryIds[$fid][] = $writtenEntryId; // log new ids (and all ids) and users for recording ownership info later
					$formulize_newEntryUsers[$fid][] = $creation_user;
					$formulize_allWrittenEntryIds[$fid][] = $writtenEntryId;
					$notEntriesList['new_entry'][$fid][] = $writtenEntryId; // log the notification info
					writeOtherValues($writtenEntryId, $fid); // write the other values for this entry
					if($creation_user == 0) { // handle cookies for anonymous users
						setcookie('entryid_'.$fid, $writtenEntryId, time()+60*60*24*7, '/');	// the slash indicates the cookie is available anywhere in the domain (not just the current folder)				
						$_COOKIE['entryid_'.$fid] = $writtenEntryId;
					}
				}
			}
		} else { // handle existing entries...
			$owner = getEntryOwner($entry, $fid);
			if(($owner == $uid AND $update_own_entry) OR ($owner != $uid AND $update_other_entries)) { // only proceed if the user has the right permissions
				$writtenEntryId = formulize_writeEntry($values, $entry);
				$formulize_allWrittenEntryIds[$fid][] = $writtenEntryId; // log the written id
				$notEntriesList['update_entry'][$fid][] = $writtenEntryId; // log the notification info
				writeOtherValues($writtenEntryId, $fid); // write the other values for this entry
			}
		}
	}
}
// set the ownership info of the new entries created...use a custom named handler, so we don't conflict with any other data handlers that might be using the more conventional 'data_handler' name, which can happen depending on the scope within which this file is included
foreach($formulize_newEntryIds as $fid=>$entries){
	$data_handler_for_owner_groups = new formulizeDataHandler($fid);
	$data_handler_for_owner_groups->setEntryOwnerGroups($formulize_newEntryUsers[$fid],$formulize_newEntryIds[$fid]);
	unset($data_handler_for_owner_groups);
}
// blank values that need blanking
// need to send cues in $_POST about entries that were on screen, and for any that we don't have a value for, set the value to "" and they will simply be set to null when the entry is written, presto.

// send notifications
foreach($notEntriesList as $notEvent=>$notDetails) {
	foreach($notDetails as $notFid=>$notEntries) {
		$notEntries = array_unique($notEntries); 
		sendNotifications($notFid, $notEvent, $notEntries);
	}
}

$formulize_readElementsWasRun = true; // flag that will prevent this from running again

// set the variables that need to be in global space, just in case this file was included from inside a function, which can happen in some cases
$GLOBALS['formulize_newEntryIds'] = $formulize_newEntryIds;
$GLOBALS['formulize_newEntryUsers'] = $formulize_newEntryUsers;
$GLOBALS['formulize_allWrittenEntryIds'] = $formulize_allWrittenEntryIds;
$GLOBALS['formulize_readElementsWasRun'] = $formulize_readElementsWasRun;

return $formulize_allWrittenEntryIds;


// THIS FUNCTION TAKES THE DATA PASSED BACK FROM THE USERPROFILE PART OF A FORM AND SAVES IT AS PART OF THE XOOPS USER PROFILE
function writeUserProfile($data, $uid) {

	// following code largely borrowed from edituser.php
	// values we receive:
	// name
	// email
	// viewemail
	// timezone_offset
	// password
	// vpass
	// attachsig
	// user_sig
	// umode
	// uorder
	// notify_method
	// notify_mode

	global $xoopsUser, $xoopsConfig;
	$config_handler =& xoops_gethandler('config');
	$xoopsConfigUser =& $config_handler->getConfigsByCat(XOOPS_CONF_USER);

	include_once XOOPS_ROOT_PATH . "/language/" . $xoopsConfig['language'] . "/user.php";

	$errors = array();
    if (!empty($data['uid'])) {
        $uid = intval($data['uid']);
    }
		
    if (empty($uid)) {
	redirect_header(XOOPS_URL,3,_US_NOEDITRIGHT);
        exit();
    } elseif(is_object($xoopsUser)) {
			if($xoopsUser->getVar('uid') != $uid) {
				redirect_header(XOOPS_URL,3,_US_NOEDITRIGHT);
				exit();	
			}
    }

    $myts =& MyTextSanitizer::getInstance();
    if ($xoopsConfigUser['allow_chgmail'] == 1) {
        $email = '';
        if (!empty($data['email'])) {
            $email = $myts->stripSlashesGPC(trim($data['email']));
        }
        if ($email == '' || !checkEmail($email)) {
            $errors[] = _US_INVALIDMAIL;
        }
    }
    $password = '';
    $vpass = '';
    if (!empty($data['password'])) {
     	  $password = $myts->stripSlashesGPC(trim($data['password']));
    }
    if ($password != '') {
     	  if (strlen($password) < $xoopsConfigUser['minpass']) {
           	$errors[] = sprintf(_US_PWDTOOSHORT,$xoopsConfigUser['minpass']);
        }
        if (!empty($data['vpass'])) { 
     	      $vpass = $myts->stripSlashesGPC(trim($data['vpass']));
        }
     	  if ($password != $vpass) {
            $errors[] = _US_PASSNOTSAME;
     	  }
    }
    if (count($errors) > 0) {
        echo '<div>';
        foreach ($errors as $er) {
            echo '<span style="color: #ff0000; font-weight: bold;">'.$er.'</span><br />';
        }
        echo '</div><br />';
    } else {
        $member_handler =& xoops_gethandler('member');
        $edituser =& $member_handler->getUser($uid);
        $edituser->setVar('name', $data['name']);
        if ($xoopsConfigUser['allow_chgmail'] == 1) {
            $edituser->setVar('email', $email, true);
        }
        $user_viewemail = (!empty($data['user_viewemail'])) ? 1 : 0;
        $edituser->setVar('user_viewemail', $user_viewemail);
        if ($password != '') {
            $edituser->setVar('pass', md5($password), true);
        }
        $edituser->setVar('timezone_offset', $data['timezone_offset']);
        $attachsig = !empty($data['attachsig']) ? 1 : 0;
	  $edituser->setVar('attachsig', $attachsig);
        $edituser->setVar('user_sig', xoops_substr($data['user_sig'], 0, 255));
        $edituser->setVar('uorder', $data['uorder']);
        $edituser->setVar('umode', $data['umode']);
        $edituser->setVar('notify_method', $data['notify_method']);
        $edituser->setVar('notify_mode', $data['notify_mode']);

        if (!$member_handler->insertUser($edituser)) {
            echo $edituser->getHtmlErrors();
						exit();
        }
    }

}

?>