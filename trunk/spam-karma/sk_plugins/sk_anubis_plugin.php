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
// Blacklist Filter
// Runs URLs and IPs through each blacklist

class sk_anubis_plugin extends sk_plugin
{
	var $name = "Anubis";
	var $description = "This plugin is the ultimate judge of a comment's fate: the comment's karma is weighted and it is either discarded as spam, moderated or displayed. <strong>Do not</strong> disable, unless you really know what you are doing.";
	var $author = "";
	var $plugin_help_url = "http://wp-plugins.net/wiki/?title=SK2_Anubis_Plugin";
	var $treatment = true;
	var $weight_levels = array("0" => "Disabled", "1.0" => "Enabled");

//### Add l10n (purgatory)
	var $settings_format = array ("paradise_border" => array("advanced" => true, "type" => "text", "value"=> 0, "caption" => "Send Comments, Trackbacks and Pingbacks with karma over ", "size" => 3, "after" => " to <strong>Paradise</strong> (approved and displayed with no moderation)."), 
												"purgatory_border" => array("advanced" => true, "type" => "text", "value"=> -6, "caption" => "Send Comments with karma over", "size" => 3, "after" => "to <strong>Purgatory</strong> (moderation with email notification)."),
												"tb_purgatory_border" => array("advanced" => true,"type" => "text", "value"=> -4, "caption" => "Send PingBacks or Trackbacks with karma over", "size" => 3, "after" => "to <strong>Purgatory</strong>.<br/><br/><i>Everything else will be left to burn silently in the flames of Hell and will only appear in the Spam Harvest and optional digest.</i>"));

	
	
	function treat_this(&$cmt_object)
	{
		global $wpdb, $sk2_settings;
		
		if ($cmt_object->is_post_proc())
			$hell_purgatory_border = min ($this->get_option_value("purgatory_border") + 4, 0);
		elseif ($cmt_object->is_comment())
			$hell_purgatory_border = $this->get_option_value("purgatory_border"); //DEBUG: eventually will be raised... just being safe for now
		else
			$hell_purgatory_border = $this->get_option_value("tb_purgatory_border");
		
		$cmt_object->karma = (float) $cmt_object->karma;
		
		if (! $cmt_object->can_unlock()
			&& ($cmt_object->karma < $hell_purgatory_border))
		{
			$new_status = 'spam';
			$treatment = 'hell';
		}
		elseif ($cmt_object->karma < $this->get_option_value("paradise_border"))
		{
			$new_status = 'moderated';
			$treatment = 'purgatory';
		}
		else
		{
			$new_status = 'approved';
			$treatment = 'paradise';
		 	if(function_exists('wp_cache_post_change'))
       			 wp_cache_post_change($cmt_object->post_ID);
		}
		
		if ($cmt_object->set_DB_status($new_status, get_class($this)))
		{
			$this->log_msg(sprintf(__("%s (ID: %d) sent to: %s (Karma: ).", 'sk2'), ucfirst($cmt_object->type), $cmt_object->ID, '<b>' . __($treatment, 'sk2') . '</b>', $cmt_object->karma), 3);
			$sk2_settings->increment_stats($treatment);
			$sk2_settings->increment_stats($treatment . "_total_karma", $cmt_object->karma);
		}
	}
		
}

$this->register_plugin("sk_anubis_plugin", 11); // should be loaded last...


?>
