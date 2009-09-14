<?php
/******************************************************************************
 Spam Karma (c) 2009 - http://code.google.com/p/spam-karma/

 This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; version 2 of the License.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

******************************************************************************/
?><?php
/*
Plugin Name: Spam Karma
Plugin URI: http://code.google.com/p/spam-karma/
Description: Ultimate Spam Killer for WordPress.<br/> Activate the plugin and go to <a href="options-general.php?page=spamkarma">Spam Karma Settings</a> to configure.
Author: dr Dave
Version: 2.4-alpha-20090801
Author URI: http://unknowngenius.com/blog/
*/

define( 'SK_TABLE_PREFIX', $table_prefix );
define( 'SK_SECOND_CHANCE_FILE', 'sk_second_chance.php');
define( 'SK_NEWS_UPDATE_CHECK_URL', 'http://wp-plugins.net/sk2/sk2_news.php');
define( 'SK_NEWS_UPDATE_INTERVAL',  86400 ); 
define( 'SK_AUTO_PURGE_INTERVAL',  600 );

if (! isset($_SERVER['PHP_SELF']))
	$_SERVER['PHP_SELF'] = @$PHP_SELF;

function sk_add_options() {
	$page = add_options_page(__('Spam Karma Options', 'spam-karma'), __('Spam Karma','spam-karma'), 'moderate_comments', 'spamkarma', 'sk_option_page');
	add_action('load-' . $page, 'sk_output_admin_load');
}

function sk_admin_init() {
	include_once(dirname(__FILE__) . '/sk_core_class.php');
	$sk_core = new SK_Core(0, true);
	// filter comments through sk plugins
	if ( isset( $_REQUEST['sk_run_filter'] ) && current_user_can('moderate_comments') ) {
		check_admin_referer('bulk-karma-filter', '_karma_filter_nonce');
		ob_flush();
		if ( isset( $_REQUEST['delete_comments'] ) ) {
			$which_plugin = $_REQUEST['bulk-karma-filter-plugins'];
			foreach( (array) $_REQUEST['delete_comments'] as $id ) {
				if ($which_plugin != 'all')
				{
					$which_plugin_obj = 0;
					foreach ($sk_core->plugins as $plugin)
						if ($plugin[2] == $which_plugin)
							$which_plugin_obj = $plugin[1];

					if (! $which_plugin_obj)
						$sk_log->log_msg(sprintf(__('Cannot find plugin: %s', 'spam-karma'), $which_plugin), 10, 0, 'web_UI');
					
					$comment_obj = new sk_comment($id, true);
					if ($which_plugin_obj->is_filter())
					{
						$sk_log->log_msg(sprintf(__('Running filter: %s on comment ID: %s', 'spam-karma'), $which_plugin_obj->name, $id), 3, $id, 'web_UI');
						$which_plugin_obj->filter_this($comment_obj);
					}
					if ($which_plugin_obj->is_treatment())
					{
						$sk_log->log_msg(sprintf(__('Running treatment: %s on comment ID %d.', 'spam-karma'),  $which_plugin_obj->name, $id), 3, $id, 'web_UI');
						$which_plugin_obj->treat_this($comment_obj);
					}
					$comment_sk_info['comment_ID'] = $id;
					$comment_sk_info['karma'] =  $comment_obj->karma;
					$comment_sk_info['karma_cmts'] =  $comment_obj->karma_cmts;
					$sk_core->set_comment_sk_info($id, $comment_sk_info);
				}
				elseif ($which_plugin == 'all')
				{
					$sk_log->log_msg(sprintf(__('Running all filters on comment ID: %s', 'spam-karma'), $id), 3, $id, 'web_UI');
					$sk_core->filter_comment($id);
					$sk_log->log_msg(sprintf(__('Running all treatments on comment ID: %s', 'spam-karma'),  $id), 3, $id, 'web_UI');
					$sk_core->treat_comment($id);
					$sk_core->set_comment_sk_info();
				}
			}
		}
	}
}

function sk_init() {
	$skdir = basename( dirname( __FILE__ ) );
	load_plugin_textdomain( 'spam-karma',
		'wp-content/plugins/' . $skdir . '/lang',
		$skdir . '/lang' );
}

