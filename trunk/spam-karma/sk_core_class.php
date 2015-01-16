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

define ('SK_PLUGIN_FOLDER', dirname(__FILE__) . '/sk_plugins/');
define ('SK_CORE_VERSION', 5);
define ('SK_VERSION', 4); // => 2.3
define ('SK_RELEASE', 'alpha');
if (isset($table_prefix))
	define ('SK_SPAM_TABLE', $table_prefix . 'sk_spams');
else
	define ('SK_SPAM_TABLE', SK_TABLE_PREFIX . 'sk_spams');

require_once('sk_util_class.php');
require_once('sk_plugin_class.php');
require_once('sk_comment_class.php');
require_once('sk_blacklist_class.php');
require_once('sk_functions.php');

global $sk_plugin_array;
$sk_plugin_array = 0;

class SK_Core
{
	var $cur_comment = 0;
	var $post_proc = false;
	var $plugins = array();
	var $version = SK_CORE_VERSION;
	
	function SK_Core($comment_ID = 0, $post_proc = false, $load_plugins = true)
	{
		return $this->__construct($comment_ID, $post_proc, $load_plugins);
	}

	function __construct($comment_ID = 0, $post_proc = false, $load_plugins = true)
	{
		add_action( 'admin_notices', array( &$this, 'sanity_check' ) );		

		$this->set_post_proc($post_proc);
		if ($comment_ID)
			$this->load_comment($comment_ID);
		$this->plugins = array();		
		if ($load_plugins)
			$this->load_plugin_files();
	}
	
	function load_plugin_files()
	{
		global $sk_plugin_array;
		if ($sk_plugin_array)
		{
			$this->plugins = $sk_plugin_array;
			return;
		}
		$plugin_files = sk_get_file_list(SK_PLUGIN_FOLDER);
		foreach($plugin_files as $plugin)
		{
			include_once(SK_PLUGIN_FOLDER . $plugin);	
		}	
		$sk_plugin_array = $this->plugins;
	}
	
	function load_comment($comment_ID)
	{
		if (! $comment_ID)
			return false;
		$this->cur_comment = new sk_comment($comment_ID, $this->post_proc, $comment_sk_info);
		if ($this->post_proc)
			$comment_sk_info = $this->get_comment_sk_info();
		
		return ($this->cur_comment->ID > 0);
	}
	
	function set_post_proc($post_proc)
	{
		$this->post_proc = $post_proc;
	}
	function is_post_proc()
	{
		return $this->post_proc;
	}
	
	function process_comment($comment_ID = 0)
	{
		if ($comment_ID && ($comment_ID != @$this->cur_comment->ID))
			$this->cur_comment = new sk_comment($comment_ID, $this->post_proc); // ? does PHP garbage collect? sure hope so...

		global $sk_settings;
		$this->filter_comment();
		
		if ($bias = $sk_settings->get_core_settings("general_bias"))
			$this->cur_comment->modify_karma($bias, "core", "Severity settings adjustment.");
		
		$this->treat_comment();
	
		$this->set_comment_sk_info();
	}
	
	function filter_comment ($comment_ID = 0)
	{
		$start = time();
		if ($comment_ID && ($comment_ID != @$this->cur_comment->ID))
			$this->cur_comment = new sk_comment($comment_ID, $this->post_proc); // ? does PHP garbage collect? sure hope so...

		if (! $this->cur_comment)
		{
			$this->log_msg(__("Cannot run <code>filter_comment</code>: no valid comment provided.", 'spam-karma'), 9);
			return;
		}
		
		$this->log_msg(__("Running filters on comment ID: ", 'spam-karma') . $this->cur_comment->ID, 0);

		foreach ($this->plugins as $plugin)
		{
			if ($plugin[1]->is_filter()
				&& ($plugin[1]->is_enabled())
				&& ($this->post_proc
					||	(((int) $this->cur_comment->karma > $plugin[1]->skip_under)
						&& ((int) $this->cur_comment->karma < $plugin[1]->skip_above))))
					$plugin[1]->filter_this($this->cur_comment);
		}
		
		$dur = time() - $start;
		if ($dur > 1000)
			$this->log_msg(sprintf(__("Filter processing on comment ID %d took %d ms.", 'spam-karma'), $this->cur_comment->ID, $dur), 3);
	}
	
