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
header("Content-type: image/png");

global $sk_log, $wpdb;
require_once('../../../wp-config.php');
include_once(dirname(__FILE__) . "/sk_core_class.php");

$comment_ID = (int) @$_REQUEST['c_id'];
$author_email = @$_REQUEST['c_author'];

$sk_log->live_output = false;
$this_cmt = new sk_comment ($comment_ID);

if (@$this_cmt->ID && ($author_email == $this_cmt->author_email))
{
	foreach($this_cmt->unlock_keys as $key)
		if ($key['class'] == "sk_captcha_plugin")
			$string = strtoupper($key['key']);
}
else
	$string = __("Invalid ID", 'spam-karma');

$im  = imagecreate(150, 50);
$bg = imagecolorallocate($im, 0, 0, 0);
$red = imagecolorallocate($im, 255, 0, 0);
$px  = (imagesx($im) - 7.5 * strlen($string)) / 2;
imagestring($im, 6, 10, 10, $string, $red);
imagepng($im);
imagedestroy($im);
?>
