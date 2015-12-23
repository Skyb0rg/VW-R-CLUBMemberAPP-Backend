<?php
require 'pass-include.php';
require 'vendor/autoload.php';

use Parse\ParseClient;
use Parse\ParseObject;
use Parse\ParseQuery;
use Parse\ParseUser;
use Parse\ParseFile;

ParseClient::initialize($Parse1, $Parse2, $Parse3);

$myfile = fopen("chatfromparse.txt", "a");
$txt = date("F j, Y, g:i a") . " war start: \n";
fwrite($myfile, $txt);

$query = new ParseQuery("ChatMsgs");
$query->equalTo("toForum", true);
$query->ascending("createdAt");
$query->includeKey("Benutzer");
$results = $query->find();
for ($i = 0; $i < count($results); $i++) {
  $object = $results[$i];

	$ctextdirect = $object->get('content');
	$ctextdirect = $vbulletin->db->escape_string($ctextdirect);
	$picture = $object->get("oripicture");
	$pictureurl = $object->get('oripicture'); $pictureurl = $picture ? $picture->getUrl() : null ;
	if ($pictureurl != null) {
			$ctextdirect = '[font=Comic Sans MS][color=#000000][size=3][AppPic]'.$pictureurl.'[/AppPic][/size][/color][/font]';
	}
	$objectFindUserList = $object->get("Benutzer");
    $name = $objectFindUserList->get('fullname');
	//$userid = $objectFindUserList->get('vbuserid');
	$userid = (is_numeric($objectFindUserList->get('vbuserid'))) ? $objectFindUserList->get('vbuserid') : "3211";
	$txt = date("F j, Y, g:i a")." ".TIMENOW." ".strtotime("now")." ".$name.": ".$ctextdirect."\n";
	fwrite($myfile, $txt);

	$name = $vbulletin->db->escape_string($name);
	$vbulletin->db->query_write("
									INSERT INTO " . TABLE_PREFIX . "mgc_cb_evo_chat (dateline, fromuid, touid, ctext, sticky, chanid, coidentifier, editdate, userip, name,tpforumid, iswarning, warningtype, usergroupid, displaygroupid, hascustomavatar, avatarpath, avatardateline, avwidth, avheight, avheight_thumb, avwidth_thumb,avatarid, avatarrevision,tousergroupid, todisplaygroupid, tohascustomavatar, toavatarpath, toavatardateline, toavwidth, toavheight, toavheight_thumb, toavwidth_thumb, tofiledata_thumb, toavatarid, toavatarrevision, tpthreaduserid)
	VALUES ('" . strtotime("now") . "', '".$userid."', '0', '$ctextdirect', '0', '0', '0', '0', '1.1.1.1', '$name', '0', '0', '', '2', '0', '1', '0', '0', '0', '0', '0', '0','0', '0','0', '0', '0', '', '0', '0', '0', '0', '0', '', '0', '0', '0')");
  
	//delete toForum-Field
	$toForumdelete = $query->get($object->getObjectId());
	$toForumdelete->delete("toForum");
	$toForumdelete->set("acceptedByForum", true);
	$toForumdelete->save();

sleep(1);
}
fclose($myfile);
?>