function sk_option_page() {
	global $wpdb, $sk_log, $sk_settings;
	include_once(dirname(__FILE__) . '/sk_core_class.php');
	$sk_core = new SK_Core(0, true);
	

	$sk_sections = 
		array (
			'general' => __('General Settings', 'spam-karma'), 
			'blacklist' => __('Blacklist', 'spam-karma'), 
			'logs' => __('SK Logs', 'spam-karma'), 
			'about' => __('About', 'spam-karma')
		);

	if ( isset( $_REQUEST['sk_section'] ) && ! empty( $sk_sections[$_REQUEST['sk_section']] ) )
		$cur_section = $_REQUEST['sk_section'];
	else
		$cur_section = 'general';

	if ( isset( $_REQUEST ) && ( !empty( $_REQUEST['sk_section'] )
									|| (!empty($_REQUEST['advanced_tools'])) 
									|| (isset($_REQUEST['sk_settings_save']))))
	{
	//print_r($_REQUEST);
		if (function_exists('check_admin_referer'))
			check_admin_referer('sk_form');
	}

	// FORM HANDLING:
	if (isset($_REQUEST['sk_section']))
	{
		if (isset($_REQUEST['sk_core_settings_ui']) && is_array($_REQUEST['sk_core_settings_ui']))
		{
			foreach($_REQUEST['sk_core_settings_ui'] as $name => $value)
			{
				if ($value == 'checkbox')
					$value = isset($_REQUEST['sk_core_settings_checkbox'][$name]);

				$sk_settings->set_core_settings($value, $name);
			}
		}
		
		if ( (isset($_REQUEST['purge_logs']))
			|| ($sk_settings->get_core_settings('auto_purge_logs') 
				&& ($sk_settings->get_core_settings('next_auto_purge_logs') < time())))
		{
			$query = "DELETE FROM  `". SK_LOGTABLE . "` WHERE `ts`< DATE_SUB(NOW(), INTERVAL ". $sk_settings->get_core_settings('purge_logs_duration') . ' ' . $sk_settings->get_core_settings('purge_logs_unit') .") AND `level` < "  . $sk_settings->get_core_settings('purge_logs_level');
			$removed = $wpdb->query($query);
			
			if (! mysql_error())
				$sk_log->log_msg(sprintf(__ngettext('Successfully purged one log entry.', 'Successfully purged %d log entries.', $removed, 'spam-karma'), $removed), 5, 0, 'web_UI');
			else
				$sk_log->log_msg_mysql(__('Failed to purge log entries.', 'spam-karma') . "<br/><code>$query</code>", 7, 0, 'web_UI');

			$sk_settings->set_core_settings(time() + SK_AUTO_PURGE_INTERVAL, 'next_auto_purge_logs');
		}	
		
		if ( (isset($_REQUEST['purge_blacklist']))
			|| ($sk_settings->get_core_settings('auto_purge_blacklist') 
				&& ($sk_settings->get_core_settings('next_auto_purge_blacklist') < time())))
		{
			$query = ("DELETE FROM  `". SK_BLACKLIST_TABLE . "` WHERE `". $sk_settings->get_core_settings('purge_blacklist_criterion') ."`< DATE_SUB(NOW(), INTERVAL ". $sk_settings->get_core_settings('purge_blacklist_duration') . ' ' . $sk_settings->get_core_settings('purge_blacklist_unit') .") AND `score` < "  . $sk_settings->get_core_settings('purge_blacklist_score'));
			$removed = $wpdb->query($query);
			
			if (! mysql_error())
				$sk_log->log_msg(sprintf(__ngettext('Successfully purged one blacklist entry.', 'Successfully purged %d blacklist entries.', $removed, 'spam-karma'), $removed), 5, 0, 'web_UI');
			else
				$sk_log->log_msg_mysql(__('Failed to purge blacklist entries.', 'spam-karma'). '<br/>' . __('Query: ', 'spam-karma'). "<code>$query</code>", 7, 0, 'web_UI');

			$sk_settings->set_core_settings(time() + SK_AUTO_PURGE_INTERVAL, 'next_auto_purge_blacklist');
		}

		if ( (isset($_REQUEST['purge_spamlist']))
			|| ($sk_settings->get_core_settings('auto_purge_spamlist')
				&& ($sk_settings->get_core_settings('next_auto_purge_spamlist') < time())))
		{	
			$spam_table = "`" . SK_SPAM_TABLE . "`";
			$cmt_table = "`$wpdb->comments`";
			$query = "DELETE  $cmt_table, $spam_table FROM $cmt_table LEFT JOIN $spam_table ON $spam_table.`comment_ID` = $cmt_table.`comment_ID` WHERE ($cmt_table.`comment_approved` = '0' OR $cmt_table.`comment_approved` = 'spam') AND $cmt_table.`comment_date_gmt` < DATE_SUB('". $gmt = gmstrftime("%Y-%m-%d %H:%M:%S") ."', INTERVAL ". $sk_settings->get_core_settings('purge_spamlist_duration') . ' ' . $sk_settings->get_core_settings('purge_spamlist_unit') .')';
			$removed = $wpdb->query($query);
			
			if (! mysql_error())
				$sk_log->log_msg(sprintf(__ngettext('Successfully purged one comment spam entry.', "Successfully purged %d comment spam entries.", $removed, 'spam-karma'), $removed), 5, 0, 'web_UI');
			else
				$sk_log->log_msg_mysql(__('Failed to purge comment spam entries.', 'spam-karma'). '<br/>' . __('Query: ', 'spam-karma'). "<code>$query</code>", 7, 0, 'web_UI');

			$sk_settings->set_core_settings(time() + SK_AUTO_PURGE_INTERVAL, 'next_auto_purge_spamlist');
		}

		
		if ($cur_section == 'approved' || $cur_section == 'spam')
		{
			if (isset($_REQUEST['recover_selection']) && isset($_REQUEST['comment_grp_check']))
			{
				foreach($_REQUEST['comment_grp_check'] as $id => $spam_id)
				{
					
					$sk_core->load_comment($id);
				
					if ($cur_section == 'spam')
					{
						$sk_core->cur_comment->set_karma(15, 'web_UI', __('Manually recovered comment.', 'spam-karma'));
						do_action('wp_set_comment_status', $sk_core->cur_comment->ID);
					}
					else
						$sk_core->cur_comment->set_karma(-30, 'web_UI', __('Manually spanked spam.', 'spam-karma'));
		
					$sk_core->treat_comment();
					$sk_core->set_comment_sk_info();			
				
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
						$sk_core->load_comment($mod_cmt->comment_ID);
						//$sk_core->cur_comment->set_DB_status('spam', 'web_UI', true);
						$sk_core->cur_comment->set_karma(-15, 'web_UI', __('Manually confirmed moderations.', 'spam-karma'));
						$sk_core->treat_comment();
						$sk_core->set_comment_sk_info();
					}
				else
					$sk_log->log_msg_mysql(__("Can't fetch moderated comments.", 'spam-karma'), 7, 0, 'web_UI');
			}
			elseif (isset($_REQUEST['sk_run_filter']))
			{
				if (isset($_REQUEST['comment_grp_check']))
				{
					$which_plugin = $_REQUEST['action_param']['which_plugin'];
					
					if ($which_plugin != 'all')
					{
						$which_plugin_obj = 0;
						foreach ($sk_core->plugins as $plugin)
							if ($plugin[2] == $which_plugin)
								$which_plugin_obj = $plugin[1];
		
						if (! $which_plugin_obj)
							$sk_log->log_msg(__('Cannot find plugin: ', 'spam-karma') . $which_plugin, 10, 0, 'web_UI');
					}
					
					foreach($_REQUEST['comment_grp_check'] as $id => $spam_id)
					{
						if ($which_plugin == 'all')
						{
							$sk_log->log_msg(__('Running all filters on comment ID: ', 'spam-karma') . $id, 3, $id, 'web_UI');
							$sk_core->filter_comment($id);				
							$sk_log->log_msg(__('Running all treatments on comment ID: ', 'spam-karma') .  $id, 3, $id, 'web_UI');
							$sk_core->treat_comment($id);
							$sk_core->set_comment_sk_info();
						}
						else
						{
							$comment_obj = new sk_comment($id, true);
							if ($which_plugin_obj->is_filter())
							{
								$sk_log->log_msg(sprintf(__('Running filter: %s on comment ID: %s', 'spam-karma'), $which_plugin_obj->name, $id), 3, $id, 'web_UI');
								$which_plugin_obj->filter_this($comment_obj);
							}
							if ($which_plugin_obj->is_treatment())
							{
								$sk_log->log_msg(sprintf(__('Running treatment: %s on comment ID %d.', 'spam-karma'),  $which_plugin_obj->name, $id), 3, $id, 'web_UI');
								$which_plugin_obj->treat_this($comment_obj);
							}
							$comment_sk_info['comment_ID'] = $id;
							$comment_sk_info['karma'] =  $comment_obj->karma;
							$comment_sk_info['karma_cmts'] =  $comment_obj->karma_cmts;
							$sk_core->set_comment_sk_info($id, $comment_sk_info);
						}
					}
				}
				else
					$sk_log->log_msg(__('No comment selected: cannot run plugins.', 'spam-karma'), 6, 0, 'web_UI');
			}
			elseif (isset($_REQUEST['remove_checked']) && isset($_REQUEST['comment_grp_check']))
			{
				foreach($_REQUEST['comment_grp_check'] as $id => $spam)
				{
					$id = mysql_escape_string($id);
					if ($wpdb->query("DELETE FROM  `$wpdb->comments` WHERE  `$wpdb->comments`.`comment_ID` = '$id'"))
						$wpdb->query("DELETE FROM `". SK_SPAM_TABLE . "` WHERE `". SK_SPAM_TABLE . "`.`comment_ID` = '$id'");
					if (! mysql_error())
						$sk_log->log_msg(__('Successfully removed spam entry ID: ', 'spam-karma'). $id, 4, 0, 'web_UI');
					else
						$sk_log->log_msg_mysql(__('Failed to remove spam entry ID: ', 'spam-karma') . $id, 7, 0, 'web_UI');
				}
			}
		}
	}
	// SECTION DISPLAY

	$last_spam_check = $sk_settings->get_core_settings('last_spam_check');
	$new_spams = $wpdb->get_var("SELECT COUNT(*) FROM `$wpdb->comments` WHERE (`comment_approved`= '0' OR `comment_approved` = 'spam') AND `comment_date_gmt` > " . gmstrftime("'%Y-%m-%d %H:%M:%S'", (int) $last_spam_check));
	$cur_moderated = $wpdb->get_var("SELECT COUNT(*) FROM `$wpdb->comments` WHERE `comment_approved`= '0'");	

	if ($new_spams || $cur_moderated)
	{
		if ($new_spams && $cur_moderated)
			$new_spams = " ($new_spams / <span style=\"color:red;\">$cur_moderated</span>)";
		elseif ($new_spams)
			$new_spams = " ($new_spams)";
		else
			$new_spams = " <span style=\"color:red;\">($cur_moderated " . __('mod.', 'spam-karma') . ')</span>';
	}
	else
		$new_spams = '';

	$last_approved_check = $sk_settings->get_core_settings('last_approved_check');
	$new_approved = $wpdb->get_var("SELECT COUNT(*) FROM `$wpdb->comments` WHERE (`comment_approved`= '1') AND `comment_date_gmt` > " . gmstrftime("'%Y-%m-%d %H:%M:%S'", (int) $last_approved_check));
	if ($new_approved)
		$new_approved = " ($new_approved)";
	else
		$new_approved = '';

?>
	<ul id="sk_menu">
<?php
	$url = $_SERVER['PHP_SELF'] . '?page=' . $_REQUEST['page'] . '&sk_section=';
	foreach ($sk_sections as $section => $name)
	{
		if ($cur_section == $section)
			echo "<li class=\"current\">$name</li>";
		else
			echo '<li><a href="' . sk_nonce_url($url . $section) . "\">$name</a></li>";
	}
?>
	</ul>
<?php
	
	switch ($cur_section)
	{
		case 'logs':
			
			$log_rows = $wpdb->get_results("SELECT *, UNIX_TIMESTAMP(`ts`) AS `ts2` FROM `". SK_LOGTABLE . "` WHERE 1 ORDER BY `ts` DESC, `id` DESC LIMIT 200");
			if (mysql_error())
				$sk_log->log_msg_mysql(__("Can't fetch logs.", 'spam-karma'), 7, 0, 'web_UI');

?>
		<div class="wrap sk_first">
		<h2><?php _e('SK Logs', 'spam-karma'); ?></h2>			
			<form id="sk_logs_remove_form" name="sk_logs_remove_form" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=spamkarma&sk_section=<?php echo $cur_section; ?>">
			<fieldset class="options">
			<?php echo sk_nonce_field(); ?>
			<legend><?php _e('Purge', 'spam-karma'); ?></legend>
			<p class="sk_form"><?php
			echo '<input type="submit" name="purge_logs" id="purge_logs" value="' . __('Remove logs:', 'spam-karma') . '" /> ' . sprintf(__('older than %s %s and with a level inferior to %s (%s do it automatically from now on).', 'spam-karma'), sk_settings_ui('purge_logs_duration'), sk_settings_ui('purge_logs_unit'), sk_settings_ui('purge_logs_level'), sk_settings_ui('auto_purge_logs'));
			?></p>
			</fieldset>
			</form>
			<p><em><?php printf(__('Displaying %i most recent log entries', 'spam-karma'), 200); ?></em>
			<table id="sk_log_list" width="100%" cellpadding="3" cellspacing="3"> 
			<tr>
				<th scope="col"><?php _e('ID', 'spam-karma'); ?></th>
				<th scope="col"><?php _e('Level', 'spam-karma'); ?></th>
				<th scope="col"><?php _e('Message', 'spam-karma'); ?></th>
				<th scope="col"><?php _e('Component', 'spam-karma'); ?></th>
				<th scope="col"><?php _e('How Long Ago', 'spam-karma'); ?></th>
			</tr>
			<?php
			foreach($log_rows as $row)
			{
				echo "<tr class=\"sk_level_$row->level\">";
				echo "<td>$row->id</td>";
				echo "<td>$row->level</td>";
				echo "<td>$row->msg</td>";
				echo "<td>$row->component</td>";
				echo "<td>" . sk_table_show_hide(sk_time_since($row->ts2), $row->ts) . "</td>";
				echo "</tr>";
			}
			?>
		</table></p>
		<?php
		break;	
				
		case 'blacklist':
			$sk_settings->set_core_settings(time(), 'last_spam_check');

			if (isset($_REQUEST['sk_blacklist_add']))
			{
				$sk_blacklist->add_entry($_REQUEST['add_blacklist_type'], $_REQUEST['add_blacklist_value'], $_REQUEST['add_blacklist_score'], 'yes', 'user');
			}
			elseif (isset($_REQUEST['sk_edit_rows']) && isset($_REQUEST['blacklist']))
			{
				foreach($_REQUEST['blacklist'] as $id => $entry)
				{
					$id = mysql_escape_string($id);
					$entry['score'] = (int) $entry['score'];
					$wpdb->query("UPDATE `" . SK_BLACKLIST_TABLE . "` SET `type` = '" . sk_escape_form_string($entry['type']) . "', `value` = '" . sk_escape_form_string($entry['val']) . "', `score` = " . $entry['score'] . ", `user_reviewed` = 'yes' WHERE `id` = '$id'");
					if (mysql_error())
						$sk_log->log_msg_sql(__('Failed to update blacklist entry ID: ', 'spam-karma') .  $id, 8, 0, 'web_UI');
					else
						$sk_log->log_msg(__('Succesfully updated blacklist entry ID: ', 'spam-karma') . $id, 4, 0, 'web_UI');
				}
			}
			elseif (isset($_REQUEST['remove_checked']) && isset($_REQUEST['blacklist_grp_check']))
			{
				foreach($_REQUEST['blacklist_grp_check'] as $id => $spam)
				{
					$id = mysql_escape_string($id);
					$wpdb->query("DELETE FROM  `". SK_BLACKLIST_TABLE . "` WHERE `id` = $id");
					if (! mysql_error())
						$sk_log->log_msg(__('Successfully removed blacklist entry ID: ', 'spam-karma') . $id, 4, 0, 'web_UI');
					else
						$sk_log->log_msg_mysql(__('Failed to remove blacklist entry ID: ', 'spam-karma') . $id, 7, 0, 'web_UI');
				}
			}
	//	print_r($_REQUEST);
		
			if (! empty($_REQUEST['sk_show_number']))
				$show_number = $_REQUEST['sk_show_number'];
			else
				$show_number = 20;

			if (isset($_REQUEST['sk_match']) && ($_REQUEST['sk_match'] == 'true'))
				$match_mode = true;
			else
				$match_mode = false;
				
		$match_value = @$_REQUEST['sk_match_value'];
		if (isset($_REQUEST['sk_match_type']))
			$match_type = $_REQUEST['sk_match_type'];
		else
			$match_type = 'all';

		if($match_mode)
			$blacklist_rows = $sk_blacklist->match_entries($match_type, $match_value, false, 0, $show_number);
		else
			$blacklist_rows = $wpdb->get_results("SELECT * FROM `". SK_BLACKLIST_TABLE . "` WHERE 1 ORDER BY `added` DESC LIMIT $show_number");
		
		sk_echo_check_all_JS();

		$blacklist_types = 
			array (
				'ip_black' => __('IP Blacklist', 'spam-karma'), 
				'ip_white' => __('IP Whitelist', 'spam-karma'), 
				'domain_black' => __('Domain Blacklist', 'spam-karma'), 
				'domain_white' => __('Domain Whitelist', 'spam-karma'), 
				'domain_grey' => __('Domain Greylist', 'spam-karma'), 
				'regex_black' => __('Regex Blacklist', 'spam-karma'), 
				'regex_white' => __('Regex Whitelist', 'spam-karma'), 
				'regex_content_black' => __('Regex Content Blacklist', 'spam-karma'), 
				'regex_content_white' => __('Regex Content Whitelist', 'spam-karma'), 
				'rbl_server' => __('RBL Server (IP)', 'spam-karma'), 
				'rbl_server_uri' => __('RBL Server (URI)', 'spam-karma'), 
				'kumo_seed' => __('Kumo Seed', 'spam-karma')
			);
?>
			<div class="wrap sk_first">
			<h2><?php _e('Blacklist', 'spam-karma'); ?></h2>
			<fieldset class="options">
			<legend><?php _e('Add', 'spam-karma'); ?></legend>
			<form id="sk_blacklist_add_form" name="sk_blacklist_add_form" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=spamkarma&sk_section=<?php echo $cur_section; ?>">
			<p class="sk_form">
						<?php echo sk_nonce_field(); ?>
			<select name="add_blacklist_type" id="add_blacklist_type"><?php
				$default = 'ip_black';
				foreach($blacklist_types as $type => $type_caption)
					if ($default == $type)
						echo "<option value=\"$type\" selected>$type_caption</option>";
					else
						echo "<option value=\"$type\">$type_caption</option>";
			?></select>: <input type="text" size="20" name="add_blacklist_value" id="add_blacklist_value" value="" /> <input type="submit" name="sk_blacklist_add" value="<?php _e('Add entry', 'spam-karma'); ?>" />   (<?php _e('Score: ', 'spam-karma'); ?><input type="text" size="3" name="add_blacklist_score" id="add_blacklist_score" value="100" />)</p>
			</form>
			</fieldset>

			<form id="sk_blacklist_remove_form" name="sk_blacklist_remove_form" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=spamkarma&sk_section=<?php echo $cur_section; ?>">
			<fieldset class="options">
			<legend><?php _e('Show', 'spam-karma'); ?></legend>
			<p class="sk_form">
			<?php
			echo sk_nonce_field();

			printf(__("%sShow%s last %s entries.", 'spam-karma'), '<input type="submit" name="sk_show_last"  id="sk_show_last" value="', '" />', '<input type="text" size="3" name="sk_show_number" id="sk_show_number" value="' . $show_number . '" />');
			?></p>
			<p class="sk_form"><input type="checkbox" name="sk_match"  id="sk_match" value="true" <?php if ($match_mode) echo 'checked'; ?> /> <?php _e('Match', 'spam-karma'); ?> <input type="text" size="10" name="sk_match_value" id="sk_match_value" value="<?php echo $match_value; ?>" /> <select name="sk_match_type" id="sk_match_type"><?php
			$options = 
				array(
					'all' => __('All', 'spam-karma'), 
					'ip' => __('IP', 'spam-karma'), 
					'url' => __('URL', 'spam-karma'), 
					'regex_content_match' => __('Content', 'spam-karma'), 
					'rbl_server' => __('RBL Server', 'spam-karma'), 
					'kumo_seed' => __('Kumo Seed', 'spam-karma'), 
					'regex' => __('Regex string (non-interpreted)', 'spam-karma')
				);
			foreach ($options as $key => $val)
			{
				echo "<option value=\"$key\"";
				if ($key == $match_type)
					echo ' selected';
				echo ">$val</option>";
			}
			?></select></p>
			</fieldset>
			<fieldset class="options">
			<legend><?php _e('Remove', 'spam-karma'); ?></legend>
			<p class="sk_form"><?php
			printf(__('%sRemove entries:%s %s more than %s ago and with a score inferior to %s (%s do it automatically from now on).', 'spam-karma'), '<input type="submit" name="purge_blacklist" id="purge_blacklist" value="', '" /> ', sk_settings_ui('purge_blacklist_criterion'), sk_settings_ui('purge_blacklist_duration') . sk_settings_ui('purge_blacklist_unit'),  sk_settings_ui('purge_blacklist_score'), sk_settings_ui('auto_purge_blacklist')); 
			?></p>

			<p class="sk_form"><input type="submit" name="remove_checked" id="remove_checked" value="<?php _e('Remove Selected Entries', 'spam-karma'); ?>" /> <a href="javascript:;" onclick="checkAll(document.getElementById('sk_blacklist_remove_form')); return false; " />(<?php _e('Invert Checkbox Selection', 'spam-karma'); ?>)</a></p>
			</fieldset>
			<fieldset class="options">
			<legend><?php
			if (! $match_mode)
				printf(__('Last %d Entries', 'spam-karma'), $show_number);
			else
				echo __('Entries Matching ', 'spam-karma'), "<em>$match_value</em>";
			?></legend>
			<p><table id="sk_spam_list" width="100%" cellpadding="3" cellspacing="3"> 
			<tr>
				<th scope="col"><?php _e('ID', 'spam-karma'); ?></th>
				<th scope="col"><?php _e('Type', 'spam-karma'); ?></th>
				<th scope="col"><?php _e('Value', 'spam-karma'); ?></th>
				<th scope="col"><?php _e('Score', 'spam-karma'); ?></th>
				<th scope="col"><?php _e('How long ago', 'spam-karma'); ?></th>
				<th scope="col"><?php _e('Used', 'spam-karma'); ?></th>
			</tr>
	<?php
		if (isset($_REQUEST['sk_edit_mode']) && ($_REQUEST['sk_edit_mode'] == "true"))
			$edit_mode = true;
		else
			$edit_mode = false;
		
		echo "<input type=\"hidden\" name=\"sk_edit_mode\" id=\"sk_edit_mode\" value=\"$edit_mode\" />";
		echo "<input type=\"submit\" name=\"switch_mode\" id=\"switch_mode\" value=\"";
		if ($edit_mode)
			_e('Switch to view mode', 'spam-karma');
		else
			_e('Switch to edit mode', 'spam-karma'); 
		echo "\" onclick=\"this.form['sk_edit_mode'].value = " . ($edit_mode ? "false" : "true") . ";\" />";
		
		if (is_array($blacklist_rows))
			foreach ($blacklist_rows as $row)
			{
				if ($row->score < 30)
					$color = 'rgb(120, 120, 120)';
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
					$color = 'rgb(90, 150, 250)';
				}
				else
				{
					$color = 'rgb(90, 150, 256)';
				}
				echo "<tr style=\"background-color: $color;\">";
				echo "<th scope=\"row\"><input type=\"checkbox\" name=\"blacklist_grp_check[$row->id]\" id=\"blacklist_grp_check[$row->id]\" value=\"true\" /> $row->id</th>";
				echo "<td>";
				if (! isset($blacklist_types[$row->type]))
					$blacklist_types[$row->type] = __('Unknown', 'spam-karma') . " (" . $row->type . ")";
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
				echo '</td>';
				echo '<td>';
				if ($edit_mode)
					echo "<input type=\"text\" name=\"blacklist[$row->id][score]\" id=\"blacklist[$row->id][score]\" value=\"$row->score\" size=\"3\">";
				else
					echo $row->score;
				echo "</td>";
				echo "<td>" . sk_table_show_hide(sk_time_since(strtotime($row->last_used)), __('Added: ', 'spam-karma') . $row->added . '<br/>' . __('Last Used: ', 'spam-karma') . $row->last_used) . '</td>';
				echo '<td>' . ($row->used_count+1) . '</tr>';
				echo '</tr>';
			}
?>
			</table></p>
			<?php
			if ($edit_mode)
				echo '<p class="submit"><input type="submit" name="sk_edit_rows" id="sk_edit_rows" value="' . __('Save Changes', 'spam-karma') . '" /></p>';
			?>
			</fieldset>
			</form>
			</div>
<?php
		break;
		
		// GENERAL SETTINGS SCREEN
		case 'general':
		default:			
			$sk_core->save_UI_settings($_REQUEST);

			if (isset($_REQUEST['advanced_tools']))
				$sk_core->advanced_tools($_REQUEST);
			
			$sk_core->update_SQL_schema();
			$sk_core->update_components();

			// GET NEWS
			if ($sk_settings->get_core_settings('next_news_update') < time())
			{
				$url = SK_NEWS_UPDATE_CHECK_URL . '?sk_version=' . urlencode(SK_VERSION) . '&sk_release=' . urlencode(SK_RELEASE) . '&sk_lang=' . urlencode(WPLANG);
				if ($update_file = sk_get_url_content($url))
				{
					if (is_array($news_array = unserialize($update_file)))
					{
						$new_news = array();
						if (! is_array($old_news = $sk_settings->get_core_settings('news_archive')))
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
					
						$sk_settings->set_core_settings($old_news, 'news_archive');
						if (count($new_news) > 0)
						{
							echo '<div class="wrap sk_first"><h2>' . __('News', 'spam-karma') . '</h2>';
							foreach ($new_news as $ts => $news_item)
							{
								echo '<div class="news_item';
								if (@$news_item['level'] > 0)
									echo ' sk_level_' . $news_item['level'];
								echo '">';
								echo $news_item['msg'];
								echo '<div class="news_posted">' . sprintf(__('Posted %s ago', 'spam-karma'), sk_time_since($ts)) . '</div> ';
								echo '</div>';
							}
							echo '</div>';
						}
						$sk_log->log_msg(__('Checked news from: ', 'spam-karma') . "<em>$url</em><br/>" . sprintf(__ngettext('One new news item, %d total', '%d new news items, %d total', count($new_news), 'spam-karma'), count($new_news), count($old_news)), 3, 0, 'web_UI');
					}
					else
						$sk_log->log_msg(__('Cannot unserialize news array from URL: ', 'spam-karma') . "<em>$url</em>", 8, 0, 'web_UI');
				}
				else
					$sk_log->log_msg(__('Cannot load news from URL: ', 'spam-karma') . "<em>$url</em>", 7, 0, 'web_UI');

				$sk_settings->set_core_settings(time() + SK_NEWS_UPDATE_INTERVAL, 'next_news_update');
			}
			
			if ($sk_settings->get_core_settings('init_install') < 1)
			{
				echo '<div class="wrap sk_first">';
				$sk_log->log_msg(__('Running first-time install checks...', 'spam-karma'), 4, 0, 'web_UI', true, false);
				echo '<br/>';
				$sk_core->advanced_tools(array('check_comment_form' => true));
				$sk_settings->set_core_settings(1, 'init_install');
				echo '</div>';
			}
?>
		<div class="wrap sk_first"><h2><?php _e('Stats', 'spam-karma'); ?></h2>
		<ul>
		<li><?php _e('Total Spam Caught: ', 'spam-karma'); ?><strong><?php echo $hell_count = (int) $sk_settings->get_stats('hell'); ?></strong> <?php 
		if ($hell_count > 0)
			echo ' (' . __('average karma: ', 'spam-karma') . round((int) $sk_settings->get_stats('hell_total_karma') / $hell_count, 2) . ')'; ?></li>
		<li><?php _e('Total Comments Approved: ', 'spam-karma'); ?><strong><?php echo $paradise_count = (int) $sk_settings->get_stats('paradise'); ?></strong><?php 
		if ($paradise_count > 0)
			echo ' (' . __('average karma: ', 'spam-karma') . round((int) $sk_settings->get_stats('paradise_total_karma') / $paradise_count, 2) . ')'; ?></li>
		<li><?php _e('Total Comments Moderated: ', 'spam-karma'); ?><strong><?php echo (int) $sk_settings->get_stats('purgatory'); ?></strong> <?php 
		if ($cur_moderated)
			printf('(' . __ngettext('currently %s%d waiting%s', 'currently %s%d waiting%s', $cur_moderated, 'spam-karma') . ')', '<a href="' . sk_nonce_url('options-general.php?page=' . $_REQUEST['page'] . '&sk_section=spam') . '">', $cur_moderated, '</a>');
			
			?></li>
		<li><?php _e('Current Version: ', 'spam-karma'); ?><strong><?php echo '2.' . SK_VERSION . ' ' . SK_RELEASE; ?></strong></li>
		</ul>
		</div>
<?php
		$sk_core->output_UI();
?>
	<div class="wrap">
	<h2><?php _e('Advanced Options', 'spam-karma'); ?></h2>
	<form name="sk_advanced_tools_form" id="sk_advanced_tools_form" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=spamkarma&sk_section=<?php echo $cur_section; ?>">
				<?php echo sk_nonce_field(); ?>
	<input type="hidden" name="advanced_tools" id="advanced_tools" value="true">
<script type="text/javascript">
<!--

function toggleAdvanced(mybutton, myid)
{
	var node = document.getElementById(myid);

	if(node == null) 
	{
		alert('<?php _e('Bad ID', 'spam-karma'); ?>');
		return;
	}

	if(node.className.match(/\bshow\b/) != null)
	{
		node.className = node.className.replace(/\bshow\b/, "hide");
		mybutton.innerHTML = "<?php _e('Show Advanced Options', 'spam-karma'); ?>";
	} 
	else	if(node.className.match(/\bhide\b/) != null)
		{
			node.className = node.className.replace(/\bhide\b/, "show"); 
			mybutton.innerHTML = "<?php _e('Hide Advanced Options', 'spam-karma'); ?>";
		}
}

//-->
</script>
		<fieldset class="themecheck">
			<p><button name="advance_toggle" id="advance_toggle" onclick="toggleAdvanced(this, 'sk_settings_pane');return false;"><?php _e('Show Advanced Settings', 'spam-karma'); ?></button> <i><?php _e('Will show/hide advanced options in the form above', 'spam-karma'); ?></i></p>
		</fieldset>
		<fieldset class="dbtools">
			<legend><?php _e('Database Tools', 'spam-karma'); ?></legend>
		    <p><input type="submit" id="force_sql_update" name="force_sql_update" value="<?php _e('Force MySQL updates', 'spam-karma'); ?>"> 
		    <input type="submit" id="reinit_plugins" name="reinit_plugins" value="<?php _e('Reinit Plugins', 'spam-karma'); ?>">
		    <input type="submit" id="reset_all_tables" name="reset_all_tables" onclick="javascript:return confirm('<?php _e('Do you really want to reset all SK tables.', 'spam-karma'); ?>');" value="<?php _e('Reset All Tables', 'spam-karma'); ?>">
		  
		  <input type="submit" id="reinit_all" name="reinit_all" onclick="javascript:return confirm('<?php _e('Do you really want to reset all SK settings?', 'spam-karma'); ?>');" value="<?php _e('Reset to Factory Settings', 'spam-karma'); ?>"></p>
		</fieldset>
		
		<fieldset class="themecheck">
			<legend><?php _e('Theme Check', 'spam-karma'); ?></legend>
			<p><?php
			_e('SK will not work properly if your theme is not 100% 1.5-compatible. In particular, oftentimes, the comment form of some custom themes does not contain the proper code to work with 1.5 plugins. For more details and a guide on how to fix, please <a href="http://wp-plugins.net/wiki/index.php?title=sk_Theme_Compatibility">check out the wiki</a>.', 'spam-karma'); 
			echo '<em>' . __('You do not have to worry about this if you are using a standard out-of-the-box 1.5 install and the theme that came with it.', 'spam-karma') . '</em>'; 
			?></p>
		    <ul>
		    <li><input type="submit" id="check_comment_form" name="check_comment_form" value="<?php _e('Theme Compatibility Check', 'spam-karma'); ?>"> (<?php _e("attempts to examine your theme's files and check for compatibility", 'spam-karma'); ?>).</form></li>
		    <li><strong><?php _e('Advanced Compatibility Check', 'spam-karma'); ?></strong> <i><?php _e('Enter the URL of a page on your blog where the comment form appears (most likely the URL to any single entry, or the URL to your pop-up comment form if you are using the pop-up view) and click Submit', 'spam-karma'); ?></i><br/>
		   	<form name="sk_advanced_tools_form_2" id="sk_advanced_tools_form_2" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=spamkarma&sk_section=<?php echo $cur_section; ?>">
	<input type="hidden" name="advanced_tools" id="advanced_tools" value="true"><input type="text" id="check_comment_form_2_url" name="check_comment_form_2_url" size="30"> 
						<?php echo sk_nonce_field(); ?>
			<input type="submit" id="check_comment_form_2" name="check_comment_form_2" value="<?php _e('Submit', 'spam-karma'); ?>"></li>
		    </ul>
		</fieldset>
	</form>
	</div>
<?php
		break;
	
		case 'about':
			include_once(dirname(__FILE__) .'/sk_about.php');
			return;
		break;
	}
	$sk_settings->save_settings();	

	// DEBUG
	/* No longer necessary...
	<div class="wrap">
	<?php _e('Log Dump: ', 'spam-karma'); ?><br/>
	<?php $sk_log->dump_logs(); ?>
	</div>
	*/
}

