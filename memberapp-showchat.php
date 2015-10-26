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

$query = new ParseQuery("ChatMsgs");
//$query->equalTo("Benutzer", "Jan");
$results = $query->find();
echo "Empfange " . count($results) . " Chatnachrichten.<br>";

for ($i = 0; $i < count($results); $i++) {
  $object = $results[$i];
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



public function findUser($email)
{
    $parseQuery = new parseQuery($class = '_User');        
    $parseQuery->where('email', $email);            
    $consumerQuery = $parseQuery->find();
    $result = $consumerQuery->results;
    return $result;
}



#https://parse.com/docs/php/guide#users
$query = ParseUser::query();
$query->equalTo("gender", "female"); 
$results = $query->find();


 let findBenutzer:PFQuery = PFUser.query()!
        
findBenutzer.whereKey("objectId", equalTo: (chattext.objectForKey("Benutzer")?.objectId)!)

findBenutzer.findObjectsInBackgroundWithBlock{
	(objects:[PFObject]?, error:NSError?) -> Void in
	if (error == nil && objects != nil){
		
		let user:PFUser = (objects! as NSArray).lastObject as! PFUser
		cell.usernameLabel.text = user.username
	}
}

##########################################################################################################
		
$query = new ParseQuery("Note");
try {
  // This will throw a ParseException, the object is not found.
  $result = $query->get("aBcDeFgH")
} catch (ParseException $error) {
  // $error is an instance of ParseException with details about the error.
  echo $error->getCode();
  echo $error->getMessage();
}
