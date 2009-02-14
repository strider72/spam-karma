<?php
/**********************************************************************************************
 Spam Karma (c) 2009 - http://code.google.com/p/spam-karma/

 This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; version 2 of the License.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

************************************************************************************************/
?><?php
/*
Plugin Name: Spam Karma
Plugin URI: http://unknowngenius.com/blog/wordpress/spam-karma/
Description: Ultimate Spam Killer for WordPress.<br/> Activate the plugin and go to <a href="edit.php?page=spamkarma2">Manage >> Spam Karma</a> to configure.<br/> See <a href="edit.php?page=spamkarma2&sk2_section=about">Spam Karma >> About</a> for details.
Author: dr Dave
Version: 2.3
Author URI: http://unknowngenius.com/blog/
 Copyright 2007 - drDave
 
 All rights reserved. You are free to use this software and redistribute it for free but may not include it in any commercial distribution without prior written permission.
 
 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY
KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS
OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR
OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/

define("sk2_table_prefix", $table_prefix);
define("sk_second_chance_file", "sk_second_chance.php");
define("sk_news_update_check_url", "http://wp-plugins.net/sk2/sk2_news.php");
define("sk_news_update_interval",  86400); 
define("sk2_auto_purge_interval",  600);

if (! isset($_SERVER['PHP_SELF']))
	$_SERVER['PHP_SELF'] = @$PHP_SELF;
		
function sk2_add_options() 
{
    add_management_page(__('Spam Karma Options', 'spam-karma'), 'Spam Karma', 7, "spamkarma2", 'sk2_option_page');
	add_options_page(__('Spam Karma Options', 'spam-karma'), 'Spam Karma', 7, "spamkarma2", 'sk2_option_page');
}

function sk2_init ()
{
	$skdir = basename( dirname( __FILE__ ) );
	load_plugin_textdomain( 'sk2',
		'wp-content/plugins/' . $skdir . '/lang',
		$skdir . '/lang' );
}

function sk2_option_page()
{
	global $wpdb, $sk2_settings;
	include_once(dirname(__FILE__) . "/sk_core_class.php");
	$sk2_core = new sk2_core(0, true);

	$sk_sections = array ("general" => __("General Settings", 'spam-karma'), "spam" => __("Recent Spam Harvest", 'spam-karma') . $new_spams, "approved" => __("Approved Comments", 'spam-karma') . $new_approved, "blacklist" => __("Blacklist", 'spam-karma'), "logs" => __("SK2 Logs", 'spam-karma'), "about" => __("About", 'spam-karma'));

	if (isset($_REQUEST['sk2_section']) && !empty($sk_sections[$_REQUEST['sk2_section']]))
		$cur_section = $_REQUEST['sk2_section'];
	else
		$cur_section = "general";

if (isset($_REQUEST) && (!empty($_REQUEST['sk2_section'])
								|| (!empty($_REQUEST['advanced_tools'])) 
								|| (isset($_REQUEST['sk2_settings_save']))))
{
//print_r($_REQUEST);
	if (function_exists('check_admin_referer'))
		check_admin_referer('sk2_form');
}

// FORM HANDLING:
if (isset($_REQUEST['sk2_section']))
{
	if (isset($_REQUEST['sk2_core_settings_ui']) && is_array($_REQUEST['sk2_core_settings_ui']))
	{
		foreach($_REQUEST['sk2_core_settings_ui'] as $name => $value)
		{
			if ($value == "checkbox")
				$value = isset($_REQUEST['sk2_core_settings_checkbox'][$name]);

			$sk2_settings->set_core_settings($value, $name);
		}
	}
	
	if ( (isset($_REQUEST['purge_logs']))
		|| ($sk2_settings->get_core_settings("auto_purge_logs") 
			&& ($sk2_settings->get_core_settings("next_auto_purge_logs") < time())))
	{
		$query = "DELETE FROM  `". sk2_kLogTable . "` WHERE `ts`< DATE_SUB(NOW(), INTERVAL ". $sk2_settings->get_core_settings("purge_logs_duration") . " " . $sk2_settings->get_core_settings("purge_logs_unit") .") AND `level` < "  . $sk2_settings->get_core_settings("purge_logs_level");
		$removed = $wpdb->query($query);
		
		if (! mysql_error())
			$sk2_log->log_msg(sprintf(__ngettext("Successfully purged one log entry.", "Successfully purged %d log entries.", $removed, 'spam-karma'), $removed), 5, 0, "web_UI");
		else
			$sk2_log->log_msg_mysql(__("Failed to purge log entries.", 'spam-karma') . "<br/><code>$query</code>", 7, 0, "web_UI");

		$sk2_settings->set_core_settings(time() + sk2_auto_purge_interval, "next_auto_purge_logs");
	}	
	
	if ( (isset($_REQUEST['purge_blacklist']))
		|| ($sk2_settings->get_core_settings("auto_purge_blacklist") 
			&& ($sk2_settings->get_core_settings("next_auto_purge_blacklist") < time())))
	{
		$query = ("DELETE FROM  `". sk2_kBlacklistTable . "` WHERE `". $sk2_settings->get_core_settings("purge_blacklist_criterion") ."`< DATE_SUB(NOW(), INTERVAL ". $sk2_settings->get_core_settings("purge_blacklist_duration") . " " . $sk2_settings->get_core_settings("purge_blacklist_unit") .") AND `score` < "  . $sk2_settings->get_core_settings("purge_blacklist_score"));
		$removed = $wpdb->query($query);
		
		if (! mysql_error())
			$sk2_log->log_msg(sprintf(__ngettext("Successfully purged one blacklist entry.", "Successfully purged %d blacklist entries.", $removed, 'spam-karma'), $removed), 5, 0, "web_UI");
		else
			$sk2_log->log_msg_mysql(__("Failed to purge blacklist entries.", 'spam-karma'). "<br/>" . __("Query: ", 'spam-karma'). "<code>$query</code>", 7, 0, "web_UI");

		$sk2_settings->set_core_settings(time() + sk2_auto_purge_interval, "next_auto_purge_blacklist");
	}

	if ( (isset($_REQUEST['purge_spamlist']))
		|| ($sk2_settings->get_core_settings("auto_purge_spamlist")
			&& ($sk2_settings->get_core_settings("next_auto_purge_spamlist") < time())))
	{	
		$spam_table = "`" . sk2_kSpamTable . "`";
		$cmt_table = "`$wpdb->comments`";
		$query = "DELETE  $cmt_table, $spam_table FROM $cmt_table LEFT JOIN $spam_table ON $spam_table.`comment_ID` = $cmt_table.`comment_ID` WHERE ($cmt_table.`comment_approved` = '0' OR $cmt_table.`comment_approved` = 'spam') AND $cmt_table.`comment_date_gmt` < DATE_SUB('". $gmt = gmstrftime("%Y-%m-%d %H:%M:%S") ."', INTERVAL ". $sk2_settings->get_core_settings("purge_spamlist_duration") . " " . $sk2_settings->get_core_settings("purge_spamlist_unit") .")";
		$removed = $wpdb->query($query);
		
		if (! mysql_error())
			$sk2_log->log_msg(sprintf(__ngettext("Successfully purged one comment spam entry.", "Successfully purged %d comment spam entries.", $removed, 'spam-karma'), $removed), 5, 0, "web_UI");
		else
			$sk2_log->log_msg_mysql(__("Failed to purge comment spam entries.", 'spam-karma'). "<br/>" . __("Query: ", 'spam-karma'). "<code>$query</code>", 7, 0, "web_UI");

		$sk2_settings->set_core_settings(time() + sk2_auto_purge_interval, "next_auto_purge_spamlist");
	}

	
	if ($cur_section == "approved" || $cur_section == "spam")
	{
		if (isset($_REQUEST['recover_selection']) && isset($_REQUEST['comment_grp_check']))
		{
			foreach($_REQUEST['comment_grp_check'] as $id => $spam_id)
			{
				
				$sk2_core->load_comment($id);
			
				if ($cur_section == 'spam')
				{
					$sk2_core->cur_comment->set_karma(15, 'web_UI', __("Manually recovered comment.", 'spam-karma'));
					do_action('wp_set_comment_status', $sk2_core->cur_comment->ID);
				}
				else
					$sk2_core->cur_comment->set_karma(-30, 'web_UI', __("Manually spanked spam.", 'spam-karma'));
	
				$sk2_core->treat_comment();
				$sk2_core->set_comment_sk_info();			
			
			}
			
			if ($cur_section == 'spam')
				$cur_section = 'approved';
			else
				$cur_section = 'spam';

		}
		elseif (isset($_REQUEST['confirm_moderation']))
		{
			if ($mod_cmts = $wpdb->get_results("SELECT `comment_ID` FROM `$wpdb->comments` WHERE `comment_approved` = '0'"))
				foreach($mod_cmts as $mod_cmt)
				{
					$sk2_core->load_comment($mod_cmt->comment_ID);
					//$sk2_core->cur_comment->set_DB_status('spam', 'web_UI', true);
					$sk2_core->cur_comment->set_karma(-15, 'web_UI', __("Manually confirmed moderations.", 'spam-karma'));
					$sk2_core->treat_comment();
					$sk2_core->set_comment_sk_info();
				}
			else
				$sk2_log->log_msg_mysql(__("Can't fetch moderated comments.", 'spam-karma'), 7, 0, "web_UI");
		}
		elseif (isset($_REQUEST['sk2_run_filter']))
		{
			if (isset($_REQUEST['comment_grp_check']))
			{
				$which_plugin = $_REQUEST['action_param']['which_plugin'];
				
				if ($which_plugin != "all")
				{
					$which_plugin_obj = 0;
					foreach ($sk2_core->plugins as $plugin)
						if ($plugin[2] == $which_plugin)
							$which_plugin_obj = $plugin[1];
	
					if (! $which_plugin_obj)
						$sk2_log->log_msg(__("Cannot find plugin: ", 'spam-karma') . $which_plugin, 10, 0, "web_UI");
				}
				
				foreach($_REQUEST['comment_grp_check'] as $id => $spam_id)
				{
					if ($which_plugin == "all")
					{
						$sk2_log->log_msg(__("Running all filters on comment ID: ", 'spam-karma') . $id, 3, $id, "web_UI");
						$sk2_core->filter_comment($id);				
						$sk2_log->log_msg(__("Running all treatments on comment ID: ", 'spam-karma') .  $id, 3, $id, "web_UI");
						$sk2_core->treat_comment($id);
						$sk2_core->set_comment_sk_info();
					}
					else
					{
						$comment_obj = new sk2_comment($id, true);
						if ($which_plugin_obj->is_filter())
						{
							$sk2_log->log_msg(sprintf(__("Running filter: %s on comment ID: %s", 'spam-karma'), $which_plugin_obj->name, $id), 3, $id, "web_UI");
							$which_plugin_obj->filter_this($comment_obj);
						}
						if ($which_plugin_obj->is_treatment())
						{
							$sk2_log->log_msg(sprintf(__("Running treatment: %s on comment ID %d.", 'spam-karma'),  $which_plugin_obj->name, $id), 3, $id, "web_UI");
							$which_plugin_obj->treat_this($comment_obj);
						}
						$comment_sk_info['comment_ID'] = $id;
						$comment_sk_info['karma'] =  $comment_obj->karma;
						$comment_sk_info['karma_cmts'] =  $comment_obj->karma_cmts;
						$sk2_core->set_comment_sk_info($id, $comment_sk_info);
					}
				}
			}
			else
				$sk2_log->log_msg(__("No comment selected: cannot run plugins.", 'spam-karma'), 6, 0, "web_UI");
		}
		elseif (isset($_REQUEST['remove_checked']) && isset($_REQUEST['comment_grp_check']))
		{
			foreach($_REQUEST['comment_grp_check'] as $id => $spam)
			{
				$id = mysql_escape_string($id);
				if ($wpdb->query("DELETE FROM  `$wpdb->comments` WHERE  `$wpdb->comments`.`comment_ID` = '$id'"))
					$wpdb->query("DELETE FROM `". sk2_kSpamTable . "` WHERE `". sk2_kSpamTable . "`.`comment_ID` = '$id'");
				if (! mysql_error())
					$sk2_log->log_msg(__("Successfully removed spam entry ID: ", 'spam-karma'). $id, 4, 0, "web_UI");
				else
					$sk2_log->log_msg_mysql(__("Failed to remove spam entry ID: ", 'spam-karma') . $id, 7, 0, "web_UI");
			}
		}
	}
}
// SECTION DISPLAY

	$last_spam_check = $sk2_settings->get_core_settings("last_spam_check");
	$new_spams = $wpdb->get_var("SELECT COUNT(*) FROM `$wpdb->comments` WHERE (`comment_approved`= '0' OR `comment_approved` = 'spam') AND `comment_date_gmt` > " . gmstrftime("'%Y-%m-%d %H:%M:%S'", (int) $last_spam_check));
	$cur_moderated = $wpdb->get_var("SELECT COUNT(*) FROM `$wpdb->comments` WHERE `comment_approved`= '0'");	

	if ($new_spams || $cur_moderated)
	{
		if ($new_spams && $cur_moderated)
			$new_spams = " ($new_spams / <span style=\"color:red;\">$cur_moderated</span>)";
		elseif ($new_spams)
			$new_spams = " ($new_spams)";
		else
			$new_spams = " <span style=\"color:red;\">($cur_moderated " . __("mod.", 'spam-karma') . ")</span>";
	}
	else
		$new_spams = "";

	$last_approved_check = $sk2_settings->get_core_settings("last_approved_check");
	$new_approved = $wpdb->get_var("SELECT COUNT(*) FROM `$wpdb->comments` WHERE (`comment_approved`= '1') AND `comment_date_gmt` > " . gmstrftime("'%Y-%m-%d %H:%M:%S'", (int) $last_approved_check));
	if ($new_approved)
		$new_approved = " ($new_approved)";
	else
		$new_approved = "";

?>
	<ul id="sk_menu">
<?php
	$url = $_SERVER['PHP_SELF'] . "?page=" . $_REQUEST['page'] . "&sk2_section=";
	foreach ($sk_sections as $section => $name)
	{
		if ($cur_section == $section)
			echo "<li class=\"current\">$name</li>";
		else
			echo "<li><a href=\"" . sk_nonce_url($url . $section) . "\">$name</a></li>";
	}
?>
	</ul>
<?php
	
	switch ($cur_section)
	{
		case 'logs':
			
			$log_rows = $wpdb->get_results("SELECT *, UNIX_TIMESTAMP(`ts`) AS `ts2` FROM `". sk2_kLogTable . "` WHERE 1 ORDER BY `ts` DESC, `id` DESC LIMIT 200");
			if (mysql_error())
				$sk2_log->log_msg_mysql(__("Can't fetch logs.", 'spam-karma'), 7, 0, "web_UI");

?>
		<div class="wrap sk_first">
		<h2><?php _e("SK2 Logs", 'spam-karma'); ?></h2>			
			<form id="sk2_logs_remove_form" name="sk2_logs_remove_form" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=spamkarma2&sk2_section=<?php echo $cur_section; ?>">
			<fieldset class="options">
			<?php echo sk_nonce_field(); ?>
			<legend><?php _e("Purge", 'spam-karma'); ?></legend>
			<p class="sk2_form"><?php
			echo '<input type="submit" name="purge_logs" id="purge_logs" value="' . __("Remove logs:", 'spam-karma') . '" /> ' . sprintf(__('older than %s %s and with a level inferior to %s (%s do it automatically from now on).', 'spam-karma'), sk2_settings_ui("purge_logs_duration"), sk2_settings_ui("purge_logs_unit"), sk2_settings_ui("purge_logs_level"), sk2_settings_ui('auto_purge_logs'));
			?></p>
			</fieldset>
			</form>
			<p><em><?php printf(__("Displaying %i most recent log entries", 'spam-karma'), 200); ?></em>
			<table id="sk2_log_list" width="100%" cellpadding="3" cellspacing="3"> 
			<tr>
				<th scope="col"><?php _e("ID", 'spam-karma'); ?></th>
				<th scope="col"><?php _e("Level", 'spam-karma'); ?></th>
				<th scope="col"><?php _e("Message", 'spam-karma'); ?></th>
				<th scope="col"><?php _e("Component", 'spam-karma'); ?></th>
				<th scope="col"><?php _e("How Long Ago", 'spam-karma'); ?></th>
			</tr>
			<?php
			foreach($log_rows as $row)
			{
				echo "<tr class=\"sk_level_$row->level\">";
				echo "<td>$row->id</td>";
				echo "<td>$row->level</td>";
				echo "<td>$row->msg</td>";
				echo "<td>$row->component</td>";
				echo "<td>" . sk2_table_show_hide(sk_time_since($row->ts2), $row->ts) . "</td>";
				echo "</tr>";
			}
			?>
		</table></p>
		<?php
		break;	
				
		case 'blacklist':
			$sk2_settings->set_core_settings(time(), "last_spam_check");

			if (isset($_REQUEST['sk2_blacklist_add']))
			{
				$sk2_blacklist->add_entry($_REQUEST['add_blacklist_type'], $_REQUEST['add_blacklist_value'], $_REQUEST['add_blacklist_score'], "yes", "user");
			}
			elseif (isset($_REQUEST['sk2_edit_rows']) && isset($_REQUEST['blacklist']))
			{
				foreach($_REQUEST['blacklist'] as $id => $entry)
				{
					$id = mysql_escape_string($id);
					$entry['score'] = (int) $entry['score'];
					$wpdb->query("UPDATE `" . sk2_kBlacklistTable . "` SET `type` = '" . sk2_escape_form_string($entry['type']) . "', `value` = '" . sk2_escape_form_string($entry['val']) . "', `score` = " . $entry['score'] . ", `user_reviewed` = 'yes' WHERE `id` = '$id'");
					if (mysql_error())
						$sk2_log->log_msg_sql(__("Failed to update blacklist entry ID: ", 'spam-karma') .  $id, 8, 0, "web_UI");
					else
						$sk2_log->log_msg(__("Succesfully updated blacklist entry ID: ", 'spam-karma') . $id, 4, 0, "web_UI");
				}
			}
			elseif (isset($_REQUEST['remove_checked']) && isset($_REQUEST['blacklist_grp_check']))
			{
				foreach($_REQUEST['blacklist_grp_check'] as $id => $spam)
				{
					$id = mysql_escape_string($id);
					$wpdb->query("DELETE FROM  `". sk2_kBlacklistTable . "` WHERE `id` = $id");
					if (! mysql_error())
						$sk2_log->log_msg(__("Successfully removed blacklist entry ID: ", 'spam-karma') . $id, 4, 0, "web_UI");
					else
						$sk2_log->log_msg_mysql(__("Failed to remove blacklist entry ID: ", 'spam-karma') . $id, 7, 0, "web_UI");
				}
			}
	//	print_r($_REQUEST);
		
			if (! empty($_REQUEST['sk2_show_number']))
				$show_number = $_REQUEST['sk2_show_number'];
			else
				$show_number = 20;

			if (isset($_REQUEST['sk2_match']) && ($_REQUEST['sk2_match'] == "true"))
				$match_mode = true;
			else
				$match_mode = false;
				
		$match_value = @$_REQUEST['sk2_match_value'];
		if (isset($_REQUEST['sk2_match_type']))
			$match_type = $_REQUEST['sk2_match_type'];
		else
			$match_type = "all";

		if($match_mode)
			$blacklist_rows = $sk2_blacklist->match_entries($match_type, $match_value, false, 0, $show_number);
		else
			$blacklist_rows = $wpdb->get_results("SELECT * FROM `". sk2_kBlacklistTable . "` WHERE 1 ORDER BY `added` DESC LIMIT $show_number");
		
		sk2_echo_check_all_JS();

		$blacklist_types = array ("ip_black" => __("IP Blacklist", 'spam-karma'), "ip_white" => __("IP Whitelist", 'spam-karma'), "domain_black" => __("Domain Blacklist", 'spam-karma'), "domain_white" => __("Domain Whitelist", 'spam-karma'), "domain_grey" => __("Domain Greylist", 'spam-karma'), "regex_black" => __("Regex Blacklist", 'spam-karma'), "regex_white" => __("Regex Whitelist", 'spam-karma'), "regex_content_black" => __("Regex Content Blacklist", 'spam-karma'), "regex_content_white" => __("Regex Content Whitelist", 'spam-karma'), "rbl_server" => __("RBL Server (IP)", 'spam-karma'), "rbl_server_uri" => __("RBL Server (URI)", 'spam-karma'), "kumo_seed" => __("Kumo Seed", 'spam-karma'));
?>
			<div class="wrap sk_first">
			<h2><?php _e("Blacklist", 'spam-karma'); ?></h2>
			<fieldset class="options">
			<legend><?php _e("Add", 'spam-karma'); ?></legend>
			<form id="sk2_blacklist_add_form" name="sk2_blacklist_add_form" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=spamkarma2&sk2_section=<?php echo $cur_section; ?>">
			<p class="sk2_form">
						<?php echo sk_nonce_field(); ?>
			<select name="add_blacklist_type" id="add_blacklist_type"><?php
				$default = "ip_black";
				foreach($blacklist_types as $type => $type_caption)
					if ($default == $type)
						echo "<option value=\"$type\" selected>$type_caption</option>";
					else
						echo "<option value=\"$type\">$type_caption</option>";
			?></select>: <input type="text" size="20" name="add_blacklist_value" id="add_blacklist_value" value="" /> <input type="submit" name="sk2_blacklist_add" value="<?php _e("Add entry", 'spam-karma'); ?>" />   (<?php _e("Score: ", 'spam-karma'); ?><input type="text" size="3" name="add_blacklist_score" id="add_blacklist_score" value="100" />)</p>
			</form>
			</fieldset>

			<form id="sk2_blacklist_remove_form" name="sk2_blacklist_remove_form" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=spamkarma2&sk2_section=<?php echo $cur_section; ?>">
			<fieldset class="options">
			<legend><?php _e("Show", 'spam-karma'); ?></legend>
			<p class="sk2_form">
			<?php
			echo sk_nonce_field();

			printf(__("%sShow%s last %s entries.", 'spam-karma'), '<input type="submit" name="sk2_show_last"  id="sk2_show_last" value="', '" />', '<input type="text" size="3" name="sk2_show_number" id="sk2_show_number" value="' . $show_number . '" />');
			?></p>
			<p class="sk2_form"><input type="checkbox" name="sk2_match"  id="sk2_match" value="true" <?php if ($match_mode) echo "checked"; ?> /> <?php _e("Match", 'spam-karma'); ?> <input type="text" size="10" name="sk2_match_value" id="sk2_match_value" value="<?php echo $match_value; ?>" /> <select name="sk2_match_type" id="sk2_match_type"><?php
			$options = array("all" => __("All", 'spam-karma'), "ip" => __("IP", 'spam-karma'), "url" => __("URL", 'spam-karma'), "regex_content_match" => __("Content", 'spam-karma'), "rbl_server" => __("RBL Server", 'spam-karma'), "kumo_seed" => __("Kumo Seed", 'spam-karma'), "regex" => __("Regex string (non-interpreted)", 'spam-karma'));
			foreach ($options as $key => $val)
			{
				echo "<option value=\"$key\"";
				if ($key == $match_type)
					echo " selected";
				echo ">$val</option>";
			}
			?></select></p>
			</fieldset>
			<fieldset class="options">
			<legend><?php _e("Remove", 'spam-karma'); ?></legend>
			<p class="sk2_form"><?php
			printf(__("%sRemove entries:%s %s more than %s ago and with a score inferior to %s (%s do it automatically from now on).", 'spam-karma'), '<input type="submit" name="purge_blacklist" id="purge_blacklist" value="', '" /> ', sk2_settings_ui("purge_blacklist_criterion"), sk2_settings_ui("purge_blacklist_duration") . sk2_settings_ui("purge_blacklist_unit"),  sk2_settings_ui("purge_blacklist_score"), sk2_settings_ui('auto_purge_blacklist')); 
			?></p>

			<p class="sk2_form"><input type="submit" name="remove_checked" id="remove_checked" value="<?php _e("Remove Selected Entries", 'spam-karma'); ?>" /> <a href="javascript:;" onclick="checkAll(document.getElementById('sk2_blacklist_remove_form')); return false; " />(<?php _e("Invert Checkbox Selection", 'spam-karma'); ?>)</a></p>
			</fieldset>
			<fieldset class="options">
			<legend><?php
			if (! $match_mode)
				printf(__("Last %d Entries", 'spam-karma'), $show_number);
			else
				echo __("Entries Matching ", 'spam-karma'), "<em>$match_value</em>";
			?></legend>
			<p><table id="sk2_spam_list" width="100%" cellpadding="3" cellspacing="3"> 
			<tr>
				<th scope="col"><?php _e("ID", 'spam-karma'); ?></th>
				<th scope="col"><?php _e("Type", 'spam-karma'); ?></th>
				<th scope="col"><?php _e("Value", 'spam-karma'); ?></th>
				<th scope="col"><?php _e("Score", 'spam-karma'); ?></th>
				<th scope="col"><?php _e("How long ago", 'spam-karma'); ?></th>
				<th scope="col"><?php _e("Used", 'spam-karma'); ?></th>
			</tr>
	<?php
		if (isset($_REQUEST['sk2_edit_mode']) && ($_REQUEST['sk2_edit_mode'] == "true"))
			$edit_mode = true;
		else
			$edit_mode = false;
		
		echo "<input type=\"hidden\" name=\"sk2_edit_mode\" id=\"sk2_edit_mode\" value=\"$edit_mode\" />";
		echo "<input type=\"submit\" name=\"switch_mode\" id=\"switch_mode\" value=\"";
		if ($edit_mode)
			_e("Switch to view mode", 'spam-karma');
		else
			_e("Switch to edit mode", 'spam-karma'); 
		echo "\" onclick=\"this.form['sk2_edit_mode'].value = " . ($edit_mode ? "false" : "true") . ";\" />";
		
		if (is_array($blacklist_rows))
			foreach ($blacklist_rows as $row)
			{
				if ($row->score < 30)
					$color = "rgb(120, 120, 120)";
				elseif ($row->score < 50)
				{
					$x = min ((int) (120 + 60 * pow(($row->score - 30)/20, 2)), 256);
					$color = "rgb($x, $x, $x)";
				}
				elseif ($row->score < 80)
				{
					$x = max (0, (int) (180 - 3 * ($row->score - 50)));
					$y = min(256, (int) (180 - $row->score + 50));
					$z = min(256, (int) (180 + 2.3 * ($row->score - 50)));
					$color = "rgb($x, $y, $z)";
				}
				elseif ($row->score < 100)
				{
					$color = "rgb(90, 150, 250)";
				}
				else
				{
					$color = "rgb(90, 150, 256)";
				}
				echo "<tr style=\"background-color: $color;\">";
				echo "<th scope=\"row\"><input type=\"checkbox\" name=\"blacklist_grp_check[$row->id]\" id=\"blacklist_grp_check[$row->id]\" value=\"true\" /> $row->id</th>";
				echo "<td>";
				if (! isset($blacklist_types[$row->type]))
					$blacklist_types[$row->type] = __("Unknown", 'spam-karma') . " (" . $row->type . ")";
				if ($edit_mode)
				{
					echo "<select name=\"blacklist[$row->id][type]\" id=\"blacklist[$row->id][type]\">";
					foreach($blacklist_types as $type => $type_caption)
						if ($row->type == $type)
							echo "<option value=\"$type\" selected>$type_caption</option>";
						else
							echo "<option value=\"$type\">$type_caption</option>";
					echo "</select>";
				}
				else
					echo $blacklist_types[$row->type];
				echo "</td>";
				echo "<td>";
				if ($edit_mode)
					echo "<input type=\"text\" name=\"blacklist[$row->id][val]\" id=\"blacklist[$row->id][val]\" value=\"" . str_replace("\"", "&quot;", $row->value) . "\" size=\"". max(6, min(strlen($row->value), 40)) ."\">";
				else
					echo $row->value;
				echo "</td>";
				echo "<td>";
				if ($edit_mode)
					echo "<input type=\"text\" name=\"blacklist[$row->id][score]\" id=\"blacklist[$row->id][score]\" value=\"$row->score\" size=\"3\">";
				else
					echo $row->score;
				echo "</td>";
				echo "<td>" . sk2_table_show_hide(sk_time_since(strtotime($row->last_used)), __("Added: ", 'spam-karma') . $row->added . "<br/>" . __("Last Used: ", 'spam-karma') . $row->last_used) . "</td>";
				echo "<td>" . ($row->used_count+1) . "</tr>";
				echo "</tr>";
			}
?>
			</table></p>
			<?php
			if ($edit_mode)
				echo "<p class=\"submit\"><input type=\"submit\" name=\"sk2_edit_rows\" id=\"sk2_edit_rows\" value=\"" . __("Save Changes", 'spam-karma') . "\" /></p>";
			?>
			</fieldset>
			</form>
			</div>
<?php
		break;
		
		// RECENT SPAM SCREEN
		case 'spam':
		case 'approved':
			

			if ($cur_section == 'spam')
			{
				$sk2_settings->set_core_settings(time(), "last_spam_check");
				$query_where = "`comment_approved` != '1'";
			}
			else
			{
				$query_where = "`comment_approved` = '1'";
				$sk2_settings->set_core_settings(time(), "last_approved_check");
			}
			
			$query_limit_str = $query_limit = max(20, @$_REQUEST['sql_rows_per_page']);
			if (@$_REQUEST['sql_skip_rows'])
				$query_limit_str = $_REQUEST['sql_skip_rows'] . ", " . ($_REQUEST['sql_skip_rows'] + $query_limit);
			
			$score_threshold = intval (isset($_REQUEST['sql_score_threshold']) ? $_REQUEST['sql_score_threshold'] : -20);
			
// added some stuff that should fix a mySQL 5 bug			
			$query = "SELECT `posts_table`.`post_title`,  `spam_table`.`karma`, `spam_table`.`id` as `spam_id`,`spam_table`.`karma_cmts`, `comments_table`.*, IF(`comments_table`.`comment_approved` = '0', 1, 0) AS `display_priority` FROM (`". $wpdb->comments . "` AS `comments_table`, `" . $wpdb->posts ."` AS `posts_table`) LEFT JOIN `". sk2_kSpamTable . "` AS `spam_table` ON `spam_table`.`comment_ID` = `comments_table`.`comment_ID` WHERE  $query_where AND `posts_table`.`ID` = `comments_table`.`comment_post_ID` AND (`spam_table`.`karma` IS NULL  OR `spam_table`.`karma` >= $score_threshold) ORDER BY `display_priority` DESC, `comments_table`.`comment_date_gmt` DESC LIMIT $query_limit_str";


			//echo "####" . $query;
			$spam_rows = $wpdb->get_results($query);
			if (mysql_error())
				$sk2_log->log_msg_mysql(__("Can't fetch comments.", 'spam-karma') . " <br/>" . __("Query: ", 'spam-karma') . "<code>$query</code>", 7, 0, "web_UI");


		sk2_echo_check_all_JS();
?>
		<div class="wrap sk_first">
		<h2><?php echo (($cur_section == 'spam') ? __("Spams Caught by SK2", 'spam-karma') : __("Comments recently approved", 'spam-karma')); ?></h2>
		<fieldset class="options">
			<legend><?php _e("Browse", 'spam-karma'); ?></legend>
			<p class="sk2_form"><form id="sk2_spamlist_display_form" name="sk2_spamlist_display_form" method="get" action="<?php echo $_SERVER['PHP_SELF'];?>">
						<?php echo sk_nonce_field(); ?>
			<input type="hidden" name="page" id="page" value="spamkarma2" />
			<input type="hidden" name="sk2_section" id="sk2_section" value="<?php echo $cur_section; ?>" /><input type="submit" name="display_cmts" id="display_cmts" value="<?php _e("Display", 'spam-karma'); ?>" /><input type="text" id="sql_rows_per_page" name="sql_rows_per_page" value="<?php echo $query_limit; ?>" size="3"/> <?php _e("comments per page, skipping first: ", 'spam-karma'); ?> <input type="text" id="sql_skip_rows" name="sql_skip_rows" value="<?php echo intval(@$_REQUEST['sql_skip_rows']) ?>" size="3" /> <?php _e('with karma lower than', 'spam-karma'); ?> <input type="text" id="sql_score_threshold" name="sql_score_threshold" value="<?php echo $score_threshold; ?>" size="4"></form></p>
		</fieldset>		
		<?php
		if ($cur_section == 'spam')
		{
		?>
		<fieldset class="options">
			<legend><?php _e("Clean", 'spam-karma'); ?></legend><form id="sk2_spamlist_purge_form" name="sk2_spamlist_purge_form" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=spamkarma2&sk2_section=<?php echo $cur_section; ?>">
				<?php 
				echo sk_nonce_field();
				if ($cur_moderated)
				{
				?><p class="sk2_form"><input type="submit" name="confirm_moderation" id="confirm_moderation" value="<?php _e("Confirm All Moderated As Spam", 'spam-karma'); ?>" /> (<?php _e("outlined in red", 'spam-karma'); ?>)</p><?php 
				}
				?>
				<p class="sk2_form"><?php
				printf(__("%sPurge Comment Spams:%s older than %s%s (%s do it automatically from now on).", 'spam-karma'), '<input type="submit" name="purge_spamlist" id="purge_spamlist" value="', '" />', sk2_settings_ui("purge_spamlist_duration"), sk2_settings_ui("purge_spamlist_unit"), sk2_settings_ui('auto_purge_spamlist')); 
				?></p></form>
		<form id="sk2_spamlist_form" name="sk2_spamlist_form" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=spamkarma2&sk2_section=<?php echo $cur_section; ?>"><p class="sk2_form">
					<?php echo sk_nonce_field(); ?>
				<input type="submit" name="remove_checked" id="remove_checked" value="<?php _e("Remove Selected Entries", 'spam-karma'); ?>" /> <a href="javascript:;" onclick="checkAll(document.getElementById('sk2_spamlist_form')); return false; " />(<?php _e("Invert Checkbox Selection", 'spam-karma'); ?>)</a></p>
			</fieldset>
		<?php
			}
			else
			{
				?>
				<form id="sk2_spamlist_form" name="sk2_spamlist_form" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=spamkarma2&sk2_section=<?php echo $cur_section; ?>">
							<?php echo sk_nonce_field(); ?>
			<?php
			}

			$switch =  (($cur_section == 'spam') ? __("Recover", 'spam-karma') : __("Moderate", 'spam-karma')); 
	?>
			
			<fieldset class="options">
			<legend><?php echo $switch; ?></legend>
			<input type="submit" id="recover_selection" name="recover_selection" value="<?php echo $switch, " ", __("Selected", 'spam-karma'); ?>" /> <a href="javascript:;" onclick="checkAll(document.getElementById('sk2_spamlist_form')); return false; " />(<?php _e("Invert Checkbox Selection", 'spam-karma'); ?>)</a>
			</fieldset>
		
			<fieldset class="options">
			<legend><?php _e("Filter", 'spam-karma'); ?></legend>
			<p class="sk2_form"><?php
			printf(__("%sRun selected entries%s through ", 'spam-karma'), '<input type="submit" name="sk2_run_filter" id="sk2_run_filter" value="', '" />');
			?><select name="action_param[which_plugin]" id="action_param[which_plugin]">
			<option value="all" selected><?php _e("All plugins", 'spam-karma'); ?></option>
			<?php
				 foreach ($sk2_core->plugins as $plugin)
				 	echo "<option value=\"$plugin[2]\">". $plugin[1]->name . "</option>\n";
			?>
			</select>  <a href="javascript:;" onclick="checkAll(document.getElementById('sk2_spamlist_form')); return false; " />(<?php _e("Invert Checkbox Selection", 'spam-karma'); ?>)</a></p>
			</fieldset>
			<p><table id="sk2_spam_list" width="100%" cellpadding="3" cellspacing="3"> 
			<tr><th colspan="7"><?php printf(__('Only displaying comments with a karma above %d:', 'spam-karma'), $score_threshold);  ?></th></tr>
			<tr>
				<th scope="col"><?php _e("ID", 'spam-karma'); ?></th>
				<th scope="col"><?php _e("Karma", 'spam-karma'); ?></th>
				<th scope="col"><?php _e("How Long Ago", 'spam-karma'); ?></th>
				<th scope="col"><?php _e("Author", 'spam-karma'); ?></th>
				<th scope="col"><?php _e("Post Title", 'spam-karma'); ?></th>
				<th scope="col"><?php _e("Comment", 'spam-karma'); ?></th>
				<th scope="col"><?php _e("Type", 'spam-karma'); ?></th>
			</tr>
<?php
		if (is_array($spam_rows))
			foreach ($spam_rows as $row)
			{
				sk2_clean_up_sql($row);
				if (! $row->spam_id)
				{
					$row->karma = "[?]";
					$color = "rgb(200, 200, 256)";
				}
				elseif ($row->karma < -20)
					$color = "rgb(130, 130, 130)";
				elseif ($row->karma < -10)
				{
					$x = (int) (130 + 50 * pow((20 + $row->karma)/10, 2));
					$color = "rgb($x, $x, $x)";
				}
				else
				{
					$x = max (0, (int) (180 - 12 * (10 + $row->karma)));
					$y = min(256, (int) (180 + 7.6 * (10 + $row->karma)));
					$color = "rgb($x, $y, $x)";
				}
				
				$karma_cmts = "";
				if (!empty($row->karma_cmts))
				{
					$karma_cmts_array = unserialize($row->karma_cmts);

					if (is_array($karma_cmts_array))
						foreach ($karma_cmts_array as $cmt)
						{
							$karma_cmts .=  "<div class=\"";
							if ($cmt['hit'] >= 0)
								$karma_cmts .= "good_karma";
							else
								$karma_cmts .= "bad_karma";
							$karma_cmts .= "\">";
							$karma_cmts .=  "<b>" . $cmt['hit'] . "</b>: <i>" . sk2_soft_hyphen($cmt['reason']) . "</i>";	
							$karma_cmts .= "</div>";
						}
				}

				// should we replace <a> tags?
				//preg_replace();

				echo "<tr style=\"background-color: $color;\"";
				if ($row->comment_approved == '0')
					echo " class=\"moderated\"";
				echo ">";
				echo "<th scope=\"row\"><input type=\"checkbox\" name=\"comment_grp_check[$row->comment_ID]\" id=\"comment_grp_check[$row->comment_ID]\" value=\"" . $row->spam_id . "\"  /> $row->comment_ID</th>";
				echo "<td>";
				if (! empty($karma_cmts))
					echo sk2_table_show_hide($row->karma, $karma_cmts);
				else
					echo "<b>$row->karma</b>";
				echo "</td>";
				echo "<td>" . sk2_table_show_hide(sk_time_since(strtotime($row->comment_date_gmt . " GMT")), $row->comment_date_gmt . "GMT") ." </td>";
				echo "<td>" . sk2_table_show_hide(substr(sk2_html_entity_decode($row->comment_author, ENT_QUOTES, "UTF-8"), 0, 20), (strlen($row->comment_author) > 20 ? "<i>" . __("Author: ", 'spam-karma') ."</i><b>" . htmlentities($row->comment_author) . "</b><br/>" : "") . "<i>" . __("E-mail: ", 'spam-karma') . "</i><b>" .  sk2_soft_hyphen($row->comment_author_email) . "</b><br/><i>" . __("IP: ", 'spam-karma') . "</i><b>". sk2_soft_hyphen($row->comment_author_IP) . "</b><br/><i>" . __("URL: ", 'spam-karma') . "</i><b>" . sk2_soft_hyphen($row->comment_author_url) . "</b>") . "</td>";
				echo "<td>$row->post_title</div></td>";
				echo "<td>";
				$row->comment_content = strip_tags($row->comment_content);
				if (strlen($row->comment_content) > 40)
					echo sk2_table_show_hide(sk2_soft_hyphen(substr($row->comment_content, 0, 35)) . " [...]", sk2_soft_hyphen(substr($row->comment_content, 35)));
				else
					echo sk2_soft_hyphen($row->comment_content);
				echo "</td><td>";
				switch ($row->comment_type)
				{
					case "":
					case "comment":
						_e("Cmt", 'spam-karma');
					break;
					case "trackback":
						_e("TB", 'spam-karma');
					break;
					case "pingback":
						_e("PB", 'spam-karma');
					break;
					default:
						echo $row->comment_type;
					break;
				}
				echo "</td>";
				echo "</tr>";
			}
?>
		</table></p>
		</form>
		</div>
<?php
		break;
		
		// GENERAL SETTINGS SCREEN
		case 'general':
		default:			
			$sk2_core->save_UI_settings($_REQUEST);

			if (isset($_REQUEST['advanced_tools']))
				$sk2_core->advanced_tools($_REQUEST);
			
			$sk2_core->update_SQL_schema();
			$sk2_core->update_components();

			// GET NEWS
			if ($sk2_settings->get_core_settings('next_news_update') < time())
			{
				$url = sk_news_update_check_url . "?sk2_version=" . urlencode(sk2_kVersion) . "&sk2_release=" . urlencode(sk2_kRelease) . "&sk2_lang=" . urlencode(WPLANG);
				if ($update_file = sk2_get_url_content($url))
				{
					if (is_array($news_array = unserialize($update_file)))
					{
						$new_news = array();
						if (! is_array($old_news = $sk2_settings->get_core_settings('news_archive')))
							$old_news = array();
						
						foreach($news_array as $ts => $news_item)
						{
							if (! isset($old_news[$ts]))
								$new_news[$ts] = $news_item;
							$old_news[$ts] = $news_item;
						}

						krsort($old_news);
						while (count($old_news) > 10)
							array_pop($old_news);
					
						$sk2_settings->set_core_settings($old_news, 'news_archive');
						if (count($new_news) > 0)
						{
							echo "<div class=\"wrap sk_first\"><h2>" . __("News", 'spam-karma') . "</h2>";
							foreach ($new_news as $ts => $news_item)
							{
								echo "<div class=\"news_item";
								if (@$news_item['level'] > 0)
									echo " sk_level_" . $news_item['level'];
								echo "\">";
								echo $news_item['msg'];								
								echo "<div class=\"news_posted\">" . sprintf(__("Posted %s ago", 'spam-karma'), sk_time_since($ts)) . "</div> ";
								echo "</div>";
							}
							echo "</div>";
						}
						$sk2_log->log_msg(__("Checked news from: ", 'spam-karma') . "<em>$url</em><br/>" . sprintf(__ngettext("One new news item, %d total", "%d new news items, %d total", count($new_news), 'spam-karma'), count($new_news), count($old_news)), 3, 0, "web_UI");
					}
					else
						$sk2_log->log_msg(__("Cannot unserialize news array from URL: ", 'spam-karma') . "<em>$url</em>", 8, 0, "web_UI");
				}
				else
					$sk2_log->log_msg(__("Cannot load news from URL: ", 'spam-karma') . "<em>$url</em>", 7, 0, "web_UI");

				$sk2_settings->set_core_settings(time() + sk_news_update_interval, 'next_news_update');
			}
			
			if ($sk2_settings->get_core_settings('init_install') < 1)
			{
				echo "<div class=\"wrap sk_first\">";
				$sk2_log->log_msg(__("Running first-time install checks...", 'spam-karma'), 4, 0, "web_UI", true, false);
				echo "<br/>";
				$sk2_core->advanced_tools(array("check_comment_form" => true));
				$sk2_settings->set_core_settings(1, 'init_install');
				echo "</div>";
			}
?>
		<div class="wrap sk_first"><h2><?php _e("Stats", 'spam-karma'); ?></h2>
		<ul>
		<li><?php _e("Total Spam Caught: ", 'spam-karma'); ?><strong><?php echo $hell_count = (int) $sk2_settings->get_stats("hell"); ?></strong> <?php 
		if ($hell_count > 0)
			echo " (" . __("average karma: ", 'spam-karma') . round((int) $sk2_settings->get_stats("hell_total_karma") / $hell_count, 2) . ")"; ?></li>
		<li><?php _e("Total Comments Approved: ", 'spam-karma'); ?><strong><?php echo $paradise_count = (int) $sk2_settings->get_stats("paradise"); ?></strong><?php 
		if ($paradise_count > 0)
			echo " (" . __("average karma: ", 'spam-karma') . round((int) $sk2_settings->get_stats("paradise_total_karma") / $paradise_count, 2) . ")"; ?></li>
		<li><?php _e("Total Comments Moderated: ", 'spam-karma'); ?><strong><?php echo (int) $sk2_settings->get_stats("purgatory"); ?></strong> <?php 
		if ($cur_moderated)
			printf("(" . __ngettext("currently %s%d waiting%s", "currently %s%d waiting%s", $cur_moderated, 'spam-karma') . ")", '<a href="' . sk_nonce_url('options-general.php?page=' . $_REQUEST['page'] . '&sk2_section=spam') . '">', $cur_moderated, '</a>');
			
			?></li>
		<li><?php _e("Current Version: ", 'spam-karma'); ?><strong><?php echo "2." . sk2_kVersion . " " . sk2_kRelease; ?></strong></li>
		</ul>
		</div>
<?php
		$sk2_core->output_UI();
?>
	<div class="wrap">
	<h2><?php _e("Advanced Options", 'spam-karma'); ?></h2>
	<form name="sk2_advanced_tools_form" id="sk2_advanced_tools_form" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=spamkarma2&sk2_section=<?php echo $cur_section; ?>">
				<?php echo sk_nonce_field(); ?>
	<input type="hidden" name="advanced_tools" id="advanced_tools" value="true">
<script type="text/javascript">
<!--

function toggleAdvanced(mybutton, myid)
{
	var node = document.getElementById(myid);

	if(node == null) 
	{
		alert('<?php _e("Bad ID", 'spam-karma'); ?>');
		return;
	}

	if(node.className.match(/\bshow\b/) != null)
	{
		node.className = node.className.replace(/\bshow\b/, "hide");
		mybutton.innerHTML = "<?php _e("Show Advanced Options", 'spam-karma'); ?>";
	} 
	else	if(node.className.match(/\bhide\b/) != null)
		{
			node.className = node.className.replace(/\bhide\b/, "show"); 
			mybutton.innerHTML = "<?php _e("Hide Advanced Options", 'spam-karma'); ?>";
		}
}

//-->
</script>
		<fieldset class="themecheck">
			<p><button name="advance_toggle" id="advance_toggle" onclick="toggleAdvanced(this, 'sk2_settings_pane');return false;"><?php _e("Show Advanced Settings", 'spam-karma'); ?></button> <i><?php _e("Will show/hide advanced options in the form above", 'spam-karma'); ?></i></p>
		</fieldset>
		<fieldset class="dbtools">
			<legend><?php _e("Database Tools", 'spam-karma'); ?></legend>
		    <p><input type="submit" id="force_sql_update" name="force_sql_update" value="<?php _e("Force MySQL updates", 'spam-karma'); ?>"> 
		    <input type="submit" id="reinit_plugins" name="reinit_plugins" value="<?php _e("Reinit Plugins", 'spam-karma'); ?>">
		    <input type="submit" id="reset_all_tables" name="reset_all_tables" onclick="javascript:return confirm('<?php _e("Do you really want to reset all SK2 tables.", 'spam-karma'); ?>');" value="<?php _e("Reset All Tables", 'spam-karma'); ?>">
		  
		  <input type="submit" id="reinit_all" name="reinit_all" onclick="javascript:return confirm('<?php _e("Do you really want to reset all SK2 settings?", 'spam-karma'); ?>');" value="<?php _e("Reset to Factory Settings", 'spam-karma'); ?>"></p>
		</fieldset>
		
		<fieldset class="themecheck">
			<legend><?php _e("Theme Check", 'spam-karma'); ?></legend>
			<p><?php
			_e("SK2 will not work properly if your theme is not 100% 1.5-compatible. In particular, oftentimes, the comment form of some custom themes does not contain the proper code to work with 1.5 plugins. For more details and a guide on how to fix, please <a href=\"http://wp-plugins.net/wiki/index.php?title=SK2_Theme_Compatibility\">check out the wiki</a>.", 'spam-karma'); 
			echo "<em>" . __("You do not have to worry about this if you are using a standard out-of-the-box 1.5 install and the theme that came with it.", 'spam-karma') . "</em>"; 
			?></p>
		    <ul>
		    <li><input type="submit" id="check_comment_form" name="check_comment_form" value="<?php _e("Theme Compatibility Check", 'spam-karma'); ?>"> (<?php _e("attempts to examine your theme's files and check for compatibility", 'spam-karma'); ?>).</form></li>
		    <li><strong><?php _e("Advanced Compatibility Check", 'spam-karma'); ?></strong> <i><?php _e("Enter the URL of a page on your blog where the comment form appears (most likely the URL to any single entry, or the URL to your pop-up comment form if you are using the pop-up view) and click Submit", 'spam-karma'); ?></i><br/>
		   	<form name="sk2_advanced_tools_form_2" id="sk2_advanced_tools_form_2" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=spamkarma2&sk2_section=<?php echo $cur_section; ?>">
	<input type="hidden" name="advanced_tools" id="advanced_tools" value="true"><input type="text" id="check_comment_form_2_url" name="check_comment_form_2_url" size="30"> 
						<?php echo sk_nonce_field(); ?>
			<input type="submit" id="check_comment_form_2" name="check_comment_form_2" value="<?php _e("Submit", 'spam-karma'); ?>"></li>
		    </ul>
		</fieldset>
	</form>
	</div>
<?php
		break;
	
		case "about":
			include_once(dirname(__FILE__) ."/sk_about.php");
			return;
		break;
	}
	$sk2_settings->save_settings();	

	// DEBUG
	/* No longer necessary...
	<div class="wrap">
	<?php _e("Log Dump: ", 'spam-karma'); ?><br/>
	<?php $sk2_log->dump_logs(); ?>
	</div>
	*/
}