function sk_submit_comments_to_plugins() {
	include_once(dirname(__FILE__) . '/sk_core_class.php');
	$sk_core = new SK_Core(0, true);
	?><p class="sk_form"><?php
	wp_nonce_field('bulk-karma-filter', '_karma_filter_nonce');
	$select = '<select name="bulk-karma-filter-plugins" id="bulk-karma-filter-plugins">';
	$select .= '<option value="all" selected>' . __('All Spam Karma plugins', 'spam-karma') . '</option>';
	foreach ($sk_core->plugins as $plugin)
		$select .= "<option value=\"$plugin[2]\">". $plugin[1]->name . "</option>\n";
	$select .= '</select>';
	printf(__('Run selected entries through %3$s %1$sApply%2$s', 'spam-karma'), '<input type="submit" name="sk_run_filter" class="button-secondary" id="sk_run_filter" value="', '" />', $select);
	echo '</p>';
}

function sk_table_show_hide($show, $hide) {
	global $sk_settings;
	if ($sk_settings->get_core_settings('hover_in_tables'))
	{
		return "<div class=\"show_hide_details\"><span class=\"show_hide_switch\">$show</span><p>$hide</p></div>";
	}
	else
	{
		return "<div class=\"no_show_hide\"><p>$show</p><p>$hide</p></div>";
	}
}

