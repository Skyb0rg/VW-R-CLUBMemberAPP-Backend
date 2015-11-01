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
$results = $query->find();
echo "Empfange " . count($results) . " Chatnachrichten.<br>";

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
  echo $objectFindUserList->get('username') . ": " . $object->get('content') . "<br>";
}      
?>
