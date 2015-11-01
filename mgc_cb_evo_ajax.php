<?php

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'mgc_cb_evo_ajax');
define('LOCATION_BYPASS', 1);
define('NOPMPOPUP', 1);
define('CSRF_PROTECTION', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array();

// get special data templates from the datastore
$specialtemplates = array('bbcodecache','mgc_cb_evo_channels','mgc_cb_evo_commands','mgc_cb_evo_bots');

// pre-cache templates used by all actions
$globaltemplates = array(
	'mgc_cb_evo_editor',
	'mgc_cb_evo_chatbit',
	'mgc_cb_evo_chatbit_menu',
	'editor_jsoptions_font',
	'editor_jsoptions_size'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

$_POST['ajax'] = 1;

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/class_xml.php');
require_once(DIR . '/includes/class_bbcode.php');
require_once(DIR . '/mgc_cb_evo/classes/class_formatting.php');

// Compare datelines of two chatbits array elements but keeps sticky at top
function array_compare_dateline($a,$b)
{
	// Both chat stickied
	if ($a['sticky'] && !$b['sticky'])
	{
		return 1;
	}
	else if ($b['sticky'] && !$a['sticky'])
	{
		return -1;
	}
	else
	{
		if ($a['dateline'] > $b['dateline'])
		{
			return 1;
		}
		else if ($a['dateline'] == $b['dateline'])
		{
			return 0;
		}
		else
		{
			return -1;
		}
	}
}	

// Chatbox content refresh
if ($_POST['action'] == 'ajax_refresh_chat')
{
    // Chatbox status (open or closed or small)
    $status 		= $vbulletin->input->clean_gpc('p', 'status', TYPE_STR);

    // Channel id
    $channel_id 	= $vbulletin->input->clean_gpc('p', 'channel_id', TYPE_UINT);

    // Chatbox location : on a vbulletin page or in its own page (full mode)
    $location 		= $vbulletin->input->clean_gpc('p', 'location', TYPE_STR);

    // First load
    $first_load 	= $vbulletin->input->clean_gpc('p', 'first_load', TYPE_UINT);

    // Chatids array joined
    $chatids  		= $vbulletin->input->clean_gpc('p', 'chatids', TYPE_STR);

    // Special chatids array joined
    $schatids 		= $vbulletin->input->clean_gpc('p', 'schatids', TYPE_STR);
    
    // Script from which it's called
    $this_script 	= $vbulletin->input->clean_gpc('p', 'this_script', TYPE_STR);

    // Normal chats handling
    if (empty($chatids))
    {
		$chatids_array 		= array();
        $skip_older_check 	= 1;
    }
    else
    {
        $chatids_array 	= explode(',',$chatids);
        $skip_older_check 	= 0;
    }

	// When notifications in sidebar block, avoid notifications display from any other page than index
	if (($vbulletin->options['mgc_cb_evo_notifications_display'] == 5) && ($this_script != 'index'))
	{
		$vbulletin->options['mgc_cb_evo_notifications_display'] = 0;
	}

    // Special chats handling in case of separation
    if ($vbulletin->options['mgc_cb_evo_notifications_display'] && $status != "small")
    {
		if (empty($schatids)) {
			$schatids_array 	= array();
			$skip_solder_check 	= 1;
		} else {
			$schatids_array 	= explode(',',$schatids);
			$skip_solder_check 	= 0;
		}
    }

    $chats = '';

    $xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
    $xml->add_group('chats');

    if ($MGCCbEvoCore->evo_permissions->can_view())
    {
        // First load in inactive mode => Update session table
        if ($first_load && $vbulletin->options['mgc_cb_evo_inactive_mode'] && $vbulletin->options['mgc_cb_evo_whoisonline_active'])
        {
            $vbulletin->db->query_write("REPLACE INTO " . TABLE_PREFIX . "mgc_cb_evo_session SET userid='" . $vbulletin->userinfo['userid'] . "',dateline='" . TIMENOW . "'");
        }

        // Warning channels activated => lets refresh it
        if ($vbulletin->options['mgc_cb_evo_channels_warning'])
        {
            if (empty($vbulletin->userinfo['mgc_cb_evo_channel_activities']))
            {
                $channel_activities = array($channel_id => TIMENOW);
            }
            else
            {
                $channel_activities = unserialize($vbulletin->userinfo['mgc_cb_evo_channel_activities']);
                $channel_activities["$channel_id"] = TIMENOW;
            }
            $serialized_channel_activities = serialize($channel_activities);

            $vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "user SET mgc_cb_evo_channel_activities='$serialized_channel_activities' WHERE userid='".  $vbulletin->userinfo['userid'] . "'");
        }

        // Retrieving of the commands permissions and status
        $identifier_array = array('\'0\'');

		if (is_array($vbulletin->mgc_cb_evo_commands))
		{					
			foreach($vbulletin->mgc_cb_evo_commands AS $command)
			{
				if (!empty($command['usergroupids']))
				{
					$command['usergroupids'] = explode(',',$command['usergroupids']);
				}
				else
				{
					$command['usergroupids'] = array();
				}
				
				if (!empty($command['userids']))
				{
					$command['userids'] = explode(',',$command['userids']);
				}	
				else
				{
					$command['userids'] = array();
				}
									
				// Channel not active => next
				if ($command['active'] == 0)
				{
					$commands_status["$command[identifier]"] = '0';
					continue;
				}

				// Test if user has permissions
				$hasaccess = 0;		
				// 0 - Show all commands 
				if ($command['showall'])
				{
					$hasaccess = 1;
				}
				// 1 - Usergroupid test
				if (!$hasaccess && is_array($command['usergroupids']) && in_array($vbulletin->userinfo['usergroupid'],$command['usergroupids']))
				{
					$hasaccess = 1;
				}
				// 2 - Member group test
				if (!$hasaccess && !empty($vbulletin->userinfo['membergroupids']))
				{
					$found 			= 0;
					$ugipds_array 	= explode(',', $vbulletin->userinfo['membergroupids']);		
					foreach ($ugipds_array as $index => $ugpid)
					{
						if (in_array($ugpid,$command['usergroupids']))
						{
							$hasaccess = 1;
						}
					}
				}
				// 3 - Userid test
				if (!$hasaccess && is_array($command['userids']) && in_array($vbulletin->userinfo['userid'],$command['userids']))
				{
					$hasaccess = 1;
				}
				// 4 - Skip channel if not
				if (!$hasaccess)
				{
					$commands_status["$command[identifier]"] = '0';
					continue;
				}
				
				$identifier_array[] = "'" . $command['identifier'] . "'";
				$commands_status["$command[identifier]"] = '1';
			}
		}

        // Building the chats retrieving query
        $left_join = "";
        $tp_notifs_sql_additionals = "";

        // Not private chats where clause
        if ($vbulletin->options['mgc_cb_evo_cmd_pm_tabs'] && $vbulletin->options['mgc_cb_evo_cmd_pm_tab_hidefromgen'])
        {       
	        $where_clause = "
								chanid='$channel_id'
								AND coidentifier IN (" . implode(',',$identifier_array) . ")
								AND touid='0'
								AND iswarning='0'
							"; 
        }
        else
        {       
	        $where_clause = "
								chanid='$channel_id'
								AND coidentifier IN (" . implode(',',$identifier_array) . ")
								AND (touid='0' OR fromuid='" . $vbulletin->userinfo['userid'] . "'
								OR touid='" . $vbulletin->userinfo['userid'] . "')
								AND iswarning='0'
							";
		}
		
        // Ignore command where clause
        if ($commands_status['ignore'] && !empty($vbulletin->userinfo['mgc_cb_evo_ignored']))
        {
            $where_clause .= " AND fromuid NOT IN (" . $vbulletin->userinfo['mgc_cb_evo_ignored'] . ")";
            $where_clause .= " AND touid NOT IN (" . $vbulletin->userinfo['mgc_cb_evo_ignored'] . ")";
        }

        // Forumids in case of newthread or newpost warn active if notification display active
        if ($status == "open" && $vbulletin->options['mgc_cb_evo_notifications_display'] && ($vbulletin->options['mgc_cb_evo_warn_newthread'] || $vbulletin->options['mgc_cb_evo_warn_newpost'] || $vbulletin->options['mgc_cb_evo_warn_thankyou']))
        {
				$allowed_forumids = array_keys($vbulletin->forumcache);

				// get forum ids for all forums user is allowed to view
				foreach ($allowed_forumids AS $index => $forumid)
				{
					$fperms =& $vbulletin->userinfo['forumpermissions']["$forumid"];
					$forum =& $vbulletin->forumcache["$forumid"];

					// Can't view forum or can't view threads
					if (!($fperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($fperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']) OR !verify_forum_password($forumid, $forum['password'], false))
					{
						unset($allowed_forumids["$index"]);
					}
					
					// Can't view other threads
					if (!($fperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']))
					{
						$tp_notifs_sql_additionals .= " AND IF (tpforumid='$forumid', IF (tpthreaduserid='" . $vbulletin->userinfo['userid'] . "',1,0), 1) = 1";
					}
				}
				$allowed_forumids = implode(',',$allowed_forumids);
        }
		
        // Get notifications only when not in collapsed mode and user has the right to when notifications are active
        $sql_where_clause_notifs 	= "";
        $sql_add_field_notifs 		= "";
        $sql_left_join_notifs 		= "";
        if ($status == "open" && $MGCCbEvoCore->evo_permissions->can_view_notifications() && $vbulletin->options['mgc_cb_evo_notifications_display'])
        {
        	// If same query for chats and notifications : construct additional where clause
        	if ($vbulletin->options['mgc_cb_evo_separate_chatsnotif_sql'] == 0)
        	{
				// Newthread warning where clause
				if ($vbulletin->options['mgc_cb_evo_warn_newthread'] && !empty($allowed_forumids))
				{
					$sql_where_clause_notifs .= " OR (iswarning='1' AND warningtype='thread' AND tpforumid IN($allowed_forumids) " . $tp_notifs_sql_additionals . " )";
				}

				// Newpost warning where clause
				if ($vbulletin->options['mgc_cb_evo_warn_newpost'] && !empty($allowed_forumids))
				{
					$sql_where_clause_notifs .= " OR (iswarning='1' AND warningtype='post' AND tpforumid IN($allowed_forumids) " . $tp_notifs_sql_additionals . " )";
				}

				// Thankyou warning where clause
				if ($vbulletin->options['mgc_cb_evo_warn_thankyou'] && !empty($allowed_forumids))
				{
					$sql_where_clause_notifs .= " OR (iswarning='1' AND warningtype='thankyou' AND tpforumid IN($allowed_forumids))";
				}

				($hook = vBulletinHook::fetch_hook('mgc_cb_evo_notifs_sql_together')) ? eval($hook) : false;

			}
			// Else : construct where clause for notifications query
			else
			{
				// Newthread warning where clause
				if ($vbulletin->options['mgc_cb_evo_warn_newthread'] && !empty($allowed_forumids))
				{
					$sql_where_clause_notifs .= "(iswarning='1' AND warningtype='thread' AND tpforumid IN($allowed_forumids) " . $tp_notifs_sql_additionals . " )";
				}

				// Newpost warning where clause
				if ($vbulletin->options['mgc_cb_evo_warn_newpost'] && !empty($allowed_forumids))
				{
					if (!empty($sql_where_clause_notifs))
					{
						$sql_where_clause_notifs .= " OR (iswarning='1' AND warningtype='post' AND tpforumid IN($allowed_forumids) " . $tp_notifs_sql_additionals . " )";
					}
					else
					{
						$sql_where_clause_notifs .= "(iswarning='1' AND warningtype='post' AND tpforumid IN($allowed_forumids) " . $tp_notifs_sql_additionals . " )";
					}
				}

				// Thankyou warning where clause
				if ($vbulletin->options['mgc_cb_evo_warn_thankyou'] && !empty($allowed_forumids))
				{
					if (!empty($sql_where_clause_notifs))
					{
						$sql_where_clause_notifs .= " OR (iswarning='1' AND warningtype='thankyou' AND tpforumid IN($allowed_forumids))";
					}
					else
					{
						$sql_where_clause_notifs .= "(iswarning='1' AND warningtype='thankyou' AND tpforumid IN($allowed_forumids))";
					}
				}

				($hook = vBulletinHook::fetch_hook('mgc_cb_evo_notifs_sql_separate')) ? eval($hook) : false;
			}
        }

        // LIMIT clause construction
        if ($status == "small")
        {
        	// Small version chatbox
        	$limit = $vbulletin->options['mgc_cb_evo_small_nbchats'];
        }
        else if ($status == "closed")
        {
        	// Collapsed chatbox
            $limit = $vbulletin->options['mgc_cb_evo_nbchats_closed'];
        }
        else if ($location == "full")
        {
        	// Full page chatbox
            $limit = $vbulletin->options['mgc_cb_evo_nbchats_full'];
        }
        else
        {
        	// Normal chatbox
            $limit = $vbulletin->options['mgc_cb_evo_nbchats_normal'];
        }

	  	// Retrieving of the notifications is separated
	  	if ($vbulletin->options['mgc_cb_evo_separate_chatsnotif_sql'] && $status == "open" && !empty($sql_where_clause_notifs))
	  	{
			$chats = $vbulletin->db->query_read("
				(
				 SELECT c.*
				 $sql_add_field_notifs
				 FROM " . TABLE_PREFIX . "mgc_cb_evo_chat AS c
				 $sql_left_join_notifs
				 WHERE $where_clause
				 ORDER BY sticky DESC,c.dateline DESC
				 LIMIT $limit
				)
				UNION ALL
				(
				 SELECT c.*
				 $sql_add_field_notifs
				 FROM " . TABLE_PREFIX . "mgc_cb_evo_chat AS c
				 $sql_left_join_notifs
				 WHERE $sql_where_clause_notifs
				 ORDER BY sticky DESC,c.dateline DESC
				 LIMIT " . $vbulletin->options['mgc_cb_evo_nb_notifs'] . "
				)
			");
	  	}
	  	else
	  	{
			$chats = $vbulletin->db->query_read("
				 SELECT c.*
				 $sql_add_field_notifs
				 FROM " . TABLE_PREFIX . "mgc_cb_evo_chat AS c
				 $sql_left_join_notifs
				 WHERE ($where_clause) $sql_where_clause_notifs
				 ORDER BY sticky DESC,c.dateline DESC
				 LIMIT $limit
			");
	  	}

        // Tri des messages selon l'ordre d'affichage (ascendant ou descendant)
        $nbchats = $vbulletin->db->num_rows($chats);
        $new_chatids_array = array();
        $new_schatids_array = array();

        if ($nbchats)
        {
        	// If small version do some tweaks
        	if ($status == "small")
        	{
        		$vbulletin->options['mgc_cb_evo_chat_fontclass'] = "smallfont";
        		$vbulletin->options['mgc_cb_evo_stickychat_replace_date'] = 0;        		
        		$vbulletin->options['mgc_cb_evo_show_date'] = $vbulletin->options['mgc_cb_evo_small_show_date'];
        		$vbulletin->options['mgc_cb_evo_show_time'] = $vbulletin->options['mgc_cb_evo_small_show_time'];
        	}
	   		// Instantiate formatting class
			require_once(DIR . '/mgc_cb_evo/classes/class_formatting.php');
			$MGCCbEvoFormatting = new MGCCbEvo_formatting($vbulletin,$MGCCbEvoCore);
        	
			$parsebbcode = $vbulletin->options['mgc_cb_evo_bbcode'] || $vbulletin->options['mgc_cb_evo_bbcode_url'] || $vbulletin->options['mgc_cb_evo_bbcode_img'];

            $mintime = 0;
            $cpt = $nbchats - 1;

            while ($chat = $vbulletin->db->fetch_array($chats))
            {
            	($hook = vBulletinHook::fetch_hook('mgc_cb_evo_chats_fetcharray')) ? eval($hook) : false;
            
                // Chat was edited or first load ?
                if ($chat['editdate'] == 0 || $first_load || !in_array($chat['chatid'],$chatids_array))
                {
                    $hasbeenedited = 0;
                }
                else
                {
                    $editoffset = TIMENOW - $chat['editdate'];

                    if ($editoffset < $permissions['mgc_cb_evo_refreshrate'])
                    {
                        $hasbeenedited = 1;
                    }
                    else
                    {
                        $hasbeenedited = 0;
                    }
                }

                // In case of specialchats separated, manage code separately
                if ($status == "open" && $vbulletin->options['mgc_cb_evo_notifications_display'] > 1)
                {
                	// Management of the notification
                	if ($chat['iswarning'])
                	{
	                	$new_schatids_array[] = $chat['chatid'];

						// Notification not already in the chatbox or has been edited
						if (!in_array($chat['chatid'],$schatids_array) || $hasbeenedited)
						{
							$chat_cols = $MGCCbEvoFormatting->construct_chat($chat, $parsebbcode, $commands_status, $channel_id);
							$schatbits_array["$cpt"] = array('chatid' => $chat['chatid'],'chat_cols' => $chat_cols, 'edited' => $hasbeenedited, 'specialchat' => $chat['iswarning'], 'sticky' => 0, 'dateline' => $chat['dateline']);
						}

						// Register the oldest notification timeline currently in the chatbox
						if (in_array($chat['chatid'],$schatids_array))
						{
							if (($smintime == 0) || ($chat['dateline'] < $smintime))
							{
								$smintime = $chat['dateline'];
							}
						}

                	}
                	// Management of the chats
                	else
                	{
	                	$new_chatids_array[] = $chat['chatid'];

						// Chat not already in the chatbox or has been edited
						if (!in_array($chat['chatid'],$chatids_array) || $hasbeenedited)
						{
							$chat_cols = $MGCCbEvoFormatting->construct_chat($chat, $parsebbcode, $commands_status, $channel_id);
							$chatbits_array["$cpt"] = array('chatid' => $chat['chatid'],'chat_cols' => $chat_cols, 'edited' => $hasbeenedited, 'specialchat' => $chat['iswarning'], 'sticky' => $chat['sticky'], 'dateline' => $chat['dateline']);
						}

						// Register the oldest chat (non stickied) timeline currently in the chatbox
						if (in_array($chat['chatid'],$chatids_array))
						{
							if (($mintime == 0) || (($chat['dateline'] < $mintime) && !$chat['sticky']))
							{
								$mintime = $chat['dateline'];
							}
						}
                	}
                }
                else
                {
					$new_chatids_array[] = $chat['chatid'];

					// Chat not already in the chatbox or has been edited
					if (!in_array($chat['chatid'],$chatids_array) || $hasbeenedited)
					{
						$chat_cols = $MGCCbEvoFormatting->construct_chat($chat, $parsebbcode, $commands_status, $channel_id);
						$chatbits_array["$cpt"] = array('chatid' => $chat['chatid'],'chat_cols' => $chat_cols, 'edited' => $hasbeenedited, 'specialchat' => $chat['iswarning'], 'sticky' => $chat['sticky'], 'dateline' => $chat['dateline']);
					}

					// Register the oldest chat (non stickied) timeline currently in the chatbox
					if (in_array($chat['chatid'],$chatids_array))
					{
						if (($mintime == 0) || (($chat['dateline'] < $mintime) && !$chat['sticky']))
						{
							$mintime = $chat['dateline'];
						}
					}
                }

                $cpt--;
            }
        }

        // Let's build the xml
        if (sizeof($chatids_array))
        {
            // Remove no more used chats if received array is not empty
            foreach($chatids_array AS $index => $chatid)
            {
                if (!in_array($chatid,$new_chatids_array))
                {
					$xml->add_group('chat');
					$xml->add_tag('type','3');
					$xml->add_tag('chatid',$chatid);
					$xml->add_tag('sticky','0');
					$xml->add_tag('oldchat','0');
					$xml->add_tag('specialchat','0');
					$xml->close_group();
                    unset($chatids_array["$chatid"]);
                }
            }
        }

        // Lowest chatid before update
        if (sizeof($chatids_array))
        {
        	$current_lowestchatid = min($chatids_array);
		}
		else
		{
			$current_lowestchatid = 0;
		}

		if(is_array($chatbits_array))
		{
			uasort($chatbits_array,'array_compare_dateline');

			foreach($chatbits_array AS $index => $tab)
			{
				if (empty($tab['chat_cols']['col_uname']))
				{
					$tab['chat_cols']['col_uname'] = "&nbsp;";
				}
			
				if ($tab['edited'])
				{
					$xml->add_group('chat');
					$xml->add_tag('type','1');
					$xml->add_tag('chatid',$tab['chatid']);
					$xml->add_tag('sticky',$tab['sticky']);
					$xml->add_tag('oldchat','0');
					$xml->add_tag('specialchat',$tab['specialchat']);
					if ($status == "small")
					{
						$templater=vB_Template::create('mgc_cb_evo_chatbit_small_vb4');
						$templater->register('tab',$tab);
						$chatbit = $templater->render();						
						$xml->add_tag('chatbit',$chatbit);
					}
					else
					{
						$xml->add_tag('col_avatar',$tab['chat_cols']['col_avatar']);
						$xml->add_tag('col_chat',$tab['chat_cols']['col_chat']);
						$xml->add_tag('col_date',$tab['chat_cols']['col_date']);
						$xml->add_tag('col_menu',$tab['chat_cols']['col_menu']);
						$xml->add_tag('col_sticky',$tab['chat_cols']['col_sticky']);
						$xml->add_tag('col_atusername',$tab['chat_cols']['col_atusername']);
						$xml->add_tag('col_uname',$tab['chat_cols']['col_uname']);
					}
					$xml->close_group();
				}
				else
				{
					// If chat is an older chat added due to other chat removal
					if (!$skip_older_check && !in_array($tab['chatid'],$chatids_array) && ($tab['dateline'] < $mintime))
					{
						$xml->add_group('chat');
						$xml->add_tag('type','0');
						$xml->add_tag('chatid',$tab['chatid']);
						$xml->add_tag('sticky',$tab['sticky']);
						$xml->add_tag('oldchat','1');
						$xml->add_tag('specialchat',$tab['specialchat']);
						if ($status == "small")
						{
							$templater=vB_Template::create('mgc_cb_evo_chatbit_small_vb4');
							$templater->register('tab',$tab);
							$chatbit = $templater->render();						
							$xml->add_tag('chatbit',$chatbit);
						}
						else
						{
							$xml->add_tag('col_avatar',$tab['chat_cols']['col_avatar']);
							$xml->add_tag('col_chat',$tab['chat_cols']['col_chat']);
							$xml->add_tag('col_date',$tab['chat_cols']['col_date']);
							$xml->add_tag('col_menu',$tab['chat_cols']['col_menu']);
							$xml->add_tag('col_sticky',$tab['chat_cols']['col_sticky']);
							$xml->add_tag('col_atusername',$tab['chat_cols']['col_atusername']);
							$xml->add_tag('col_uname',$tab['chat_cols']['col_uname']);
						}
						$xml->close_group();
					}
					else
					{
						$xml->add_group('chat');
						$xml->add_tag('type','0');
						$xml->add_tag('chatid',$tab['chatid']);
						$xml->add_tag('sticky',$tab['sticky']);
						$xml->add_tag('oldchat','0');
						$xml->add_tag('specialchat',$tab['specialchat']);
						if ($status == "small")
						{
							$templater=vB_Template::create('mgc_cb_evo_chatbit_small_vb4');
							$templater->register('tab',$tab);
							$chatbit = $templater->render();						
							$xml->add_tag('chatbit',$chatbit);
						}
						else
						{
							$xml->add_tag('col_avatar',$tab['chat_cols']['col_avatar']);
							$xml->add_tag('col_chat',$tab['chat_cols']['col_chat']);
							$xml->add_tag('col_date',$tab['chat_cols']['col_date']);
							$xml->add_tag('col_menu',$tab['chat_cols']['col_menu']);
							$xml->add_tag('col_sticky',$tab['chat_cols']['col_sticky']);
							$xml->add_tag('col_atusername',$tab['chat_cols']['col_atusername']);
							$xml->add_tag('col_uname',$tab['chat_cols']['col_uname']);
						}
						$xml->close_group();
					}
				}
			}
		}

        // Notification separated from chats - Let's manage them
        if ($vbulletin->options['mgc_cb_evo_separate_special_chats'])
        {
			if (sizeof($schatids_array))
			{
				// Remove no more used notifications if received array is not empty
				foreach($schatids_array AS $index => $chatid)
				{
					if (!in_array($chatid,$new_schatids_array))
					{
						$xml->add_group('chat');
						$xml->add_tag('type','3');
						$xml->add_tag('chatid',$chatid);
						$xml->add_tag('sticky','0');
						$xml->add_tag('oldchat','0');
						$xml->add_tag('specialchat','1');
						$xml->close_group();
						unset($schatids_array["$chatid"]);
					}
				}
			}

			// Lowest notification chatid before update
			if (sizeof($schatids_array))
			{
				$current_lowestschatid = min($schatids_array);
			}
			else
			{
				$current_lowestschatid = 0;
			}
        }

		if(is_array($schatbits_array))
		{
			uasort($schatbits_array,'array_compare_dateline');

			foreach($schatbits_array AS $index => $tab)
			{
				if ($tab['edited'])
				{
					$xml->add_group('chat');
					$xml->add_tag('type','1');
					$xml->add_tag('chatid',$tab['chatid']);
					$xml->add_tag('sticky',$tab['sticky']);
					$xml->add_tag('oldchat','0');
					$xml->add_tag('specialchat',$tab['specialchat']);
					$xml->add_tag('col_avatar',$tab['chat_cols']['col_avatar']);
					$xml->add_tag('col_chat',$tab['chat_cols']['col_chat']);
					$xml->add_tag('col_date',$tab['chat_cols']['col_date']);
					$xml->add_tag('col_menu',$tab['chat_cols']['col_menu']);
					$xml->add_tag('col_sticky',$tab['chat_cols']['col_sticky']);
					$xml->add_tag('col_uname',$tab['chat_cols']['col_uname']);
					$xml->add_tag('col_atusername',$tab['chat_cols']['col_atusername']);
					$xml->close_group();
				}
				else
				{
					// If chat is an older chat added due to other chat removal
					if (!$skip_solder_check && !in_array($tab['chatid'],$schatids_array) && ($tab['dateline'] < $smintime))
					{
						$xml->add_group('chat');
						$xml->add_tag('type','0');
						$xml->add_tag('chatid',$tab['chatid']);
						$xml->add_tag('sticky',$tab['sticky']);
						$xml->add_tag('oldchat','1');
						$xml->add_tag('specialchat',$tab['specialchat']);
						$xml->add_tag('col_avatar',$tab['chat_cols']['col_avatar']);
						$xml->add_tag('col_chat',$tab['chat_cols']['col_chat']);
						$xml->add_tag('col_date',$tab['chat_cols']['col_date']);
						$xml->add_tag('col_menu',$tab['chat_cols']['col_menu']);
						$xml->add_tag('col_sticky',$tab['chat_cols']['col_sticky']);
						$xml->add_tag('col_uname',$tab['chat_cols']['col_uname']);
						$xml->add_tag('col_atusername',$tab['chat_cols']['col_atusername']);
						$xml->close_group();
					}
					else
					{
						$xml->add_group('chat');
						$xml->add_tag('type','0');
						$xml->add_tag('chatid',$tab['chatid']);
						$xml->add_tag('sticky',$tab['sticky']);
						$xml->add_tag('oldchat','0');
						$xml->add_tag('specialchat',$tab['specialchat']);
						$xml->add_tag('col_avatar',$tab['chat_cols']['col_avatar']);
						$xml->add_tag('col_chat',$tab['chat_cols']['col_chat']);
						$xml->add_tag('col_date',$tab['chat_cols']['col_date']);
						$xml->add_tag('col_menu',$tab['chat_cols']['col_menu']);
						$xml->add_tag('col_sticky',$tab['chat_cols']['col_sticky']);
						$xml->add_tag('col_uname',$tab['chat_cols']['col_uname']);
						$xml->add_tag('col_atusername',$tab['chat_cols']['col_atusername']);
						$xml->close_group();
					}
				}
			}
		}
    }
    else
    {
		$xml->add_group('chat');
		$xml->add_tag('type','2');
		$xml->add_tag('chatid',$chatid);
		$xml->add_tag('sticky','0');
		$xml->add_tag('oldchat','0');
		$xml->add_tag('specialchat','0');
		$xml->close_group();
    }

    $xml->close_group();
    $xml->print_xml();
}

// Chatbox chat sending
if ($_POST['action'] == 'ajax_chat')
{
    $xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');

    if ($MGCCbEvoCore->evo_permissions->can_view() && $MGCCbEvoCore->evo_permissions->can_use())
    {
    	// Update chatbox session
        if ($vbulletin->options['mgc_cb_evo_whoisonline_active'])
        {
            $vbulletin->db->query_write("REPLACE INTO " . TABLE_PREFIX . "mgc_cb_evo_session SET userid='" . $vbulletin->userinfo['userid'] . "',dateline='" . TIMENOW . "'");
        }

        // Retrieval of the message
        $chat		= convert_urlencoded_unicode(urldecode($vbulletin->input->clean_gpc('p', 'chat', TYPE_NOCLEAN)));
        $channel_id	= $vbulletin->input->clean_gpc('p', 'channel_id', TYPE_INT);

        // Retrieval of editor conf
        $b 			= $vbulletin->input->clean_gpc('p', 'b', TYPE_INT);
        $i 			= $vbulletin->input->clean_gpc('p', 'i', TYPE_INT);
        $u 			= $vbulletin->input->clean_gpc('p', 'u', TYPE_INT);
        $size 		= $vbulletin->input->clean_gpc('p', 'size', TYPE_INT);
        $font 		= $vbulletin->input->clean_gpc('p', 'font', TYPE_STR);
        $color		= $vbulletin->input->clean_gpc('p', 'color', TYPE_STR);

		$chat_params = array(
			'mgc_cb_evo_font' 			=> $font,
			'mgc_cb_evo_size' 			=> $size,
			'mgc_cb_evo_color' 			=> substr($color,1),
			'mgc_cb_evo_b' 				=> $b,
			'mgc_cb_evo_u' 				=> $u,
			'mgc_cb_evo_i' 				=> $i,
			'mgc_cb_evo_show' 			=> $vbulletin->userinfo['mgc_cb_evo_show'],
			'mgc_cb_evo_uchanid' 		=> $vbulletin->userinfo['mgc_cb_evo_uchanid'],
			'mgc_cb_evo_sound_disable' 	=> $vbulletin->userinfo['mgc_cb_evo_sound_disable']
		);
		
		($hook = vBulletinHook::fetch_hook('mgc_cb_evo_ajax_chat_start')) ? eval($hook) : false;
		
		$vbulletin->userinfo['mgc_cb_evo_params'] = serialize($chat_params);

        if ($vbulletin->options['mgc_cb_evo_keep_user_format'])
        {
        	// Save user chat formatting
			$serialize_cb_params = $vbulletin->db->escape_string($vbulletin->userinfo['mgc_cb_evo_params']);
			
			$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "user SET mgc_cb_evo_params='$serialize_cb_params' WHERE userid='" . $vbulletin->userinfo['userid'] . "'");
			$MGCCbEvoCore->unserialize_user_params();
        }

        // Retrieval of the username for anonymous if needed (option active and usergroupid = 1)
        if ($vbulletin->options['mgc_cb_evo_ask_anonymous_name'] && $vbulletin->userinfo['usergroupid'] == 1)
        {
        	$chat_name = $vbulletin->input->clean_gpc('p', 'chat_name', TYPE_NOHTML);
        }

        // Message processing
        $chat_datam = &datamanager_init('Mgccb_Chat', $vbulletin, ERRTYPE_ARRAY,'mgccbchat');

        if ($_SERVER['HTTP_X_FORWARD_FOR'])
        {
            $ip = $_SERVER['HTTP_X_FORWARD_FOR'];
        }
        else
        {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        $chat_datam->do_set('userip', $ip);
        
        $chat_datam->do_set('chanid', $channel_id);
        
        if ($vbulletin->options['mgc_cb_evo_ask_anonymous_name'] && $vbulletin->userinfo['usergroupid'] == 1)
        {
			$chat_datam->do_set('name',$chat_name);
        }
        else
        {
			$chat_datam->do_set('name',$vbulletin->userinfo['username']);
        }
        
        // Check if user can use bbcodes
        if (!$MGCCbEvoCore->evo_permissions->can_use_bbcodes())
        {
        	$chat_datam->strip_bbcode = 1;
        }
        
        $chat_datam->retrieve_command_permissions();
        $chat_datam->set_raw_chat($chat);
      	$chat_datam->pre_save();
		
		include_once "/var/www/vhosts/vw-r-club.de/httpdocs/vw-r-club-member-app/chatposttoparse.php";

      	// Chat is a bot trigger and is supposed to hide trigger
      	if ($chat_datam->only_bot_answer)
      	{
	      	$chat_datam->insert_bot_answer();
	      	$xml->add_tag('sendchat_result',"MGCCbEvoNS.chatbox_refresh('forced')");
      	}
        // The pre-save has returned an action (no chat), no need to go further, just return the action
        else if (!empty($chat_datam->action))
        {
            $xml->add_tag('sendchat_result', $chat_datam->action);
        }
        else
        {
			// If user can do limited number of chats per day => checks if not reached
			if ($MGCCbEvoCore->evo_permissions->get_chats_perday_limit())
			{
				$yesterday = TIMENOW - (24 * 60 * 60);
				$getnbchats = $vbulletin->db->query_first("
					SELECT COUNT(chatid) AS nbchats
					FROM " . TABLE_PREFIX . "mgc_cb_evo_chat
					WHERE dateline>='" . $yesterday . "' AND fromuid='" . $vbulletin->userinfo['userid'] . "'
				");

				// Max number of chats reached => error
				if ($getnbchats['nbchats'] >= $MGCCbEvoCore->evo_permissions->get_chats_perday_limit())
				{
					// Compute delay before user can send new chats
					$getoldestchattoday = $vbulletin->db->query_first("
						SELECT dateline FROM " . TABLE_PREFIX . "mgc_cb_evo_chat
						WHERE dateline>='" . $yesterday . "' AND fromuid='" . $vbulletin->userinfo['userid'] . "'
						ORDER BY dateline ASC
						LIMIT 1
					");
					$nextchattime 	= 86400 - (TIMENOW - $getoldestchattoday['dateline']);
					$hours 			= floor($nextchattime / 3600);
					$nextchattime 	= $nextchattime % 3600;
					$minutes		= floor($nextchattime / 60);
					$seconds		= $nextchattime % 60;
					
					// Construct error message
					$error_msg = construct_phrase($vbphrase['mgc_cb_evo_too_much_chats_today'],$hours,$minutes,$seconds);
	            	$xml->add_tag('sendchat_result',"MGCCbEvoNS.hide_editor(\"" . addslashes_js($vbphrase['mgc_cb_evo_msg_max_numberchatsperday_reached']) . "\"); MGCCbEvoNS.show_dialog('" . addslashes_js($error_msg) . "','');");
				}
				else
				{
					$chat_datam->save();

					// With this chat => max number of chats per day reached insert it but send message error to the user
					if (($getnbchats['nbchats'] + 1) == $MGCCbEvoCore->evo_permissions->get_chats_perday_limit())
					{
						// Compute delay before user can send new chats
						$getoldestchattoday = $vbulletin->db->query_first("
							SELECT dateline
							FROM " . TABLE_PREFIX . "mgc_cb_evo_chat
							WHERE dateline>='" . $yesterday . "'
							ORDER BY dateline ASC
							LIMIT 1
						");
						$nextchattime 	= 86400 - (TIMENOW - $getoldestchattoday['dateline']);
						$hours 			= floor($nextchattime / 3600);
						$nextchattime 	= $nextchattime % 3600;
						$minutes		= floor($nextchattime / 60);
						$seconds		= $nextchattime % 60;

						// Construct error message
						$error_msg = construct_phrase($vbphrase['mgc_cb_evo_too_much_chats_today'],$hours,$minutes,$seconds);
						$xml->add_tag('sendchat_result',"MGCCbEvoNS.hide_editor(\"" . addslashes_js($vbphrase['mgc_cb_evo_msg_max_numberchatsperday_reached']) . "\"); MGCCbEvoNS.show_dialog('" . $vbphrase['mgc_cb_evo_cmd_del_success'] ."','MGCCbEvoNS.chatbox_refresh(\'forced\')');");
	            	}
	            	else
	            	{
	            		$xml->add_tag('sendchat_result',"MGCCbEvoNS.chatbox_refresh('forced')");
	            	}
				}
			}
			else
			{
				$chat_datam->save();
	            $xml->add_tag('sendchat_result',"MGCCbEvoNS.chatbox_refresh('forced')");
			}
			
			// Check if bot answer needed
			if (!empty($chat_datam->bot_answer))
			{
				$chat_datam->insert_bot_answer();
			}
        }
    } else {
        $xml->add_tag('sendchat_result','MGCCbEvoNS.force_page_refresh()');
    }
    $xml->print_xml();
}

// Chatbox chat editing retrieval
if ($_POST['action'] == 'ajax_edit_getchat')
{
    $xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');

    if ($MGCCbEvoCore->evo_permissions->can_view() && $MGCCbEvoCore->evo_permissions->can_use())
    {
        $chatid = $vbulletin->input->clean_gpc('p', 'chatid', TYPE_INT);

        // User can manage all chats
        if ($MGCCbEvoCore->evo_permissions->can_manage())
        {
            $chat = $vbulletin->db->query_first("SELECT chatid,ctext FROM " . TABLE_PREFIX . "mgc_cb_evo_chat WHERE chatid='$chatid'");

            // Something retrieved ?
            if ($chat)
            {
                $xml->add_tag('chat_content',$chat['ctext']);
            }
            else
            {
                $xml->add_tag('chat_content', '');
            }
        }
        else
        {
            if ($MGCCbEvoCore->evo_permissions->can_manage_own_chats())
            {
                // Let's check if user is the author
                $chat = $vbulletin->db->query_first("
                	SELECT chatid,ctext
                	FROM " . TABLE_PREFIX . "mgc_cb_evo_chat
                	WHERE chatid='$chatid' AND fromuid='" . $vbulletin->userinfo['userid'] . "'
                ");

                // Something retrieved ?
                if ($chat)
                {
                    $xml->add_tag('chat_content',$chat['ctext']);
                }
                else
                {
                    $xml->add_tag('chat_content', '');
                }
            }
        }
    }
    else
    {
        $xml->add_tag('chat_content', '');
    }
    $xml->print_xml();
}

// Chatbox chat editing execution
if ($_POST['action'] == 'ajax_save_edit')
{
    $xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');

    if ($MGCCbEvoCore->evo_permissions->can_view() && $MGCCbEvoCore->evo_permissions->can_use())
    {
        $vbulletin->input->clean_array_gpc('p', array('chat' => TYPE_NOCLEAN, 'chatid' => TYPE_UINT));

		$vbulletin->GPC['chat'] = convert_urlencoded_unicode(urldecode($vbulletin->GPC['chat']));
		
		($hook = vBulletinHook::fetch_hook('mgc_cb_evo_ajax_save_edit_start')) ? eval($hook) : false;

        // No chat => error
        if ((empty($vbulletin->GPC['chat'])) || (trim($vbulletin->GPC['chat']) == ""))
        {
            $xml->add_tag('edit_result',"MGCCbEvoNS.show_dialog('$vbphrase[mgc_cb_evo_edit_failed]','');");
        }
        else
        {
            // User can manage all chats
           	if ($MGCCbEvoCore->evo_permissions->can_manage())
            {
                $vbulletin->db->query_write("
					UPDATE " . TABLE_PREFIX . "mgc_cb_evo_chat
					SET ctext='" . $vbulletin->db->escape_string(trim($vbulletin->GPC['chat'])) . "',editdate='" . TIMENOW . "'
					WHERE chatid='" . $vbulletin->GPC['chatid'] . "'");

                $xml->add_tag('edit_result', 1);
            }
            else
            {
                if ($MGCCbEvoCore->evo_permissions->can_manage_own_chats())
                {
                    // Let's check if user is the author
                    $chat = $vbulletin->db->query_first("
                    	SELECT chatid,ctext
                    	FROM " . TABLE_PREFIX . "mgc_cb_evo_chat
                    	WHERE chatid='" . $vbulletin->GPC['chatid'] . "' AND fromuid='" . $vbulletin->userinfo['userid'] . "'
                    ");

                    if ($chat)
                    {
						$vbulletin->db->query_write("
							UPDATE " . TABLE_PREFIX . "mgc_cb_evo_chat
							SET ctext='" . $vbulletin->db->escape_string(trim($vbulletin->GPC['chat'])) . "',editdate='" . TIMENOW . "'
  							WHERE chatid='" . $vbulletin->GPC['chatid'] . "'
  						");

                        $xml->add_tag('edit_result', 1);
                    }
                    else
                    {
                        $xml->add_tag('edit_result',"MGCCbEvoNS.show_dialog('$vbphrase[mgc_cb_evo_edit_forbidden]','');");
                    }
                }
                else
                {
                    $xml->add_tag('edit_result',"MGCCbEvoNS.show_dialog('$vbphrase[mgc_cb_evo_edit_forbidden]','');");
                }
            }
        }
    }
    $xml->print_xml();
}

// Chatbox channel statuses retrieving
if ($_POST['action'] == 'ajax_check_messages')
{
    $xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');

    if ($MGCCbEvoCore->evo_permissions->can_view() && $MGCCbEvoCore->evo_permissions->can_use())
    {
        $channel_id = $vbulletin->input->clean_gpc('p', 'channel_id', TYPE_INT);

		// Unserialize user last activites in channels
        $channel_activities = unserialize($vbulletin->userinfo['mgc_cb_evo_channel_activities']);
        
        // Construct query
		$channel_ids = '';
		if (is_array($vbulletin->mgc_cb_evo_channels))
		{					
			foreach($vbulletin->mgc_cb_evo_channels AS $channel)
			{
				if (!empty($channel['usergroupids']))
				{
					$channel['usergroupids'] = explode(',',$channel['usergroupids']);
				}
				else
				{
					$channel['usergroupids'] = array();
				}
				
				if (!empty($channel['userids']))
				{
					$channel['userids'] = explode(',',$channel['userids']);
				}	
				else
				{
					$channel['userids'] = array();
				}
									
				// Channel not active => next
				if ($channel['active'] == 0)
				{
					continue;
				}
				
				// Test if user has permissions
				$hasaccess = 0;		
				// 1 - Usergroupid test
				if (is_array($channel['usergroupids']) && in_array($vbulletin->userinfo['usergroupid'],$channel['usergroupids']))
				{
					$hasaccess = 1;
				}
				// 2 - Member group test
				if (!$hasaccess && !empty($vbulletin->userinfo['membergroupids']))
				{
					$found 			= 0;
					$ugipds_array 	= explode(',', $vbulletin->userinfo['membergroupids']);		
					foreach ($ugipds_array as $index => $ugpid)
					{
						if (in_array($ugpid,$channel['usergroupids']))
						{
							$hasaccess = 1;
						}
					}
				}
				// 3 - Userid test
				if (!$hasaccess && is_array($channel['userids']) && in_array($vbulletin->userinfo['userid'],$channel['userids']))
				{
					$hasaccess = 1;
				}
				// 4 - Skip channel if not
				if (!$hasaccess)
				{
					continue;
				}
				
				// If warn active in this channel add it
				if ($channel['warnon'])
				{
					$channel_ids[] = $channel['chanid'];
				}
			}
		}

		if ($channel_id && $vbulletin->options['mgc_cb_evo_channels_warning_gen'])
		{
			$channel_ids[] = 0;
		}
		
		if (is_array($channel_ids))
		{
			$sql_condition_ch = " AND chanid IN(" . implode(',',$channel_ids) . ")";
		}
		else
		{
			$sql_condition_ch = "";
		}
		
		$command_ids = array();
		$command_ids[] = 0;
		
		if (is_array($vbulletin->mgc_cb_evo_commands))
		{					
			foreach($vbulletin->mgc_cb_evo_commands AS $command)
			{
				if (!empty($command['usergroupids']))
				{
					$command['usergroupids'] = explode(',',$command['usergroupids']);
				}
				else
				{
					$command['usergroupids'] = array();
				}
				
				if (!empty($command['userids']))
				{
					$command['userids'] = explode(',',$command['userids']);
				}	
				else
				{
					$command['userids'] = array();
				}
									
				// Channel not active => next
				if ($command['active'] == 0)
				{
					continue;
				}
				
				// Test if user has permissions
				$hasaccess = 0;		
				// 1 - Usergroupid test
				if (is_array($command['usergroupids']) && in_array($vbulletin->userinfo['usergroupid'],$command['usergroupids']))
				{
					$hasaccess = 1;
				}
				// 2 - Member group test
				if (!$hasaccess && !empty($vbulletin->userinfo['membergroupids']))
				{
					$found 			= 0;
					$ugipds_array 	= explode(',', $vbulletin->userinfo['membergroupids']);		
					foreach ($ugipds_array as $index => $ugpid)
					{
						if (in_array($ugpid,$command['usergroupids']))
						{
							$hasaccess = 1;
						}
					}
				}
				// 3 - Userid test
				if (!$hasaccess && is_array($command['userids']) && in_array($vbulletin->userinfo['userid'],$command['userids']))
				{
					$hasaccess = 1;
				}
				// 4 - Skip channel if not
				if (!$hasaccess)
				{
					continue;
				}
				
				$command_ids[] = "'" . $command['identifier'] . "'";
			}
		}	
		
		$sql_condition_co = "coidentifier IN(" . implode(',',$command_ids) . ")";	

		// Get the last message dateline of each channels user has access to
        $getchannelslastmessage = $vbulletin->db->query_read("
			SELECT MAX(dateline) AS cdate,c.chanid
			FROM " . TABLE_PREFIX . "mgc_cb_evo_chat AS c
			WHERE
				$sql_condition_co  $sql_condition_ch
				AND
				(
					fromuid<>'0'
					AND
					(
						coidentifier<>'pm'
						OR
						(
							fromuid='" . $vbulletin->userinfo['userid'] . "'
							OR touid='" . $vbulletin->userinfo['userid'] . "'
						)
					)
				)
			GROUP BY chanid
			ORDER BY dateline DESC
		");

        $xml->add_group('channels_statuses');

		// Construct the xml response statuses
        if ($vbulletin->db->num_rows($getchannelslastmessage))
        {
            while ($channellastmessage = $vbulletin->db->fetch_array($getchannelslastmessage))
            {
                if (empty($channellastmessage['chanid']))
                {
                    $channellastmessage['chanid'] = 0;
                }

                if (array_key_exists($channellastmessage['chanid'],$channel_activities))
                {
                    if ($channellastmessage['cdate'] > $channel_activities["$channellastmessage[chanid]"])
                    {
                        $xml->add_tag('status', 1, array('chanid' => $channellastmessage['chanid']));
                    }
                    else
                    {
                        $xml->add_tag('status', 0, array('chanid' => $channellastmessage['chanid']));
                    }
                }
                else
                {
                    $xml->add_tag('status', 1, array('chanid' => $channellastmessage['chanid']));
                }
            }
        }
        else
        {
            $xml->add_tag('status','none');
        }
        $xml->close_group();
    }
    $xml->print_xml();
}

if ($_POST['action'] == 'ajax_get_online_users')
{
    $xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
    
    if ($MGCCbEvoCore->evo_permissions->can_view() && $MGCCbEvoCore->evo_permissions->can_use())
    {
        // Inactive mode active and delay < session delay => inactive mode delay taken
        if ($vbulletin->options['mgc_cb_evo_inactive_mode'] && $vbulletin->options['mgc_cb_evo_inactive_mode_delay'] < $vbulletin->options['mgc_cb_evo_whoisonline_delay']) 
		{
            $dateline = TIMENOW - ($vbulletin->options['mgc_cb_evo_inactive_mode_delay'] * 60);
        }
        else
        {
            $dateline = TIMENOW - ($vbulletin->options['mgc_cb_evo_whoisonline_delay'] * 60);
        }

        $getusers = $vbulletin->db->query_read("
			SELECT DISTINCT u.username,u.displaygroupid,u.userid,s.userid AS suserid,u.usergroupid,(u.options & " . $vbulletin->bf_misc_useroptions['invisible'] . ") AS invisible
			FROM " . TABLE_PREFIX . "mgc_cb_evo_session AS cbs
			LEFT JOIN " . TABLE_PREFIX . "user AS u ON (cbs.userid=u.userid)
			LEFT JOIN " . TABLE_PREFIX . "session AS s ON (cbs.userid=s.userid)
			WHERE cbs.dateline>='$dateline' AND cbs.userid!=0
		");

        if ($vbulletin->db->num_rows($getusers))
        {
            $users_online['nb'] = 0;
            $first = 1;
            while ($user = $vbulletin->db->fetch_array($getusers))
            {
                if ($user['invisible'])
                {
                    if ($MGCCbEvoCore->evo_permissions->can_see_hidden_users() OR $user['userid'] == $vbulletin->userinfo['userid'])
                    {
                        if ($user['suserid'])
                        {
                            $users_online['nb']++;
                            if (!$first)
                            {
                                $users_online['list'] .= ', ';
                            }
                            else
                            {
                                $first = 0;
                            }
                            $users_online['list'] .= '<a href="member.php?' . $session['sessionurl'] . 'u=' . $user['userid'] . '">' . fetch_musername($user,'displaygroupid') . '</a>*';
                        }
                    }
                }
                else
                {
                    if ($user['suserid'])
                    {
                        $users_online['nb']++;
                        if (!$first)
                        {
                            $users_online['list'] .= ', ';
                        }
                        else
                        {
                            $first = 0;
                        }
                        $users_online['list'] .= '<a href="member.php?' . $session['sessionurl'] . 'u=' . $user['userid'] . '">' . fetch_musername($user,'displaygroupid') . '</a>';
                    }
                }
            }
            $xml->add_tag('online',$users_online['list'],array('nb' => $users_online['nb']));
        }
        else
        {
            $xml->add_tag('online',"",array('nb' => 0));
        }
    }
    $xml->print_xml();
}

// Chatbox chat stickying
if ($_POST['action'] == 'ajax_sticky_chat')
{
    $xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');

    if ($MGCCbEvoCore->evo_permissions->can_manage())
    {
        $vbulletin->input->clean_array_gpc('p', array('status' => TYPE_UINT, 'chatid' => TYPE_UINT));
        // Let's sticky or unsticky chat
        $vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "mgc_cb_evo_chat SET sticky='" . $vbulletin->GPC['status'] . "' WHERE chatid='" . $vbulletin->GPC['chatid'] . "'");
        if ($vbulletin->GPC['status'])
        {
            $xml->add_tag('stiky_result',"MGCCbEvoNS.show_dialog('$vbphrase[mgc_cb_evo_chat_stickied]','MGCCbEvoNS.force_page_refresh');");
        }
        else
        {
            $xml->add_tag('stiky_result',"MGCCbEvoNS.show_dialog('$vbphrase[mgc_cb_evo_chat_unstickied]','MGCCbEvoNS.force_page_refresh');");
        }
    }
    $xml->print_xml();
}

// Chatbox chat removal
if ($_POST['action'] == 'ajax_delete_chat')
{
    $xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');

	$vbulletin->input->clean_array_gpc('p', array('chatid' => TYPE_UINT));
	
	// Get chatinfo
	$chat = $vbulletin->db->query_first("
		SELECT chatid,username,ctext,fromuid
		FROM " . TABLE_PREFIX . "mgc_cb_evo_chat AS c
		LEFT JOIN " . TABLE_PREFIX . "user AS u ON (c.fromuid=u.userid)
		WHERE chatid='" . $vbulletin->GPC['chatid'] . "'
	");	

    if ($MGCCbEvoCore->evo_permissions->can_manage() || (($vbulletin->userinfo['userid'] == $chat['fromuid']) && $MGCCbEvoCore->evo_permissions->can_manage_own_chats()))
    {
    	// Delete chat
        $vbulletin->db->query_write("DELETE FROM " . TABLE_PREFIX . "mgc_cb_evo_chat WHERE chatid='" . $vbulletin->GPC['chatid'] . "'");
        
        // Log action
        $log_action = construct_phrase($vbphrase['mgc_cb_evo_log_del_chat_of_x_with_text_y'],$chat['username'],$chat['ctext']);
        
		$vbulletin->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "mgc_cb_evo_log
			SET dateline='" . TIMENOW . "',username='" . $vbulletin->db->escape_string($vbulletin->userinfo['username']) . "',ltext='" . $vbulletin->db->escape_string($log_action) . "'
		");
        
        // Construct result
		$xml->add_tag('answer_result',"MGCCbEvoNS.show_dialog('$vbphrase[mgc_cb_evo_cmd_del_success]','MGCCbEvoNS.chatbox_refresh(\'forced\')');");
    }
    else
    {
		$xml->add_tag('answer_result',"MGCCbEvoNS.show_dialog('$vbphrase[mgc_cb_evo_cmd_del_nopermission]','MGCCbEvoNS.chatbox_refresh(\'forced\')');");
    }
    
    $xml->print_xml();
}

// Retrieves PM chats
if ($_POST['action'] == 'ajax_get_pms')
{
	// First check permissions to use the PM command
	$hasaccess = 0;
	
	$command = $vbulletin->db->query_first("SELECT active,usergroupids,userids FROM " . TABLE_PREFIX . "mgc_cb_evo_command WHERE identifier='pm'");
	
	if (!empty($command['usergroupids']))
	{
		$command['usergroupids'] = explode(',',$command['usergroupids']);
	}
	else
	{
		$command['usergroupids'] = array();
	}
	
	if (!empty($command['userids']))
	{
		$command['userids'] = explode(',',$command['userids']);
	}	
	else
	{
		$command['userids'] = array();
	}
						
	// Channel not active => next
	if ($command['active'])
	{	
		// 1 - Usergroupid test
		if (is_array($command['usergroupids']) && in_array($vbulletin->userinfo['usergroupid'],$command['usergroupids']))
		{
			$hasaccess = 1;
		}
		// 2 - Member group test
		if (!$hasaccess && !empty($vbulletin->userinfo['membergroupids']))
		{
			$found 			= 0;
			$ugipds_array 	= explode(',', $vbulletin->userinfo['membergroupids']);		
			foreach ($ugipds_array as $index => $ugpid)
			{
				if (in_array($ugpid,$command['usergroupids']))
				{
					$hasaccess = 1;
				}
			}
		}
		// 3 - Userid test
		if (!$hasaccess && is_array($command['userids']) && in_array($vbulletin->userinfo['userid'],$command['userids']))
		{
			$hasaccess = 1;
		}
	}
	
	if ($hasaccess)
	{
		$vbulletin->input->clean_array_gpc('p', array('lastchatid' => TYPE_UINT, 'tabuserid' => TYPE_UINT));

		// Compute edit offset
		$editoffset = TIMENOW - $vbulletin->options['mgc_cb_evo_cmd_pm_tab_refresh_rate'];		
		
		if ($vbulletin->GPC['lastchatid'] == 0)
		{
			// New opened tab, retrieves last chats
			$chats = $vbulletin->db->query_read("
				 SELECT c.*
				 FROM " . TABLE_PREFIX . "mgc_cb_evo_chat AS c
				 WHERE
				 	coidentifier='pm'
				 	AND (
				 		(fromuid='" . $vbulletin->userinfo['userid'] . "' AND touid='" . $vbulletin->GPC['tabuserid'] . "')
				 		OR
				 		(fromuid='" . $vbulletin->GPC['tabuserid'] . "' AND touid='" . $vbulletin->userinfo['userid'] . "')
				 	)
				 ORDER BY c.chatid DESC
				 LIMIT " . $vbulletin->options['mgc_cb_evo_cmd_pm_tabs_nbchats_start']
			);
		}
		else
		{
			// Refresh retrieves chats since last tab refresh
			$chats = $vbulletin->db->query_read("
				 SELECT c.*
				 FROM " . TABLE_PREFIX . "mgc_cb_evo_chat AS c
				 WHERE
				 	coidentifier='pm'
				 	AND (
				 		(fromuid='" . $vbulletin->userinfo['userid'] . "' AND touid='" . $vbulletin->GPC['tabuserid'] . "')
				 		OR
				 		(fromuid='" . $vbulletin->GPC['tabuserid'] . "' AND touid='" . $vbulletin->userinfo['userid'] . "')
				 	)
				 	AND (
				 		chatid>'" . $vbulletin->GPC['lastchatid'] . "'
				 		OR
				 		editdate>'$editoffset'
				 	)
				 ORDER BY c.chatid DESC
			");
		}
		
		if ($vbulletin->db->num_rows($chats))
		{
		    $xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
		    $xml->add_group('chats');		
			while($chat = $vbulletin->db->fetch_array($chats))
			{
				// Instantiate formatting class
				require_once(DIR . '/mgc_cb_evo/classes/class_formatting.php');
				$MGCCbEvoFormatting = new MGCCbEvo_formatting($vbulletin,$MGCCbEvoCore);
				$MGCCbEvoFormatting->inpm = 1;
				$parsebbcode = $vbulletin->options['mgc_cb_evo_bbcode'] || $vbulletin->options['mgc_cb_evo_bbcode_url'] || $vbulletin->options['mgc_cb_evo_bbcode_img'];
				$chat_cols = $MGCCbEvoFormatting->construct_chat($chat, $parsebbcode, $commands_status, $channel_id);
				$xml->add_group('chat');
				
				// Chat content
				$xml->add_tag('chatid',$chat['chatid']);
				$xml->add_tag('col_avatar',$chat_cols['col_avatar']);
				$xml->add_tag('col_chat',$chat_cols['col_chat']);
				$xml->add_tag('col_date',$chat_cols['col_date']);
				$xml->add_tag('col_uname',$chat_cols['col_uname']);
				
				// Edited chat ?
				if ($chat['editdate'] > $editoffset)
				{
					$xml->add_tag('edited', 1);
				}
				else
				{
					$xml->add_tag('edited', 0);
				}
				
				$xml->close_group();
			}
			$xml->close_group();
			$xml->print_xml();
		}
		else
		{
		    $xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
		    $xml->add_group('chats');		
			$xml->close_group();
			$xml->print_xml();		
		}
	}
	else
	{
		    $xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
		    $xml->add_group('chats');		
			$xml->close_group();
			$xml->print_xml();		
	}
}

// Chatbox chat report
if ($_POST['action'] == 'ajax_report_chat')
{
    $xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');

	$vbulletin->input->clean_array_gpc('p', array('chatid' => TYPE_UINT,'reason' => TYPE_NOCLEAN));
	
	$chatid 	= $vbulletin->GPC['chatid'];
	$reason 	= $vbulletin->db->escape_string($vbulletin->GPC['reason']);
	$username 	= $vbulletin->db->escape_string($vbulletin->userinfo['username']);
	$date		= TIMENOW;

    if ($MGCCbEvoCore->evo_permissions->can_report())
    {
    	// Get chattext
    	$getchat 	= $vbulletin->db->query_first("SELECT ctext FROM " . TABLE_PREFIX . "mgc_cb_evo_chat WHERE chatid='" . $chatid . "'");
    	$ctext 		= $vbulletin->db->escape_string($getchat['ctext']);
    
		$vbulletin->db->query_write("INSERT INTO " . TABLE_PREFIX . "mgc_cb_evo_report SET chatid='$chatid',username='$username',reason='$reason',dateline='$date',ctext='$ctext'");
		$xml->add_tag('answer_result',"MGCCbEvoNS.show_dialog('$vbphrase[mgc_cb_evo_report_success]','MGCCbEvoNS.chatbox_refresh(\'forced\')');");
    }
    else
    {
		$xml->add_tag('answer_result',"MGCCbEvoNS.show_dialog('$vbphrase[mgc_cb_evo_report_nopermission]','MGCCbEvoNS.chatbox_refresh(\'forced\')');");
    }
    
    $xml->print_xml();
}

// Chatbox display channel authorization retrieval
if ($_POST['action'] == 'ajax_change_channel')
{
    $xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');

    if ($MGCCbEvoCore->evo_permissions->can_view() && $MGCCbEvoCore->evo_permissions->can_use())
    {
        $channel_id = $vbulletin->input->clean_gpc('p', 'channel_id', TYPE_INT);

        // Construct query
		$canviewchannel = 0;
		
		if ($channel_id == 0)
		{
			$canviewchannel = 1;
		}
		else if (is_array($vbulletin->mgc_cb_evo_channels))
		{					
			foreach($vbulletin->mgc_cb_evo_channels AS $channel)
			{
				if ($channel['chanid'] == $channel_id)
				{
					if (!empty($channel['usergroupids']))
					{
						$channel['usergroupids'] = explode(',',$channel['usergroupids']);
					}
					else
					{
						$channel['usergroupids'] = array();
					}
					
					if (!empty($channel['userids']))
					{
						$channel['userids'] = explode(',',$channel['userids']);
					}	
					else
					{
						$channel['userids'] = array();
					}
										
					// Channel not active => next
					if ($channel['active'] == 0)
					{
						continue;
					}
					
					// Test if user has permissions
					$hasaccess = 0;		
					// 1 - Usergroupid test
					if (is_array($channel['usergroupids']) && in_array($vbulletin->userinfo['usergroupid'],$channel['usergroupids']))
					{
						$hasaccess = 1;
					}
					// 2 - Member group test
					if (!$hasaccess && !empty($vbulletin->userinfo['membergroupids']))
					{
						$found 			= 0;
						$ugipds_array 	= explode(',', $vbulletin->userinfo['membergroupids']);		
						foreach ($ugipds_array as $index => $ugpid)
						{
							if (in_array($ugpid,$channel['usergroupids']))
							{
								$hasaccess = 1;
							}
						}
					}
					// 3 - Userid test
					if (!$hasaccess && is_array($channel['userids']) && in_array($vbulletin->userinfo['userid'],$channel['userids']))
					{
						$hasaccess = 1;
					}
					// 4 - Skip channel if not
					if ($hasaccess)
					{
						$canviewchannel = 1;
						break;
					}
				}
			}
		}

        $xml->add_group('status');
		$xml->add_tag('canview', $canviewchannel);
		$xml->add_tag('channel_id', $channel_id);
        $xml->close_group();
    }
    $xml->print_xml();
}


?>