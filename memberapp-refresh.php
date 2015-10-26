<?php

require 'vendor/autoload.php';
 
use Parse\ParseClient;
use Parse\ParseObject;
use Parse\ParseQuery;
 
ParseClient::initialize('rG0fHSY1NGztjMC3k6DJgsOR6FSjGiawXO9ss2kb', 'rd9Z65vMXxT8bhOqcS9UUGWizzaJvymuG2U7KN9e', 'biJ8p7ZfQoE7QVNyeqkini8ZXH4mP6QTFoUgx9AU');


 
//$testObject = ParseObject::create("TestObject");
//$testObject->set("foo", "bar");
//$testObject->save();

$query = new ParseQuery("Chatnachricht");
$query->equalTo("Benutzer", "Jan");
$results = $query->find();
echo "Successfully retrieved " . count($results) . " Chatnachricht.";
// Do something with the returned ParseObject values
for ($i = 0; $i < count($results); $i++) {
  $object = $results[$i];
  echo $object->getObjectId(); //. ' - ' . print_r($object->get('Benutzer')) . ' - ' . $object->get('content') . "<br>";
}



?>

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