	function treat_comment ($comment_ID = 0)
	{
		$start = time();
		if ($comment_ID && ($comment_ID != @$this->cur_comment->ID))
			$this->cur_comment = new sk_comment($comment_ID, $this->post_proc);

		if (! $this->cur_comment)
		{
			$this->log_msg(__("Cannot run <code>treat_comment</code>: no valid comment provided.", 'spam-karma'), 9);
			return;
		}

		$this->log_msg(__("Running treatments on comment ID: ", 'spam-karma') . $this->cur_comment->ID, 0);
				
		foreach ($this->plugins as $plugin)
			if ($plugin[1]->is_treatment()
				&& ($plugin[1]->is_enabled()))
				$plugin[1]->treat_this($this->cur_comment);

		$dur = time() - $start;
		if ($dur > 1000)
			$this->log_msg(sprintf(__("Treatment processing on comment ID %d took %d ms.", 'spam-karma'), $this->cur_comment->ID, $dur), 3);

	}
	
	function form_insert ($post_ID = 0)
	{
		foreach ($this->plugins as $plugin)
			if ($plugin[1]->is_enabled())
				$plugin[1]->form_insert($post_ID);
	}


	function second_chance()
	{
		if (! $this->cur_comment)
			return false;

		$rem_attempts = (int) $this->cur_comment->remaining_attempts;
			
		if($rem_attempts <= 0)
		{
			echo ("<div class=\"sk_box\">" . __("Too many unlock attempts.", 'spam-karma') . "</div>");
			return false;
		}
		
		if (! $this->cur_comment->can_unlock())
		{
			echo ("<div class=\"sk_box\">" . __("This comment wasn't given a second chance.", 'spam-karma') . "</div>");
			return false;
		}
		
		if (!empty ($_REQUEST['sk_second_chance']))
		{
			$class = $_REQUEST['sk_second_chance'];
			$this_key = 0;
			foreach ($this->cur_comment->unlock_keys as $id => $unlock_key)
				$this_key = $unlock_key;

			if ($this_key
				&& ($this_key['expire'] > time())
				&& ($my_plugin = $this->get_plugin($this_key['class']))
				&& $my_plugin->is_enabled())
			{
				
				echo "<div class=\"sk_box\">";
				if ($my_plugin->treat_second_chance($this->cur_comment, $this_key['key']))
				{
					$this->cur_comment->post_proc = true; // just making sure we don't run into loops here
				//	if ($anubis = $this->get_plugin('sk_anubis_plugin'))
				//		$anubis->treat_this($this->cur_comment);
					$this->treat_comment();
					$this->cur_comment->remaining_attempts = 0; // no more attempts
					
					if ($this->cur_comment->approved == '1')
					{
						echo __("Thank you. Your comment has been approved.", 'spam-karma');
						if ( get_option('comments_notify') )
							wp_notify_postauthor($this->cur_comment->ID, $this->cur_comment->type);
						//TODO redirect to comment page...
					}
					else
					{
						echo __("Thank you. Your comment will be displayed as soon as it is approved by a moderator.", 'spam-karma');
						wp_notify_moderator($this->cur_comment->ID);
					}
				}
				else
				{
					$this->cur_comment->remaining_attempts = --$rem_attempts;
					if ($rem_attempts <= 0)
					{
						//if ($anubis = $this->get_plugin('sk_anubis_plugin'))
						//	$anubis->treat_this($this->cur_comment);
						$this->treat_comment($this->cur_comment);
						echo "<span class=\"sk_fail\">" . __("Too many missed attempts. Your comment's moderation has been confirmed. A log of your comment will be kept and presented to the blog admin upon his next log-on. Please contact him directly via e-mail regarding this problem.", 'spam-karma') . "</span>";
					}
					else
					{
						echo "<span class=\"sk_fail\">" . sprintf(__ngettext("Sorry, bad luck on this one, cow-boy, try again. You have %d attempt left.", "Sorry, bad luck on this one, cow-boy, try again. You have %d attempts left.", $rem_attempts, 'spam-karma'), $rem_attempts) . "</span>";
					}
				}
				$this->set_comment_sk_info();
				echo "</div>";
			}
			else
			{
				echo ("<div class=\"sk_box\">" . __("Can't use this unlock method.", 'spam-karma') . "</div>");
				$this->cur_comment->remaining_attempts = 0;
				$this->set_comment_sk_info();
				return false;
			}
							
		}
		else
		{
			$i = 0;
			foreach($this->cur_comment->unlock_keys as $id => $unlock_key)
			{
				$which_plugin_obj = $this->get_plugin($unlock_key['class']);
				$msg = "";
				$msg_level = 0;
				
				echo "<div class=\"sk_box\">";

				if (! $which_plugin_obj)
				{
					$msg = "<strong>" . sprintf(__("Cannot find 2nd chance plugin file: %s", 'spam-karma'), $unlock_key['class']) . "</strong>";
					$msg_level = 9;
				}
				elseif (! $which_plugin_obj->is_enabled())
				{
					$msg = "<strong>" . sprintf(__("2nd chance: %s plugin disabled.", 'spam-karma'), $which_plugin_obj->name) . "</strong>";
					$msg_level = 6;
				}
				elseif ($unlock_key['expire'] < time())
				{
					$msg = "<strong>" . sprintf(__("%s: unlock key expired.", 'spam-karma'), $which_plugin_obj->name) . "</strong>";
					$msg_level = 6;
				}
				else
				{
					$i++;
					echo "<form name=\"sk_form_". $unlock_key['class'] . "\" id=\"sk_form_". $unlock_key['class'] . "\ method=\"post\">";
					echo "<input type=\"hidden\" name=\"sk_second_chance\" id=\"sk_second_chance\" value=\"". $unlock_key['class'] . "\">";
					echo "<input type=\"hidden\" name=\"c_id\" id=\"c_id\" value=\"" . $this->cur_comment->ID . "\">";
					echo "<input type=\"hidden\" name=\"c_author\" id=\"c_author\" value=\"" . $this->cur_comment->author_email . "\">";

					$which_plugin_obj->display_second_chance($this->cur_comment, $unlock_key['key']);
					echo "</form>";
				}
				
				if ($msg_level)
				{
					echo $msg;
					$this->log_msg($msg, $msg_level);
				}
				
				echo "</div>";
			
			}
		
			if ($i <= 0)
				$this->cur_comment->remaining_attempts = 0;
		}
		
		$this->set_comment_sk_info();
	}
	