function sk2_table_show_hide($show, $hide)
{
	global $sk2_settings;
	if ($sk2_settings->get_core_settings("hover_in_tables"))
	{
		return "<div class=\"show_hide_details\"><span class=\"show_hide_switch\">$show</span><p>$hide</p></div>";
	}
	else
	{
		return "<div class=\"no_show_hide\"><p>$show</p><p>$hide</p></div>";
	}
}


function sk2_output_admin_css ()
{
	if ($_REQUEST['page'] == 'spamkarma2')
		include_once(dirname(__FILE__) . "/sk_admin_css.php");
}

function sk2_settings_ui($name, $type = false, $options_size = false)
{
	global $sk2_settings;
	$str = "";
	
	if (! $type)
	{
		$type = @$sk2_settings->core_defaults[$name]["type"];
		if (($type == "menu") || ($type == "select"))
			$options_size = @$sk2_settings->core_defaults[$name]["options"];
		elseif ($type == "text")
			$options_size = max(@$sk2_settings->core_defaults[$name]["size"], 1);	
	}
	
	$value = $sk2_settings->get_core_settings($name);
	
	switch ($type)
	{
		case "checkbox":
		case "check":
			$str .= "<input type=\"checkbox\" name=\"sk2_core_settings_checkbox[$name]\" id=\"sk2_core_settings_checkbox[$name]\" ";
			if ($value)
				$str .= "checked ";
			$str .= "/>";
			$str .= "<input type=\"hidden\" name=\"sk2_core_settings_ui[$name]\" id=\"sk2_core_settings_ui[$name]\" value=\"checkbox\" />";
		break;
		
		case "text":
			$str .= "<input type=\"text\" name=\"sk2_core_settings_ui[$name]\" id=\"sk2_core_settings_ui[$name]\" value=\"". str_replace("\"", "&#34;", $value) . "\" size=\"$options_size\" />";
		break;
		
		case "menu":
		case "select":

			$str .= "<select name=\"sk2_core_settings_ui[$name]\" id=\"sk2_core_settings_ui[$name]\">";
			
			foreach ($options_size as $key => $text)
			{
				$key = str_replace("\"", "&#34;", $key);
				if ($value == $key)
					$str .= "<option value=\"$key\" selected>" . __($text, 'spam-karma') . "</option>";
				else
					$str .= "<option value=\"$key\">" . __($text, 'spam-karma') . "</option>";
			}
			
			$str .= "</select>";
		break;
		
		default:
			$str .= "<strong>" . __("Can't render UI control: ", 'spam-karma') . "$name</strong><br/>";
		break;
	}
	
	return $str;
}

