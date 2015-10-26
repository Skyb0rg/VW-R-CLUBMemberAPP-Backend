<?php

require 'vendor/autoload.php';
 

use Parse\ParseClient;
use Parse\ParseObject;
use Parse\ParseQuery;
 
use Parse\ParseUser;

ParseClient::initialize('rG0fHSY1NGztjMC3k6DJgsOR6FSjGiawXO9ss2kb', 'rd9Z65vMXxT8bhOqcS9UUGWizzaJvymuG2U7KN9e', 'biJ8p7ZfQoE7QVNyeqkini8ZXH4mP6QTFoUgx9AU');


 

//$testObject = ParseObject::create("TestObject");
//$testObject->set("foo", "bar");
//$testObject->save();

$query = new ParseQuery("Chatnachricht");
$query->equalTo("Benutzer", "Jan");
$query = new ParseQuery("ChatMsgs");
//$query->equalTo("Benutzer", "Jan");
$results = $query->find();
echo "Successfully retrieved " . count($results) . " Chatnachricht.";
// Do something with the returned ParseObject values
echo "Empfange " . count($results) . " Chatnachrichten.<br>";

for ($i = 0; $i < count($results); $i++) {
  $object = $results[$i];
  echo $object->getObjectId(); //. ' - ' . print_r($object->get('Benutzer')) . ' - ' . $object->get('content') . "<br>";
  //echo print_r($object->get("Benutzer")) . "<br>";
  //User
        $queryUser = ParseUser::query();
        $chatusername = $object->get("Benutzer");
        $queryUser->equalTo("objectId", $chatusername->getObjectId());
        $resultsuser = $queryUser->find();
        $objectuser = $resultsuser[0];
        //echo "Chatbenutzer: " . print_r($objectuser->get('username')) . "<br>";

  echo $objectuser->get('username') . ": " . $object->get('content') . "<br>";
}



?>