	// TODO: maybe change default priority to 10 just for consistency with WP hook priority norms.
	function register_plugin($plugin_class, $priority = 5)
	{
		$i = 0;
		foreach ($this->plugins as $elem)
			if ( $elem[0] <= $priority )
				$i++;

		$this->log_msg(__("Registering plugin: ", 'spam-karma') . "<i>" . $plugin_class . "</i>, " . __("priority: ", 'spam-karma') . $priority);
		array_splice( $this->plugins, $i, 0, array( array( $priority, new $plugin_class(), $plugin_class) ) );
	}
	
	function output_UI()
	{
?>
	  <div class="hide" id="sk_settings_pane"><h3><?php _e("General Settings", 'spam-karma'); ?></h3>
	  <form name="sk_settings_form" id="sk_settings_form" method="post">
		<fieldset class="options">
		<ul>
<?php
		echo sk_nonce_field();

		global $sk_settings;
		foreach($sk_settings->core_defaults as $name => $format)
		{
			if (@$format['auto_draw'] != true)
				continue; // no UI
				
			echo "<li";
			if (@$format['advanced'])
				 echo " class=\"advanced\"";
			echo ">\n";
			if ($format['type'] == "check" || $format['type'] == "checkbox")
			{
				sk_plugin::output_UI_input($name, "checkbox", $sk_settings->get_core_settings($name));
				if (!empty($format['caption']))
					echo " " . __($format['caption'], 'spam-karma');
				if (!empty($format['after']))
					echo " " . __($format['after'], 'spam-karma');
			}
			else
			{
				if (! empty($format['caption']))
					echo __($format['caption'], 'spam-karma') . " ";
				if ($format['type'] == "menu" || $format['type'] == "select")
					sk_plugin::output_UI_menu($name, $format['options'], $sk_settings->get_core_settings($name));
				else 
					sk_plugin::output_UI_input($name, "text", $sk_settings->get_core_settings($name), @$format['size']);
				
				if (! empty($format['after']))
					echo " " . __($format['after'], 'spam-karma');
			}
			
			echo "</li>\n";
		}
			
?>	
		</ul></fieldset>
		  <p class="submit"><input type="submit" id="sk_settings_save" name="sk_settings_save" value="<?php _e("Save new settings", 'spam-karma'); ?>"></p>
		<h3><?php _e("Filter Plugins Settings", 'spam-karma'); ?></h3>
		<fieldset class="options">
<?php
		foreach ($this->plugins as $plugin)
			if ($plugin[1]->is_filter())
				$plugin[1]->output_plugin_UI();
?>
		</fieldset>
		<h3><?php _e("Treatment Plugins Settings", 'spam-karma'); ?></h3>
		<fieldset class="options">
<?php
		foreach ($this->plugins as $plugin)
			if ($plugin[1]->is_treatment() && ! $plugin[1]->is_filter())
				$plugin[1]->output_plugin_UI();
?>
		</fieldset>
						
		  <p class="submit"><input type="submit" id="sk_settings_save" name="sk_settings_save" value="<?php _e("Save new settings", 'spam-karma'); ?>"></p>
		</form></div>
<?php		
	
	}
	
