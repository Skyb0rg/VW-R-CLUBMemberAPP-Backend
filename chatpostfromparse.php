<?php
require 'pass-include.php';
require 'vendor/autoload.php';

use Parse\ParseClient;
use Parse\ParseObject;
use Parse\ParseQuery;
use Parse\ParseUser;

ParseClient::initialize($Parse1, $Parse2, $Parse3);

        //UserList pre-read
    $queryUserList = ParseUser::query();
    $resultsuserList = $queryUserList->find();

$query = new ParseQuery("ChatMsgs");
$query->equalTo("toForum", true);
$results = $query->find();
for ($i = 0; $i < count($results); $i++) {
  $object = $results[$i];
  //User compare
        $chatusername = $object->get("Benutzer");
                        for ($j = 0; $j < count($resultsuserList); $j++) {
           $objectUserList = $resultsuserList[$j];
            if($chatusername->getObjectId() == $objectUserList->getObjectId()) {
                                $objectFindUserList = $objectUserList;
            }else echo "";
        }

	$ctextdirect = $object->get('content');
        $name = $objectFindUserList->get('username');
	$myfile = fopen("chatfromparse.txt", "w");
	$txt = $ctextdirect ."\n".$name."\n";
	fwrite($myfile, $txt);
	fclose($myfile);
	
	$ctextdirect = $vbulletin->db->escape_string($ctextdirect);
	$name = $vbulletin->db->escape_string($name);
	$vbulletin->db->query_write("
									INSERT INTO " . TABLE_PREFIX . "mgc_cb_evo_chat (dateline, fromuid, touid, ctext, sticky, chanid, coidentifier, editdate, userip, name,tpforumid, iswarning, warningtype, usergroupid, displaygroupid, hascustomavatar, avatarpath, avatardateline, avwidth, avheight, avheight_thumb, avwidth_thumb,avatarid, avatarrevision,tousergroupid, todisplaygroupid, tohascustomavatar, toavatarpath, toavatardateline, toavwidth, toavheight, toavheight_thumb, toavwidth_thumb, tofiledata_thumb, toavatarid, toavatarrevision, tpthreaduserid)
	VALUES ('" . TIMENOW . "', '3211', '0', '$ctextdirect', '0', '0', '0', '0', '1.1.1.1', '$name', '0', '0', '', '2', '0', '1', '0', '0', '0', '0', '0', '0','0', '0','0', '0', '0', '', '0', '0', '0', '0', '0', '', '0', '0', '0')");
  
	//delete toForum-Field
	$toForumdelete = $query->get($object->getObjectId());
	$toForumdelete->delete("toForum");
	$toForumdelete->set("acceptedByForum", true);
	$toForumdelete->save();

}
?>