function sk_output_admin_print() {
	?>
	<script type="text/javascript">
	// <![CDATA[
	(function () {
		var head = document.getElementsByTagName("head")[0];
		if (head) {
			var scriptStyles = document.createElement("link");
			scriptStyles.rel = "stylesheet";
			scriptStyles.type = "text/css";
			scriptStyles.href = "<?php echo WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)) . '/css/sk-admin-script-styles.css'; ?>";
			head.appendChild(scriptStyles);
		}
	}());

	if ( 'undefined' != typeof jQuery )
	jQuery(function(j) {
		j('.karma-details').hide();
		j('.column-comment').hover(
			function() { // over 
				j('.karma-details', this).fadeIn('slow');
			},
			function() { // out
				j('.karma-details', this).fadeOut('slow');
			}
		);
	});
	// ]]>
	</script>
	<?php 
}

function sk_output_admin_load() {
	wp_enqueue_style('sk-css', WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)) . '/css/sk-admin-styles.css');
	wp_enqueue_script('jquery');
}

function sk_settings_ui($name, $type = false, $options_size = false) {
	global $sk_settings;
	$str = '';
	
	if (! $type)
	{
		$type = @$sk_settings->core_defaults[$name]['type'];
		if (($type == 'menu') || ($type == 'select'))
			$options_size = @$sk_settings->core_defaults[$name]['options'];
		elseif ($type == 'text')
			$options_size = max(@$sk_settings->core_defaults[$name]['size'], 1);	
	}
	
	$value = $sk_settings->get_core_settings($name);
	
	switch ($type)
	{
		case 'checkbox':
		case 'check':
			$str .= "<input type=\"checkbox\" name=\"sk_core_settings_checkbox[$name]\" id=\"sk_core_settings_checkbox[$name]\" ";
			if ($value)
				$str .= 'checked ';
			$str .= '/>';
			$str .= "<input type=\"hidden\" name=\"sk_core_settings_ui[$name]\" id=\"sk_core_settings_ui[$name]\" value=\"checkbox\" />";
		break;
		
		case 'text':
			$str .= "<input type=\"text\" name=\"sk_core_settings_ui[$name]\" id=\"sk_core_settings_ui[$name]\" value=\"". str_replace("\"", "&#34;", $value) . "\" size=\"$options_size\" />";
		break;
		
		case 'menu':
		case 'select':

			$str .= "<select name=\"sk_core_settings_ui[$name]\" id=\"sk_core_settings_ui[$name]\">";
			
			foreach ($options_size as $key => $text)
			{
				$key = str_replace('"', '&#34;', $key);
				if ($value == $key)
					$str .= "<option value=\"$key\" selected>" . __($text, 'spam-karma') . '</option>';
				else
					$str .= "<option value=\"$key\">" . __($text, 'spam-karma') . '</option>';
			}
			
			$str .= '</select>';
		break;
		
		default:
			$str .= '<strong>' . __("Can't render UI control: ", 'spam-karma') . "$name</strong><br/>";
		break;
	}
	
	return $str;
}