	function update_SQL_schema()
	{
		global $sk_settings;
		
		$mysql_updates = $sk_settings->get_core_settings("mysql_updates");
		
		if (@$mysql_updates['core'] < $this->version)
		{
			if ($this->update_core_SQL_schema (@$mysql_updates['core']))
			{
				$this->log_msg(sprintf(__("Updated SQL schema for the core (to version: %d).", 'spam-karma'), $this->version), 5);
				$mysql_updates['core'] = $this->version;
			}
			else
				$this->log_msg(sprintf(__("Failed to update SQL schema for the core (to version: %d).", 'spam-karma'), $this->version), 8);
		}	

		foreach ($this->plugins as $plugin)
		{
			$class_name = $plugin[2];
			if (@$mysql_updates[$class_name] < $plugin[1]->plugin_version)
			{
				if ($plugin[1]->update_SQL_schema (@$mysql_updates[$class_name]))
				{
					$this->log_msg(__("Updated SQL schema for plugin: ", 'spam-karma') . "<em>" . $plugin[1]->name . "</em> ". sprintf(__("(to version: %d).", 'spam-karma'), $plugin[1]->plugin_version), 5);
					$mysql_updates[$class_name] = $plugin[1]->plugin_version;
				}
				else
					$this->log_msg(__("Failed to update SQL schema for plugin: ", 'spam-karma') . "<em>" . $plugin[1]->name . "</em> ". sprintf(__("(to version: %d).", 'spam-karma'), $plugin[1]->plugin_version), 8);
			}
		}
		
		$sk_settings->set_core_settings($mysql_updates, "mysql_updates");
	}
	
	function update_components()
	{
		global $sk_settings;
		
		$version_updates = $sk_settings->get_core_settings("version_updates");
		
		if (@$version_updates['core'] < $this->version)
		{
			if ($this->update_core (@$version_updates['core']))
			{
				$this->log_msg(__("Initialized the core", 'spam-karma') . " " . sprintf(__("(to version: %d).", 'spam-karma'), $this->version), 5);
				$version_updates['core'] = $this->version;
			}
			else
				$this->log_msg(__("Failed to initialize the core", 'spam-karma') . " " . sprintf(__("(to version: %d).", 'spam-karma'), $this->version), 8);
		}	

		foreach ($this->plugins as $plugin)
		{
			$class_name = $plugin[2];

			if (@$version_updates[$class_name] < $plugin[1]->plugin_version)
			{
				if ($plugin[1]->version_update (@$version_updates[$class_name]))
				{
					$this->log_msg(__("Updated plugin: ", 'spam-karma') . "<em>" . $plugin[1]->name . "</em> " . sprintf(__("(to version: %d).", 'spam-karma'), $plugin[1]->plugin_version), 5);
					$version_updates[$plugin[2]] = $plugin[1]->plugin_version;
				}
				else
					$this->log_msg(__("Failed to update plugin: ", 'spam-karma') . "<em>" . $plugin[1]->name . "</em> " . sprintf(__("(to version: %d).", 'spam-karma'), $plugin[1]->plugin_version), 8);
			}
		}
		
		$sk_settings->set_core_settings($version_updates, "version_updates");
	}
	
	function update_core ($cur_version)
	{
		// doing nothing right now
		return true;
	}
	
	function update_core_SQL_schema ($cur_version)
	{
		global $wpdb;
		$success = true;
		
		if ($cur_version == 0)
		{
			$query = "CREATE TABLE IF NOT EXISTS `" . SK_SPAM_TABLE . "` (
	 `id` int(11) NOT NULL auto_increment,
	 `comment_ID` int(11) NOT NULL default '0',
	 `karma` float(2) NOT NULL default '0',
	 `karma_cmts` text NOT NULL,
	 `last_mod` datetime NOT NULL default '0000-00-00 00:00:00',
	 `unlock_keys` tinytext NOT NULL,
	 `remaining_attempts` INT NOT NULL,
		PRIMARY KEY (`id`),
		KEY `comment_ID` (`comment_ID`)
	) TYPE=MyISAM;";
	
			$wpdb->query($query);
			if (mysql_error())
			{
				$this->log_msg(__("Could not create SQL table: ", 'spam-karma') . SK_SPAM_TABLE . ".", 10, true);
				$success = false;
			}	
	
			$query = "CREATE TABLE IF NOT EXISTS `" . SK_LOGTABLE . "` (
	`id` INT NOT NULL AUTO_INCREMENT ,
	`msg` TEXT NOT NULL ,
	`component` TINYTEXT NOT NULL ,
	`level` TINYINT NOT NULL ,
	`ts` DATETIME NOT NULL ,
	PRIMARY KEY ( `id` ) 
	) TYPE=MyISAM;";
		
