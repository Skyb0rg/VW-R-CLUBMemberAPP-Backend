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