function sk2_echo_check_all_JS()
{
?>
<script type="text/javascript">
<!--
function checkAll(form)
{
	for (i = 0, n = form.elements.length; i < n; i++) {
		if((form.elements[i].type == "checkbox") && (form.elements[i].id.indexOf("grp") > 0))
		{
				form.elements[i].checked = ! form.elements[i].checked;
		}
	}
}
//-->
</script>
<?php
}


function sk2_form_insert($id = 0)
{
	global $sk2_settings;
	
	if (! $id)
	{
		global $post;
		$id = $post->ID;
	}
	require_once(dirname(__FILE__) ."/sk_core_class.php");
	$sk2_core = new sk2_core(0, false);
	$sk2_core->form_insert($id);
	$sk2_settings->save_settings();	
}

function sk2_fix_approved($approved)
{
// only way to prevent notification
	return 'spam';
}


function sk2_filter_comment($comment_ID)
{
	include_once(dirname(__FILE__) ."/sk_core_class.php");

	if (! $comment_ID)
	{
		$sk2_log->log_msg(__("Structural failure: no comment ID sent to comment hook", 'spam-karma'), 10, 0, "web_UI", true, false);
		die(__("Aborting Spam Karma", 'spam-karma'));
	}
	$sk2_core = new sk2_core($comment_ID, false);
	$sk2_core->process_comment();

	$approved = $sk2_core->cur_comment->approved;

	$sk2_settings->save_settings();	
	// should also save/display logs here...
	
	// doing notification ourselves (since we killed WP's)
	if ($approved  == 'spam')
	{ // your adventure stops here, cowboy...
		header("HTTP/1.1 403 Forbidden");
		header("Status: 403 Forbidden");
		_e("Sorry, but your comment has been flagged by the spam filter running on this blog: this might be an error, in which case all apologies. Your comment will be presented to the blog admin who will be able to restore it immediately.<br/>You may want to contact the blog admin via e-mail to notify him.", 'spam-karma');
		
//		echo "<!-- ";
//		$sk2_log->dump_logs();
//		echo "-->";
		die();
	}
	else
	{
		if ( '0' == $approved )
		{
			if ($sk2_core->cur_comment->can_unlock())
			{
				// redirect to Second Chance page
                header('Expires: Mon, 26 Aug 1980 09:00:00 GMT');
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');

				$location = get_bloginfo('wpurl') .  "/" . strstr(str_replace("\\", "/", dirname(__FILE__)), "wp-content/") . "/" . sk_second_chance_file ."?c_id=$comment_ID&c_author=" . urlencode($sk2_core->cur_comment->author_email);

                //$location = str_replace($_SERVER['DOCUMENT_ROOT'], "/", dirname(__FILE__)) . "/" . sk_second_chance_file ."?c_id=$comment_ID&c_author=" . urlencode($sk2_core->cur_comment->author_email);

				 $can_use_location = ( @preg_match('/Microsoft|WebSTAR|Xitami/', getenv('SERVER_SOFTWARE')) ) ? false : true;
				 if (!$can_use_location && ($phpver >= '4.0.1') && @preg_match('/Microsoft/', getenv('SERVER_SOFTWARE')) && (php_sapi_name() == 'isapi'))
						$can_use_location = true;

				if ($can_use_location)
					header("Location: $location");
				else
					header("Refresh: 0;url=$location");
				
				exit();
			}
			else
				wp_notify_moderator($comment_ID);
		}
		elseif ( get_option('comments_notify'))
		{
			wp_notify_postauthor($comment_ID, $sk2_core->cur_comment->type);
		}
	}
}