			 $wpdb->query($query);
			if (mysql_error())
			{
				$this->log_msg(__("Could not create SQL table: ", 'spam-karma') . SK_LOGTABLE . ".", 10, true);
				$success = false;
			}	
			
			$query = "CREATE TABLE IF NOT EXISTS `". SK_BLACKLIST_TABLE ."` (
	 `id` int(11) NOT NULL auto_increment,
	 `type` tinytext NOT NULL,
	 `value` text NOT NULL,
	 `score` int(11) NOT NULL default '0',
	 `trust` int(11) NOT NULL default '0',
	 `comments` text NOT NULL,
	 `added` datetime NOT NULL default '0000-00-00 00:00:00',
	 `added_by` tinytext NOT NULL,
	 `last_used` datetime NOT NULL default '0000-00-00 00:00:00',
	 `used_count` int(11) NOT NULL default '0',
	 `user_reviewed` enum('yes','no') NOT NULL default 'no',
	 PRIMARY KEY (`id`)
	) TYPE=MyISAM ;";
		
			$wpdb->query($query);
			if (mysql_error())
			{
				$this->log_msg(__("Could not create SQL table: ", 'spam-karma') . SK_BLACKLIST_TABLE . ".", 10, true);
				$success = false;
			}	
		}
		elseif ($cur_version == 1)
		{
			$query = "ALTER TABLE `" . SK_SPAM_TABLE . "` ADD INDEX ( `comment_ID` )";

			$wpdb->query($query);
			if (mysql_error())
			{
				$this->log_msg(__("Could not alter SQL table: ", 'spam-karma') . SK_SPAM_TABLE . ".", 10, true);
				$success = false;
			}
			else
				$this->log_msg(__("Successfully created index for SQL table: ", 'spam-karma') . SK_SPAM_TABLE . ".", 5, false);
		}
	
		// modifying WP's tables to work correctly...
		// Still returns success on SQL failure (so it doesn't keep trying adding indices in case they are already there or the tables have been otherwise modified)
		$query = "ALTER TABLE `" . $wpdb->comments . "` ADD INDEX `comment_date_gmt` ( `comment_date_gmt`)";

		$wpdb->query($query);
		if (! mysql_error())
			$this->log_msg(__("Successfully created index for SQL table: ", 'spam-karma') . $wpdb->comments . ".", 5, false);
			
		$query = "ALTER TABLE `" . $wpdb->posts . "` ADD INDEX `post_date` ( `post_date`)";

		$wpdb->query($query);
		if (! mysql_error())
			$this->log_msg(__("Successfully created index for SQL table: ", 'spam-karma') . $wpdb->posts . ".", 5, false);
		
		$query = "ALTER TABLE `" . $wpdb->posts . "` ADD INDEX `post_date_gmt` ( `post_date_gmt`)";

		$wpdb->query($query);
		if (! mysql_error())
			$this->log_msg(__("Successfully created index for SQL table: ", 'spam-karma') . $wpdb->posts . ".", 5, false);
				
		return $success;
	}
	
	function save_UI_settings($form_values)
	{
		global $sk_settings;
		//print_r($form_values);
		
		if (isset($form_values["sk_settings_save"]))
		{
			if (isset($form_values['sk_filter_options']))
			{
				array_walk($form_values['sk_filter_options'], "sk_unescape_form_string_callback");
				$this->log_msg(sprintf(__ngettext("Saving new settings for %d plugin.", "Saving new settings for %d plugins.", count($form_values['sk_filter_options']), 'spam-karma'), count($form_values['sk_filter_options'])), 3, 'spam-karma');

				if (isset($form_values['sk_filter_checkboxes']))
					foreach($form_values['sk_filter_checkboxes'] as $check_plugin => $check_name)
						if (! isset($form_values['sk_filter_options'][$check_plugin][$check_name]))
							$form_values['sk_filter_options'][$check_plugin][$check_name] = false;
			
				if (isset($form_values['sk_filter_options'][get_class($this)]))
				{
					foreach($form_values['sk_filter_options'][get_class($this)] as $name => $value)
						$sk_settings->set_core_settings($value, $name);
					unset($form_values['sk_filter_options'][get_class($this)]);
				}

				$sk_settings->set_plugins_settings($form_values['sk_filter_options']);
			}
		}
	}

	function get_plugin($this_one)
	{
		$which_plugin_obj = 0;
		foreach ($this->plugins as $plugin)
			if ($plugin[2] == $this_one)
				$which_plugin_obj = $plugin[1];
		
		return $which_plugin_obj;
	}
	
	function set_comment_sk_info($comment_ID = 0, $comment_sk_info = 0, $append = false)
	{
		// if $comment_ID != 0: must provide $comment_sk_info
		global $sk_settings, $wpdb;
		
		if (! $comment_ID)
		{
			if (! $this->cur_comment->ID)
			{
				$this->log_msg(__("Cannot update SK_SPAM_TABLE info (no comment ID provided).", 'spam-karma'), 8);
				return false;
			}

			$comment_sk_info = array();
			$comment_ID = $comment_sk_info['comment_ID'] = $this->cur_comment->ID;
			$comment_sk_info['karma'] = $this->cur_comment->karma;
			$comment_sk_info['karma_cmts'] = $this->cur_comment->karma_cmts;
			$comment_sk_info['unlock_keys'] = $this->cur_comment->unlock_keys;
			$comment_sk_info['remaining_attempts'] = $this->cur_comment->remaining_attempts;
		}
	
		$comment_sk_info_orig = $this->get_comment_sk_info($comment_ID);

		if ($comment_sk_info_orig)
		{
			if ($append)
			{
				if(! is_array($comment_sk_info_orig->karma_cmts))
					$comment_sk_info_orig->karma_cmts = array();
				if(! is_array($comment_sk_info_orig->unlock_keys))
					$comment_sk_info_orig->unlock_keys = array();
				if(! is_array($comment_sk_info['karma_cmts']))
					$comment_sk_info['karma_cmts'] = array();
				if(! is_array($comment_sk_info['unlock_keys']))
					$comment_sk_info['unlock_keys'] = array();
				$comment_sk_info['karma_cmts'] = $comment_sk_info_orig->karma_cmts + $comment_sk_info['karma_cmts'];
				$comment_sk_info['unlock_keys'] = $comment_sk_info_orig->unlock_keys + $comment_sk_info['unlock_keys'];
				if (! isset($comment_sk_info['karma']))
					$comment_sk_info['karma'] = $comment_sk_info_orig->karma;
				if (! isset($comment_sk_info['remaining_attempts']))
					$comment_sk_info['remaining_attempts'] = $comment_sk_info_orig->remaining_attempts;
			
		//	print_r($comment_sk_info);
			}
			
			$query = "UPDATE `". SK_SPAM_TABLE ."` SET ";
			$query_end = "`last_mod` = NOW() WHERE `id` = " . $comment_sk_info_orig->id;
		}
		else
		{
			$query = "INSERT INTO `". SK_SPAM_TABLE ."` SET ";
			$query_end = "`last_mod` = NOW(), `comment_ID` = $comment_ID";
		}

		foreach ($comment_sk_info as $key => $val)
		{
			if ($key == 'comment_ID')
				continue;
			if (is_array($val))
				$val = serialize($val);
			if (is_int($val) || is_float($val))
				$query .= "`$key` = " . $val . ",";
			else
				$query .= "`$key` = '" . sk_escape_string($val) . "', ";
		}
		$query .= $query_end;
		//echo $query;
		$wpdb->query($query);
		if (! mysql_error())
			$this->log_msg(__("Inserted/Updated SK_SPAM_TABLE record for comment ID: ", 'spam-karma') . $comment_ID . " (". ($append ? __("mode: append", 'spam-karma') : __("mode: overwrite", 'spam-karma')) . ").", 0);
		else
			$this->log_msg(__("Failed inserting/updating SK_SPAM_TABLE record for comment ID:", 'spam-karma') . $comment_ID . " (". ($append ? __("mode: append", 'spam-karma') : __("mode: overwrite", 'spam-karma')) . "). <br/>" . __("Query: ", 'spam-karma') . "<code>$query</code>", 8, true);

	}
	
	function get_comment_sk_info($comment_ID = 0)
	{
		global $wpdb;

		if (! $comment_ID)
			$comment_ID = $this->cur_comment->ID;

		if (! $comment_ID)
		{
		echo 
			$this->log_msg(__("get_comment_sk_info: Cannot get SK_SPAM_TABLE info (no comment ID provided).", 'spam-karma'), 8);
			return false;
		}

		if ($comment_sk_info = $wpdb->get_row("SELECT * FROM `". SK_SPAM_TABLE ."` WHERE `comment_ID` = $comment_ID"))
		{

			if (! empty($comment_sk_info->karma_cmts))
				$comment_sk_info->karma_cmts = unserialize($comment_sk_info->karma_cmts);
			
			if (! is_array($comment_sk_info->karma_cmts))
				$comment_sk_info->karma_cmts = array();

			if (! empty($comment_sk_info->unlock_keys))
				$comment_sk_info->unlock_keys = unserialize($comment_sk_info->unlock_keys);

			if (! is_array($comment_sk_info->unlock_keys))
				$comment_sk_info->unlock_keys = array();
				
			return $comment_sk_info;
		}
		else
			return false;
	}
	
	// sanity_check() makes sure we've at least been run *once*
	function sanity_check()
	{
		global $sk_settings;
		if ( isset($_REQUEST['sk_section'] ) || ( isset( $_REQUEST['page'] ) && 'spamkarma' == $_REQUEST['page'] ) )
			return;
			
		$mysql_updates = $sk_settings->get_core_settings("mysql_updates");
		$version_updates = $sk_settings->get_core_settings("version_updates");
		
		if (empty($mysql_updates['core']) || empty($version_updates['core'])) {
			$message = __('It sounds like SK has been recently installed on this blog, but not configured.', 'spam-karma');
		} elseif (($mysql_updates['core'] < $this->version) || ($version_updates['core'] < $this->version)) {
			$message = __('It sounds like SK has recently been updated on this blog. But not fully configured.', 'spam-karma');
		}

		if (!empty ($message)) {
			$message .= ' ' . sprintf(__("You MUST visit %sSpam Karma's admin page%s at least once before letting it filter your comments (chaos may ensue otherwise).", 'spam-karma'), "<a href=\"". get_bloginfo('wpurl') . "/wp-admin/options-general.php?page=spamkarma" . "\">", "</a>");
		
			echo "<div class=\"error\">$message</div>";
		}
	}
	
	function advanced_tools($run_tools)
	{
		if (! is_array($run_tools))
			$run_tools = array($run_tools => true);
		
		global $sk_settings, $wpdb;
		
		if (isset($run_tools['force_sql_update']))
		{
			$sk_settings->set_core_settings("", "mysql_updates");
			$this->log_msg(__("Forcing MySQL updates on core and plugins.", 'spam-karma'), 3);
		}
		
		if (isset($run_tools['reinit_plugins']))
		{
			$sk_settings->set_core_settings("", "version_updates");
			$sk_settings->reset_plugin_settings();
			$this->log_msg(__("Reinitialize all plugins.", 'spam-karma'), 3);
		}
		
		if (isset($run_tools['reinit_all']))
		{
			$sk_settings->reset_all_settings();
			$this->log_msg(__("Reinitialize everything to factory settings.", 'spam-karma'), 3);
		}
		
		if (isset($run_tools['reset_all_tables']))
		{
			$this->log_msg(__("Dropping all SK Tables!", 'spam-karma'), 8);
			$wpdb->query("DROP TABLE `". SK_SPAM_TABLE . "`;");
			$wpdb->query("DROP TABLE `". SK_LOGTABLE . "`;");
			$wpdb->query("DROP TABLE `". SK_BLACKLIST_TABLE . "`;");
			$this->log_msg(__("Dropped all SK Tables!", 'spam-karma'), 7);
			$sk_settings->set_core_settings("", "mysql_updates");
			$this->log_msg(__("Forcing MySQL updates on core and plugins.", 'spam-karma'), 6, 0, "web_UI");
		}
	
		if(isset($run_tools['check_comment_form']))
		{
			$found = 0;
			foreach(array("comments.php", "comments-popup.php") as $file_name)
			{
				$content = "";
				if ($file = @fopen(TEMPLATEPATH . "/$file_name", "r"))
				{
					while(! feof($file))
						$content .= fread($file, 4096);
					if (preg_match("#do_action.*comment_form#", $content))
					{
						$found++;
						echo "<div class=\"msg_good\">" . sprintf(__("File <code>%s</code> is OK.", 'spam-karma'), $file_name) . "</div>";
					}
					else
					{
						echo "<div class=\"msg_bad\">" . sprintf(__("File <code>%s</code>: Could not find <code>do_action</code> hook. Some plugins will fail. Please refer to <a href=\"http://wp-plugins.net/wiki/index.php?title=sk_Theme_Compatibility\">SK's documentation</a>.", 'spam-karma'), $file_name) . "</div>";
					}
				}
				else
				{
					echo "<div class=\"msg_good\">" . sprintf(__("File <code>%s</code> looks OK (not present in the active theme, should be using WP default).", 'spam-karma'), $file_name) . "</div>";
					$found++;
				}
				
			}
			
			if ($found < 2)
			{
				if ($payload_plugin = $this->get_plugin("sk_payload_plugin"))
				{
					$payload_plugin->set_option_value("weight", "0.0");
					echo "<div class=\"msg_bad\">" . __("Temporarily disabling form payload check.", 'spam-karma') . "</div>";				
				}
			}
			else
			{
				echo "<div class=\"msg_good\">" . __("Your theme appears to be compatible (but you might want to run the <strong>Advanced Theme Compatibility Check</strong> at the bottom of the General Settings screen, for extra security).", 'spam-karma') . "</div>";
			}
		}
		
		if(isset($run_tools['check_comment_form_2']))
		{
			if (empty($run_tools['check_comment_form_2_url']))
			{
				echo "<div class=\"msg_bad\">" . __("You need to provide the URL address to a page on your blog that contains a comment form (e.g. a single entry permalink).", 'spam-karma') . "</div>";
			}
			else
			{
				global $sk_settings;
				if ($payload_plugin = $this->get_plugin("sk_payload_plugin"))
				{
					$save_weight = $payload_plugin->get_option_value("weight");
					$payload_plugin->set_option_value("weight", "1.0");
					$sk_settings->save_settings();
				}
				else
				{
					echo "<div class=\"msg_bad\">" . __("Cannot load Payload plugin. You need to have this plugin present in your Plugins directory to use the advanced Theme check.", 'spam-karma') . "</div>";
					return;
				}
				
 				global $curl_error;
				$content = sk_get_url_content($run_tools['check_comment_form_2_url']);
				$payload_plugin->set_option_value("weight", $save_weight);				
				$sk_settings->save_settings();
				
				if (empty ($content))
				{
					if ($curl_error == 22)
					{
						### Add l10n
						echo "<div class=\"msg_bad\">" . __("SK was unable to open that page (an HTTP error was returned). This may be due to a bad URL or some form of anti-bot script present on your server.", 'spam-karma') . "</div>";
					}
					else
					{
						echo "<div class=\"msg_bad\">" . __("Couldn't open the page: You need to provide a valid URL address to a page on your blog that contains a comment form (e.g. a single entry permalink).", 'spam-karma') . "</div>";
					}
				}
				else
				{
					if (strpos($content, "id=\"sk_payload\"") !== FALSE)
					{
						echo "<div class=\"msg_good\">" . sprintf(__("URL %s contains the Payload. Your theme appears to be OK.", 'spam-karma'), "<i>" . ($run_tools['check_comment_form_2_url']) . "</i>") . "</div>";
						echo "<div class=\"msg_good\">" . __("Re-enabling form payload check.", 'spam-karma') . "</div>";
						$payload_plugin->set_option_value("weight", 1.0);	
					}
					else
					{
						echo "<div class=\"msg_bad\">" . sprintf(__("URL %s: Could not find Payload in comment form. Your theme is likely not compatible. Make sure you used a URL that contains your blog's comment form, try again and, if still getting this result, please refer to <a href=\"http://wp-plugins.net/wiki/index.php?title=sk_Theme_Compatibility\">SK's documentation</a>.", 'spam-karma'), "<i>" . ($run_tools['check_comment_form_2_url']) . "</i>") . "</div>";
						echo "<div class=\"msg_bad\">" . __("Temporarily disabling form payload check.", 'spam-karma') . "</div>";				
						$payload_plugin->set_option_value("weight", 0.0);				
					}
			
				}
			}				
		}
	}
	
	function log_msg($msg, $level = 0, $mysql = false)
	{
		global $sk_log;
		if ($mysql)
			$sk_log->log_msg_mysql($msg, $level, 0, "core");
		else
			$sk_log->log_msg($msg, $level, 0, "core");
	}
}

?>
