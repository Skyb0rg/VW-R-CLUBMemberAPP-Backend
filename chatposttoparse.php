<?php
require 'pass-include.php';

require 'vendor/autoload.php';

use Parse\ParseClient;
use Parse\ParseObject;
use Parse\ParseQuery;
use Parse\ParseUser;
use Parse\ParseCloud;

ParseClient::initialize($Parse1, $Parse2, $Parse3);

//$chat = '<p>Test-VW-R-CLUB</p><!-- Kommentar --> <a href="#fragment">2</a>';
//$puser = "testuser";
$puser = $vbulletin->userinfo['username'];

$myfile = fopen("chattoparse.txt", "w");
$txt = $chat ."\n";
fwrite($myfile, $txt);
$txt = $puser ."\n";
fwrite($myfile, $txt);
$cleanhtmltext = strip_tags($chat);
$txt = $cleanhtmltext ."\n";
fwrite($myfile, $txt);
fclose($myfile);

	try {
	  $user = ParseUser::logIn("vw-r-club", "vw-r-cIub");
		// Do stuff after successful login.
		//Send Message
		$ChatMsg = new ParseObject("ChatMsgs");
		$ChatMsg->set("content", $cleanhtmltext);
		$ChatMsg->set("Benutzer", ParseUser::getCurrentUser());
		$ChatMsg->set("groupId", "9GXWx7cWHh");
        $ChatMsg->set("rclubchatbenutzer",$puser);
		try {
		  $ChatMsg->save();
		}catch (Exception $e) {
		  //echo 'Exception : ',  $e->getMessage(), "\n";
		}
		
	} catch (ParseException $error) {
 
	}
	try {
		  ParseCloud::run('sendfromForumPushToApp', ['user' => $puser,'message' => $cleanhtmltext]);
	}catch (Exception $e) {
		  //echo 'Exception : ',  $e->getMessage(), "\n";
	}

?>