function sk2_insert_footer()
{
	global $sk2_settings;
	require_once(dirname(__FILE__) . "/sk_util_class.php");

	if ($sk2_settings->get_core_settings("display_sk2_footer"))
	{
		if ($sk2_settings->get_stats("hell") < 2)
		{
			echo __($sk2_settings->get_core_settings("sk2_footer_msg_0"), 'spam-karma');
		}
		else
		{
			foreach (array("hell", "purgatory", "paradise", "hell_total_karma", "paradise_total_karma") as $val)
				$replace_vals["{". $val . "}"] = $sk2_settings->get_stats($val);
			echo strtr(__($sk2_settings->get_core_settings("sk2_footer_msg_n"), 'spam-karma'), $replace_vals);
		}
	}
}

// PLUGGABLE FUNCTIONS: overriding 

if ( !function_exists('wp_notify_moderator') )
{
	function wp_notify_moderator($comment_id) 
	{
			global $wpdb;
	
			if( get_option( "moderation_notify" ) == 0 )
					return true; 
		
			$comment = $wpdb->get_row("SELECT * FROM $wpdb->comments WHERE comment_ID='$comment_id' LIMIT 1");
			$post = $wpdb->get_row("SELECT * FROM $wpdb->posts WHERE ID='$comment->comment_post_ID' LIMIT 1");
	
			$comment_author_domain = gethostbyaddr($comment->comment_author_IP);
			$comments_waiting = $wpdb->get_var("SELECT count(comment_ID) FROM $wpdb->comments WHERE comment_approved = '0'");
	
			$notify_message  = sprintf( __('A new comment on the post #%1$s "%2$s" is waiting for your approval', 'spam-karma'), $post->ID, $post->post_title ) . "\r\n";
			$notify_message .= get_permalink($comment->comment_post_ID) . "\r\n\r\n";
			$notify_message .= sprintf( __('Author : %1$s (IP: %2$s , %3$s)', 'spam-karma'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
			$notify_message .= sprintf( __('E-mail : %s', 'spam-karma'), $comment->comment_author_email ) . "\r\n";
			$notify_message .= sprintf( __('URI    : %s', 'spam-karma'), $comment->comment_author_url ) . "\r\n";
			$notify_message .= sprintf( __('Whois  : http://ws.arin.net/cgi-bin/whois.pl?queryinput=%s', 'spam-karma'), $comment->comment_author_IP ) . "\r\n";
			$notify_message .= __('Comment: ', 'spam-karma') . "\r\n" . $comment->comment_content . "\r\n\r\n";

//### DdV Mods
			$location = get_bloginfo('wpurl') . "/wp-admin/edit.php?page=spamkarma2";

			$notify_message .= sprintf( __('To approve this comment, visit: %s', 'spam-karma'), sk_nonce_email_url($post->post_author, $location . "&recover_selection=1&comment_grp_check%5B$comment_id%5D=$comment_id&sk2_section=spam"))  . "\r\n";
			//### Add l10n:
			$notify_message .= sprintf( __('To flag this comment as spam, visit: %s', 'spam-karma'), sk_nonce_email_url($post->post_author, $location . "&recover_selection=1&comment_grp_check%5B$comment_id%5D=$comment_id&sk2_section=approved&sql_score_threshold=-30")) . "\r\n";
			$notify_message .= sprintf( __('Currently %s comments are waiting for approval. Please visit the moderation panel:', 'spam-karma'), $comments_waiting ) . "\r\n";
			$notify_message .= sk_nonce_email_url($post->post_author, $location . "&sk2_section=spam") . "\r\n";

//### end DdV Mods
	
			$subject = sprintf( __('[%1$s] Please moderate: "%2$s"', 'spam-karma'), get_option('blogname'), $post->post_title );
			$admin_email = get_option("admin_email");
	
			$notify_message = apply_filters('comment_moderation_text', $notify_message);
			$subject = apply_filters('comment_moderation_subject', $subject);
	
			@wp_mail($admin_email, $subject, $notify_message);
		
			return true;
	}
}

if ( ! function_exists('wp_notify_postauthor') )
{
	if (function_exists('get_comment') )
	{
		function wp_notify_postauthor($comment_id, $comment_type='') 
		{
			global $wpdb;
			
			$comment = get_comment($comment_id);
			$post    = get_post($comment->comment_post_ID);
			$user    = get_userdata( $post->post_author );
		
			if ($user->ID == $comment->user_id || '' == $user->user_email) return false; // If there's no email to send the comment to (or email's been posted by author)
		
			$comment_author_domain = gethostbyaddr($comment->comment_author_IP);
		
			$blogname = get_option('blogname');
			
			if ( empty( $comment_type ) ) $comment_type = 'comment';
			
			if ('comment' == $comment_type) {
				$notify_message  = sprintf( __('New comment on your post #%1$s "%2$s"', 'spam-karma'), $comment->comment_post_ID, $post->post_title ) . "\r\n";
				$notify_message .= sprintf( __('Author : %1$s (IP: %2$s , %3$s)', 'spam-karma'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
				$notify_message .= sprintf( __('E-mail : %s', 'spam-karma'), $comment->comment_author_email ) . "\r\n";
				$notify_message .= sprintf( __('URI    : %s', 'spam-karma'), $comment->comment_author_url ) . "\r\n";
				$notify_message .= sprintf( __('Whois  : http://ws.arin.net/cgi-bin/whois.pl?queryinput=%s', 'spam-karma'), $comment->comment_author_IP ) . "\r\n";
				$notify_message .= __('Comment: ', 'spam-karma') . "\r\n" . $comment->comment_content . "\r\n\r\n";
				$notify_message .= __('You can see all comments on this post here: ', 'spam-karma') . "\r\n";
				$subject = sprintf( __('[%1$s] Comment: "%2$s"', 'spam-karma'), $blogname, $post->post_title );
			} elseif ('trackback' == $comment_type) {
				$notify_message  = sprintf( __('New trackback on your post #%1$s "%2$s"', 'spam-karma'), $comment->comment_post_ID, $post->post_title ) . "\r\n";
				$notify_message .= sprintf( __('Website: %1$s (IP: %2$s , %3$s)', 'spam-karma'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
				$notify_message .= sprintf( __('URI    : %s', 'spam-karma'), $comment->comment_author_url ) . "\r\n";
				$notify_message .= __('Excerpt: ', 'spam-karma') . "\r\n" . $comment->comment_content . "\r\n\r\n";
				$notify_message .= __('You can see all trackbacks on this post here: ', 'spam-karma') . "\r\n";
				$subject = sprintf( __('[%1$s] Trackback: "%2$s"', 'spam-karma'), $blogname, $post->post_title );
			} elseif ('pingback' == $comment_type) {
				$notify_message  = sprintf( __('New pingback on your post #%1$s "%2$s"', 'spam-karma'), $comment->comment_post_ID, $post->post_title ) . "\r\n";
				$notify_message .= sprintf( __('Website: %1$s (IP: %2$s , %3$s)', 'spam-karma'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
				$notify_message .= sprintf( __('URI    : %s', 'spam-karma'), $comment->comment_author_url ) . "\r\n";
				$notify_message .= __('Excerpt: ', 'spam-karma') . "\r\n" . sprintf('[...] %s [...]', $comment->comment_content ) . "\r\n\r\n";
				$notify_message .= __('You can see all pingbacks on this post here: ', 'spam-karma') . "\r\n";
				$subject = sprintf( __('[%1$s] Pingback: "%2$s"', 'spam-karma'), $blogname, $post->post_title );
			}
			$notify_message .= get_permalink($comment->comment_post_ID) . "#comments\r\n\r\n";

	//### DdV Mods
				$location = get_bloginfo('wpurl') . "/wp-admin/edit.php?page=spamkarma2";
	//	echo "##" . print_r($comment_id, true) . "##" . ($location . '&recover_selection=1&comment_grp_check[' . $comment_id . ']=' . $comment_id . '&sk2_section=approved') . "**" . sk_nonce_url($location . '&recover_selection=1&comment_grp_check[' . $comment_id . ']=' . $comment_id . '&sk2_section=approved') . "**" . sk_nonce_email_url($location . '&recover_selection=1&comment_grp_check[' . $comment_id . ']=' . $comment_id . '&sk2_section=approved') . "**";
				$notify_message .= sprintf( __('To flag this comment as spam, visit: %s', 'spam-karma'), sk_nonce_email_url($post->post_author, $location . "&recover_selection=1&comment_grp_check%5B$comment_id%5D=$comment_id&sk2_section=approved&sql_score_threshold=-30")) . "\r\n";
				$notify_message .= sprintf( __('To delete this comment (without flagging it as spam), visit: %s', 'spam-karma'), get_option('siteurl').'/wp-admin/comment.php?action=cdc&c=' . $comment_id) . "\r\n";
	//###

			$wp_email = 'wordpress@' . preg_replace('#^www\.#', '', strtolower($_SERVER['SERVER_NAME']));
		
			if ( '' == $comment->comment_author ) {
				$from = "From: \"$blogname\" <$wp_email>";
				if ( '' != $comment->comment_author_email )
					$reply_to = "Reply-To: $comment->comment_author_email";
			} else {
				$from = "From: \"$comment->comment_author\" <$wp_email>";
				if ( '' != $comment->comment_author_email )
					$reply_to = "Reply-To: \"$comment->comment_author_email\" <$comment->comment_author_email>";
			}
		
			$message_headers = "MIME-Version: 1.0\n"
				. "$from\n"
				. "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";
		
			if ( isset($reply_to) )
				$message_headers .= $reply_to . "\n";
		
			$notify_message = apply_filters('comment_notification_text', $notify_message, $comment_id);
			$subject = apply_filters('comment_notification_subject', $subject, $comment_id);
			$message_headers = apply_filters('comment_notification_headers', $message_headers, $comment_id);
		
			@wp_mail($user->user_email, $subject, $notify_message, $message_headers);
		   
			return true;
		}
	
	}
	else
	{
		function wp_notify_postauthor($comment_id, $comment_type='') 
		{
				global $wpdb;
			
				$comment = $wpdb->get_row("SELECT * FROM $wpdb->comments WHERE comment_ID='$comment_id' LIMIT 1");
				$post = $wpdb->get_row("SELECT * FROM $wpdb->posts WHERE ID='$comment->comment_post_ID' LIMIT 1");
				$user = $wpdb->get_row("SELECT * FROM $wpdb->users WHERE ID='$post->post_author' LIMIT 1");
		
				if ($user->ID == $comment->user_id || '' == $user->user_email) return false; // If there's no email to send the comment to
		
				$comment_author_domain = gethostbyaddr($comment->comment_author_IP);
		
				$blogname = get_option('blogname');
				
				if ( empty( $comment_type ) ) $comment_type = 'comment';
				
				if ('comment' == $comment_type) {
						$notify_message  = sprintf( __('New comment on your post #%1$s "%2$s"', 'spam-karma'), $comment->comment_post_ID, $post->post_title ) . "\r\n";
						$notify_message .= sprintf( __('Author : %1$s (IP: %2$s , %3$s)', 'spam-karma'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
						$notify_message .= sprintf( __('E-mail : %s', 'spam-karma'), $comment->comment_author_email ) . "\r\n";
						$notify_message .= sprintf( __('URI    : %s', 'spam-karma'), $comment->comment_author_url ) . "\r\n";
						$notify_message .= sprintf( __('Whois  : http://ws.arin.net/cgi-bin/whois.pl?queryinput=%s', 'spam-karma'), $comment->comment_author_IP ) . "\r\n";
						$notify_message .= __('Comment: ', 'spam-karma') . "\r\n" . $comment->comment_content . "\r\n\r\n";
						$notify_message .= __('You can see all comments on this post here: ', 'spam-karma') . "\r\n";
						$subject = sprintf( __('[%1$s] Comment: "%2$s"', 'spam-karma'), $blogname, $post->post_title );
				} elseif ('trackback' == $comment_type) {
						$notify_message  = sprintf( __('New trackback on your post #%1$s "%2$s"', 'spam-karma'), $comment->comment_post_ID, $post->post_title ) . "\r\n";
						$notify_message .= sprintf( __('Website: %1$s (IP: %2$s , %3$s)', 'spam-karma'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
						$notify_message .= sprintf( __('URI    : %s', 'spam-karma'), $comment->comment_author_url ) . "\r\n";
						$notify_message .= __('Excerpt: ', 'spam-karma') . "\r\n" . $comment->comment_content . "\r\n\r\n";
						$notify_message .= __('You can see all trackbacks on this post here: ', 'spam-karma') . "\r\n";
						$subject = sprintf( __('[%1$s] Trackback: "%2$s"', 'spam-karma'), $blogname, $post->post_title );
				} elseif ('pingback' == $comment_type) {
						$notify_message  = sprintf( __('New pingback on your post #%1$s "%2$s"', 'spam-karma'), $comment->comment_post_ID, $post->post_title ) . "\r\n";
						$notify_message .= sprintf( __('Website: %1$s (IP: %2$s , %3$s)', 'spam-karma'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
						$notify_message .= sprintf( __('URI    : %s', 'spam-karma'), $comment->comment_author_url ) . "\r\n";
						$notify_message .= __('Excerpt: ', 'spam-karma') . "\r\n" . sprintf( __('[...] %s [...]', 'spam-karma'), $comment->comment_content ) . "\r\n\r\n";
						$notify_message .= __('You can see all pingbacks on this post here: ', 'spam-karma') . "\r\n";
						$subject = sprintf( __('[%1$s] Pingback: "%2$s"', 'spam-karma'), $blogname, $post->post_title );
				}
				$notify_message .= get_permalink($comment->comment_post_ID) . "#comments\r\n\r\n";
	
	//### DdV Mods
				$location = get_bloginfo('wpurl') . "/wp-admin/edit.php?page=spamkarma2";
	
				$notify_message .= sprintf( __('To flag this comment as spam, visit: %s', 'spam-karma'), sk_nonce_email_url($post->post_author, $location . "&recover_selection=1&comment_grp_check%5B$comment_id%5D=$comment_id&sk2_section=approved&sql_score_threshold=-30")) . "\r\n";
				$notify_message .= sprintf( __('To delete this comment (without flagging it as spam), visit: %s', 'spam-karma'), get_option('siteurl'). '/wp-admin/comment.php?action=cdc&c=' . $comment_id) . "\r\n";
	//###
			
				if ( '' == $comment->comment_author ) {
					$from = "From: \"$blogname\" <$wp_email>";
					if ( '' != $comment->comment_author_email )
						$reply_to = "Reply-To: $comment->comment_author_email";
				} else {
					$from = "From: \"$comment->comment_author\" <$wp_email>";
					if ( '' != $comment->comment_author_email )
						$reply_to = "Reply-To: \"$comment->comment_author_email\" <$comment->comment_author_email>";
				}
		
				$notify_message = apply_filters('comment_notification_text', $notify_message);
				$subject = apply_filters('comment_notification_subject', $subject);
				$message_headers = "MIME-Version: 1.0\n"
						. "$from\n"
						. "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";
				if ( isset($reply_to) )
					$message_headers .= $reply_to . "\n";
	
				$message_headers = apply_filters('comment_notification_headers', $message_headers);
		
				@wp_mail($user->user_email, $subject, $notify_message, $message_headers);
		   
				return true;
		}
	}
}

function sk_nonce_url($link, $function = 'form')
{
	 if  (function_exists('wp_nonce_url'))
 		return wp_nonce_url($link, 'sk2_' . $function);
 	else 
 		return $link;
}

function sk_nonce_email_url($uid, $link, $function = 'form')
{
	
	
	 if  (function_exists('sk_create_nonce'))
 		return $link . '&_wpnonce=' .  sk_create_nonce($uid, 'sk2_' . $function);
 	else 
 		return $link;
}


function sk_nonce_field($function = 'form')
{
	if ( function_exists('wp_create_nonce') )
	{
		//return wp_nonce_field('sk2_' . $function);
		return "<input type=\"hidden\" name=\"_wpnonce\" value=\"" .  wp_create_nonce('sk2_' . $function) . "\" />";
	}
	else
		return "";
}

// Not using pluggable override as we don't really want to change behaviours for other parts of WP:
function sk_create_nonce($uid, $action = -1) 
{	
	if (function_exists("wp_hash"))
	{
		$i = ceil(time() / 43200);
		return substr(wp_hash($i . $action . $uid), -12, 10);
	}
	else
		return "";
}


add_action('init', 'sk2_init');

add_action('comment_form', 'sk2_form_insert');
add_action('admin_menu', 'sk2_add_options');
add_action('admin_head', 'sk2_output_admin_css');
add_filter('pre_comment_approved', 'sk2_fix_approved');
add_action('comment_post', 'sk2_filter_comment');

add_action('wp_footer', 'sk2_insert_footer', 3);

?>
