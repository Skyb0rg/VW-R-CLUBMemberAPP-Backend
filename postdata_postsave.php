<?php
if (!is_object($this->dbobject))
{
	exit;
}

global $MGCCbEvoCore;

if ($this->existing['visible'] == 0 && $this->fetch_field('visible') == 1 && !defined('DISABLE_MGCCBEVO_NOTIFICATION'))
{
	if (substr($this->registry->options['templateversion'],0,3) != "3.6")
	{
		$sql_add_fields = ",customavatar.height_thumb AS avheight_thumb, customavatar.width_thumb AS avwidth_thumb, customavatar.filedata_thumb";
	}         

	// Get threadid, forumid and all userinfo
	$tinfo = $this->dbobject->query_first("
		SELECT t.threadid,t.forumid,t.title,t.firstpostid,t.postuserid,
				 u.userid,u.username,u.usergroupid,u.membergroupids,u.displaygroupid,
				 avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline, 
				 customavatar.width AS avwidth, customavatar.height AS avheight,u.avatarid,u.avatarrevision									
				 $sql_add_fields
		FROM " . TABLE_PREFIX . "post AS p
		LEFT JOIN " . TABLE_PREFIX . "thread AS t on (p.threadid=t.threadid)
		LEFT JOIN " . TABLE_PREFIX . "user AS u on (p.userid=u.userid)
		LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON (avatar.avatarid = u.avatarid)
		LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON (customavatar.userid = u.userid)
		WHERE postid='" . $postid . "'
	");

    if (!empty($this->registry->options['mgc_cb_evo_newthreadpost_ffid']))
    {
        $fids = explode(',',$this->registry->options['mgc_cb_evo_newthreadpost_ffid']);
        
        if (in_array($tinfo['forumid'],$fids))
        {
            $no_notification = 1;
        }
        else
        {
            $no_notification = 0;
        }
    }
    else
    {
        $no_notification = 0;
    }
                    
    if ($this->registry->options['mgc_cb_evo_warn_newpost'] && ($tinfo['firstpostid'] != $postid) && !$no_notification && $MGCCbEvoCore->evo_permissions->authorize_user_newthread_newpost_notif($tinfo))
    {    
		$tinfo['avatarpath'] 		= $this->dbobject->escape_string($tinfo['avatarpath']);
		$tinfo['filedata_thumb'] 	= $this->dbobject->escape_string($tinfo['filedata_thumb']);
		$tinfo['username'] 			= $this->dbobject->escape_string($tinfo['username']);
		
    	$text = $this->dbobject->escape_string($tinfo['threadid'] . "," . $postid . "," . $tinfo['title']);
    	
    	$add_field 		= '';
    	$add_content 	= '';
    	
    	if (!empty($tinfo['avatarpath']))
    	{
    		$add_field 		.= ",avatarpath";
    		$add_content 	.= ",'" . $tinfo['avatarpath'] . "'";
    	}
    	
    	if (!empty($tinfo['avatardateline']))
    	{
    		$add_field 		.= ",avatardateline";
    		$add_content 	.= ",'" . $tinfo['avatardateline'] . "'";
    	}
    	
    	if (!empty($tinfo['avwidth']))
    	{
    		$add_field 		.= ",avwidth";
    		$add_content 	.= ",'" . $tinfo['avwidth'] . "'";
    	}    
    	
    	if (!empty($tinfo['avheight']))
    	{
    		$add_field 		.= ",avheight";
    		$add_content 	.= ",'" . $tinfo['avheight'] . "'";
    	}    		
    	
    	if (!empty($tinfo['avheight_thumb']))
    	{
    		$add_field 		.= ",avheight_thumb";
    		$add_content 	.= ",'" . $tinfo['avheight_thumb'] . "'";
    	}    		
    	
    	if (!empty($tinfo['avwidth_thumb']))
    	{
    		$add_field 		.= ",avwidth_thumb";
    		$add_content 	.= ",'" . $tinfo['avwidth_thumb'] . "'";
    	}  		
    	
    	if (!empty($tinfo['filedata_thumb']))
    	{
    		$add_field 		.= ",filedata_thumb";
    		$add_content 	.= ",'" . $tinfo['filedata_thumb'] . "'";
    	}		
    	
		// Let's insert the new thread warning in the chatbox
      	$this->dbobject->query_write("
      		INSERT INTO " . TABLE_PREFIX . "mgc_cb_evo_chat 	
      		(      			
      			dateline,fromuid,name,ctext,sticky,chanid,coidentifier,iswarning,warningtype,tpforumid,tpthreaduserid,
      			hascustomavatar,avatarid,avatarrevision,usergroupid,displaygroupid
      			$add_field
      		)
         	VALUES
         	(
         		'" . TIMENOW . "','" . $tinfo['userid'] . "','" . $tinfo['username'] . "','$text',0,0,0,1,'post','" . $tinfo['forumid'] . "','" . $tinfo['postuserid'] . "',
         		'" . $tinfo['hascustomavatar'] . "','" . $tinfo['avatarid'] . "','" . $tinfo['avatarrevision'] . "',
         		'" . $tinfo['usergroupid'] . "','" . $tinfo['displaygroupid'] . "'
         		$add_content
         	)
		");
    }
}
?>