<?php
/**********************************************************************************************
 Spam Karma (c) 2015 - http://github.com/strider72/spam-karma

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

class sk_payload_plugin extends sk_plugin
{
	var $name = "Encrypted Payload";
	var $author = "";
	var $plugin_help_url = "http://wp-plugins.net/wiki/?title=sk_Payload_Plugin";
	var $description = "Embed an encrypted payload in comment form. Ensures that the form has been loaded before a comment is submitted (and more).";
	var $filter = true;
	
	function form_insert($post_ID)
	{
		$seed = $this->get_option_value('secret_seed');
		if (empty ($seed))
		{
			$seed = sk_rand_str(10);
			$this->set_option_value('secret_seed', $seed);
			$this->log_msg(__("Resetting secret seed to: $seed.", 'spam-karma'), 5);
		}
		$time = time();
		$ip = $_SERVER['REMOTE_ADDR'];
		//echo ("<!--#". $time . "#". $seed . "#". $ip ."#". $post_ID . "#-->"); // debug
		$payload = md5($time . $seed . $ip . $post_ID); 
		echo '<div id="sk-payload-fields">';
		echo "<input type=\"hidden\" id=\"sk_time\" name=\"sk_time\" value=\"$time\" />";
		echo "<input type=\"hidden\" id=\"sk_ip\" name=\"sk_ip\" value=\"$ip\" />";
		echo "<input type=\"hidden\" id=\"sk_payload\" name=\"sk_payload\" value=\"$payload\" />";
		echo '</div>';
	}

	function version_update($cur_version)
	{
		$seed = sk_rand_str(10);
		$this->set_option_value('secret_seed', $seed);
		$this->log_msg(__("Resetting secret seed to: ", 'spam-karma') . $seed, 5);
		return true;
	}

	function filter_this(&$cmt_object)
	{					
		if ($cmt_object->is_post_proc())
		{
			$log = __("Cannot check encrypted payload in post_proc mode.", 'spam-karma');
			$this->log_msg($log, 4);
			return;
		}	

		if (! $cmt_object->is_comment())
			return;
		
		if (empty($_REQUEST['sk_payload']))
		{
			$log = __("Encrypted Payload missing from form.", 'spam-karma');
			$karma_diff = -20;
			$this->log_msg($log, 1);
		}
		elseif($cmt_object->post_ID != $_REQUEST['comment_post_ID'])
		{
			$log = sprintf(__("Error: Submitted Post_ID variable (%d) not matching ours (%d).", 'spam-karma'), $_REQUEST['comment_post_ID'], $cmt_object->post_ID);
			$this->log_msg($log, 9);
			$karma_diff = -8;
		}
		else
		{
			$seed = $this->get_option_value('secret_seed');
		
			if ($_REQUEST['sk_payload'] != md5($_REQUEST['sk_time'] . $seed . $_REQUEST['sk_ip'] . $cmt_object->post_ID))
			{
				$log = __("Fake Payload.", 'spam-karma');
				$karma_diff = -20;
				$this->log_msg($log, 2);
			}
			elseif ($_REQUEST['sk_ip'] == $_SERVER['REMOTE_ADDR'])
			{
				$log = __("Encrypted payload valid: IP matching.", 'spam-karma');
				$karma_diff = 0;
			}
			else
			{
				$log = __("Encrypted payload valid: IP <strong>not</strong> matching.", 'spam-karma');
				$karma_diff = - 2.5;
			}
		}
		$this->modify_karma($cmt_object, $karma_diff, $log);
	}
}

$this->register_plugin("sk_payload_plugin", 2);

?>