function sk_echo_check_all_JS() {
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

function sk_form_insert($id = 0) {
	global $sk_settings;
	
	if (! $id)
	{
		global $post;
		$id = $post->ID;
	}
	require_once(dirname(__FILE__) .'/sk_core_class.php');
	$sk_core = new SK_Core(0, false);
	$sk_core->form_insert($id);
	$sk_settings->save_settings();	
}

function sk_fix_approved($approved) {
// only way to prevent notification
	return 'spam';
}

function sk_filter_comment($comment_ID) {
	include_once(dirname(__FILE__) .'/sk_core_class.php');

	if (! $comment_ID)
	{
		$sk_log->log_msg(__('Structural failure: no comment ID sent to comment hook', 'spam-karma'), 10, 0, 'web_UI', true, false);
		die(__('Aborting Spam Karma', 'spam-karma'));
	}
	$sk_core = new SK_Core($comment_ID, false);
	$sk_core->process_comment();

	$approved = $sk_core->cur_comment->approved;

	$sk_settings->save_settings();	
	// should also save/display logs here...
	
	// doing notification ourselves (since we killed WP's)
	if ($approved  == 'spam')
	{ // your adventure stops here, cowboy...
		header('HTTP/1.1 403 Forbidden');
		header('Status: 403 Forbidden');
		_e('Sorry, but your comment has been flagged by the spam filter running on this blog: this might be an error, in which case all apologies. Your comment will be presented to the blog admin who will be able to restore it immediately.<br/>You may want to contact the blog admin via e-mail to notify him.', 'spam-karma');
		
//		echo '<!-- ';
//		$sk_log->dump_logs();
//		echo '-->';
		die();
	}
	else
	{
		if ( '0' == $approved )
		{
			if ($sk_core->cur_comment->can_unlock())
			{
				// redirect to Second Chance page
                header('Expires: Mon, 26 Aug 1980 09:00:00 GMT');
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');

				$location = get_bloginfo('wpurl') .  '/' . strstr(str_replace("\\", '/', dirname(__FILE__)), 'wp-content/') . '/' . SK_SECOND_CHANCE_FILE ."?c_id=$comment_ID&c_author=" . urlencode($sk_core->cur_comment->author_email);

                //$location = str_replace($_SERVER['DOCUMENT_ROOT'], '/', dirname(__FILE__)) . '/' . SK_SECOND_CHANCE_FILE ."?c_id=$comment_ID&c_author=" . urlencode($sk_core->cur_comment->author_email);

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
			wp_notify_postauthor($comment_ID, $sk_core->cur_comment->type);
		}
	}
}

function sk_insert_footer() {
	global $sk_settings;
	require_once(dirname(__FILE__) . '/sk_util_class.php');

	if ($sk_settings->get_core_settings('display_sk_footer'))
	{
		if ($sk_settings->get_stats('hell') < 2)
		{
			echo __($sk_settings->get_core_settings('sk_footer_msg_0'), 'spam-karma');
		}
		else
		{
			foreach (array('hell', 'purgatory', 'paradise', 'hell_total_karma', 'paradise_total_karma') as $val)
				$replace_vals['{'. $val . '}'] = $sk_settings->get_stats($val);
			echo strtr(__($sk_settings->get_core_settings('sk_footer_msg_n'), 'spam-karma'), $replace_vals);
		}
	}
}

// PLUGGABLE FUNCTIONS: overriding 

if ( !function_exists('wp_notify_moderator') ) {
	function wp_notify_moderator($comment_id) {
			global $wpdb;
	
			if( get_option('moderation_notify') == 0 )
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
			$location = get_bloginfo('wpurl') . '/wp-admin/options-general.php?page=spamkarma';

			$notify_message .= sprintf( __('To approve this comment, visit: %s', 'spam-karma'), sk_nonce_email_url($post->post_author, $location . "&recover_selection=1&comment_grp_check%5B$comment_id%5D=$comment_id&sk_section=spam"))  . "\r\n";
			//### Add l10n:
			$notify_message .= sprintf( __('To flag this comment as spam, visit: %s', 'spam-karma'), sk_nonce_email_url($post->post_author, $location . "&recover_selection=1&comment_grp_check%5B$comment_id%5D=$comment_id&sk_section=approved&sql_score_threshold=-30")) . "\r\n";
			$notify_message .= sprintf( __('Currently %s comments are waiting for approval. Please visit the moderation panel:', 'spam-karma'), $comments_waiting ) . "\r\n";
			$notify_message .= sk_nonce_email_url($post->post_author, $location . '&sk_section=spam') . "\r\n";

//### end DdV Mods
	
			$subject = sprintf( __('[%1$s] Please moderate: "%2$s"', 'spam-karma'), get_option('blogname'), $post->post_title );
			$admin_email = get_option('admin_email');
	
			$notify_message = apply_filters('comment_moderation_text', $notify_message);
			$subject = apply_filters('comment_moderation_subject', $subject);
	
			@wp_mail($admin_email, $subject, $notify_message);
		
			return true;
	}
}

if ( ! function_exists('wp_notify_postauthor') ) {
	if (function_exists('get_comment') ) {
		function wp_notify_postauthor($comment_id, $comment_type='')  {
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
				$location = get_bloginfo('wpurl') . '/wp-admin/options-general.php?page=spamkarma';
	//	echo "##" . print_r($comment_id, true) . "##" . ($location . '&recover_selection=1&comment_grp_check[' . $comment_id . ']=' . $comment_id . '&sk_section=approved') . "**" . sk_nonce_url($location . '&recover_selection=1&comment_grp_check[' . $comment_id . ']=' . $comment_id . '&sk_section=approved') . "**" . sk_nonce_email_url($location . '&recover_selection=1&comment_grp_check[' . $comment_id . ']=' . $comment_id . '&sk_section=approved') . "**";
				$notify_message .= sprintf( __('To flag this comment as spam, visit: %s', 'spam-karma'), sk_nonce_email_url($post->post_author, $location . "&recover_selection=1&comment_grp_check%5B$comment_id%5D=$comment_id&sk_section=approved&sql_score_threshold=-30")) . "\r\n";
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
				. 'Content-Type: text/plain; charset="' . get_option('blog_charset') . "\"\n";
		
			if ( isset($reply_to) )
				$message_headers .= $reply_to . "\n";
		
			$notify_message = apply_filters('comment_notification_text', $notify_message, $comment_id);
			$subject = apply_filters('comment_notification_subject', $subject, $comment_id);
			$message_headers = apply_filters('comment_notification_headers', $message_headers, $comment_id);
		
			@wp_mail($user->user_email, $subject, $notify_message, $message_headers);
		   
			return true;
		}
	
	} else {
		function wp_notify_postauthor($comment_id, $comment_type='') {
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
			$location = get_bloginfo('wpurl') . '/wp-admin/options-general.php?page=spamkarma';

			$notify_message .= sprintf( __('To flag this comment as spam, visit: %s', 'spam-karma'), sk_nonce_email_url($post->post_author, $location . "&recover_selection=1&comment_grp_check%5B$comment_id%5D=$comment_id&sk_section=approved&sql_score_threshold=-30")) . "\r\n";
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
				. 'Content-Type: text/plain; charset="' . get_option('blog_charset') . "\"\n";
			if ( isset($reply_to) )
				$message_headers .= $reply_to . "\n";
	
			$message_headers = apply_filters('comment_notification_headers', $message_headers);

			@wp_mail($user->user_email, $subject, $notify_message, $message_headers);

			return true;
		}
	}
}

function sk_nonce_url($link, $function = 'form') {
	 if  (function_exists('wp_nonce_url'))
 		return wp_nonce_url($link, 'sk_' . $function);
 	else 
 		return $link;
}

function sk_nonce_email_url($uid, $link, $function = 'form') {
	 if  (function_exists('sk_create_nonce'))
 		return $link . '&_wpnonce=' .  sk_create_nonce($uid, 'sk_' . $function);
 	else 
 		return $link;
}

function sk_nonce_field($function = 'form') {
	if ( function_exists('wp_create_nonce') ) {
		//return wp_nonce_field('sk_' . $function);
		return "<input type=\"hidden\" name=\"_wpnonce\" value=\"" .  wp_create_nonce('sk_' . $function) . "\" />";
	} else {
		return '';
	}
}

// Not using pluggable override as we don't really want to change behaviours for other parts of WP:
function sk_create_nonce($uid, $action = -1) {	
	if (function_exists('wp_hash')) {
		$i = ceil(time() / 43200);
		return substr(wp_hash($i . $action . $uid), -12, 10);
	} else {
		return '';
	}
}

function sk_comment_row_actions($actions = array(), $comment = null) {
	$details = sk_get_karma_details($comment->comment_ID);
	if ( ! empty( $details ) ) :
		$details->karma = ( empty( $details->karma ) ) ? 0 : $details->karma;
		$color = sk_get_karma_color($details->karma);
		if ( ! empty( $details->karma_cmts ) ) {
			$karma_cmts_array = unserialize($details->karma_cmts);
			if (is_array($karma_cmts_array)) {
				$karma_cmts = '<div class="karma-details" style="border-color: ' . $color . ';"><h3>' . __('Karma Details', 'spam-karma') . '</h3><ul>';
				foreach ($karma_cmts_array as $cmt) {
					$karma_cmts .=  '<li class="karma-detail ';
					if ($cmt['hit'] >= 0)
						$karma_cmts .= 'good_karma';
					else
						$karma_cmts .= 'bad_karma';
					$karma_cmts .= '"><strong>' . $cmt['hit'] . '</strong>: <em>' . sk_soft_hyphen($cmt['reason']) . '</i>';	
					$karma_cmts .= "</li>\n";
				}
				$karma_cmts .= '</ul></div>';
				echo $karma_cmts;
			}
		}
	endif;
	return $actions;
}

function sk_get_karma_color($karma = 0) {
	if ( empty($karma) ) {
		$color = 'rgb(200, 200, 256)';
	}
	elseif ($karma < -20)
		$color = 'rgb(130, 130, 130)';
	elseif ($karma < -10) {
		$x = (int) (130 + 50 * pow((20 + $karma)/10, 2));
		$color = "rgb($x, $x, $x)";
	} else {
		$x = max (0, (int) (180 - 12 * (10 + $karma)));
		$y = min(256, (int) (180 + 7.6 * (10 + $karma)));
		$color = "rgb($x, $y, $x)";
	}
	return $color;
}

function sk_get_karma_details($comment_id = 0) {
	global $wpdb;
	$comment_id = intval($comment_id);
	include_once(dirname(__FILE__) . '/sk_core_class.php');
	$query = "SELECT `karma`, `karma_cmts` FROM ". SK_SPAM_TABLE . " WHERE  comment_ID = '{$comment_id}' LIMIT 1";
	$row = $wpdb->get_row($query);
	return $row;
}

add_action('init', 'sk_init');
add_action('admin_init', 'sk_admin_init');

add_action('comment_form', 'sk_form_insert');
add_action('admin_menu', 'sk_add_options');
add_action('load-edit-comments.php', 'sk_output_admin_load');
add_action('admin_head-edit-comments.php', 'sk_output_admin_print');
add_action('comment_post', 'sk_filter_comment');
add_action('manage_comments_nav', 'sk_submit_comments_to_plugins');
add_action('wp_footer', 'sk_insert_footer', 3);

add_filter('comment_row_actions', 'sk_comment_row_actions', 10, 2);
add_filter('pre_comment_approved', 'sk_fix_approved');

?>
